<?php
namespace application\models;
use \library\MVC as l;

class Upgrade extends l\Model {
    /* upgrade table
        1	id        int(11)			AUTO_INCREMENT
        2	id_user   int(11)
		3	txn_id	  varchar(64)	Transaction ID, Must be unique
        4	size      bigint(20)
        5	price     float
		6	currency  varchar(10)
		7	start	  int(11)
		8	end	      int(11)
		9	removed	  tinyint(1)	Confirmation that upgrade has been removed from user storage quota
    */

    protected $upgrades = null;
	protected $id_user = null;

	function __construct($id_user = null) {
		parent::__construct();
		if(is_numeric($id_user)) {
			$this->id_user = $id_user;
			$req = self::$_sql->prepare("SELECT * FROM upgrade WHERE id_user = ? ORDER BY `end` DESC");
			$req->execute([$this->id_user]);
			$this->upgrades = $req->fetchAll(\PDO::FETCH_ASSOC);
		}
	}

	function getUpgrades() {
		return $this->upgrades;
	}

	function transactionExists($txn_id) {
		$req = self::$_sql->prepare("SELECT id FROM upgrade WHERE txn_id = ?");
		$req->execute([$txn_id]);
		if($req->rowCount() > 0) return true;
		return false;
	}

    function getDaysLeft($id_user) {
        $req = self::$_sql->prepare("SELECT `end` FROM upgrade WHERE id_user = ? AND removed = 0");
        $req->execute([$id_user]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return floor(($res['end'] - time())/(24 * 60 * 60));
    }

    function expiresSoon($id_user) {
        $req = self::$_sql->prepare("SELECT `end` FROM upgrade WHERE id_user = ? AND removed = 0");
        $req->execute([$id_user]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        if($res['end'] > (time() + (7 * 24 * 60 * 60))) return false;
        return true;
    }

    function hasExpired($id_user) {
        $req = self::$_sql->prepare("SELECT * FROM upgrade WHERE id_user = ? AND removed = 0");
        $req->execute([$id_user]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        if($res['end'] > time()) return false;
        $this->expires($res['id'], $id_user, $res['size']);
        return true;
    }

    function expires($id_plan, $id_user, $storage_size) {
        $req = self::$_sql->prepare("UPDATE upgrade SET removed = 1 WHERE id = ? AND id_user = ? AND removed = 0");
        $req->execute([$id_plan, $id_user]);

        $req = self::$_sql->prepare("UPDATE storage SET user_quota = user_quota-? WHERE id_user = ?");
        $req->execute([$storage_size, $id_user]);
    }

    function cancelSubscription($id_user) {
        $req = self::$_sql->prepare("SELECT * FROM upgrade WHERE id_user = ? AND removed = 0");
        $req->execute([$id_user]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        $storage_size = $res['size'];

        $req = self::$_sql->prepare("UPDATE upgrade SET removed = 1 WHERE id_user = ? AND removed = 0");
        $req->execute([$id_user]);

        $req = self::$_sql->prepare("UPDATE storage SET user_quota = user_quota-? WHERE id_user = ?");
        $req->execute([$storage_size, $id_user]);
    }

    function hasSubscriptionActive($id_user) {
        $req = self::$_sql->prepare("SELECT id FROM upgrade WHERE id_user = ? AND removed = 0");
		$req->execute([$id_user]);
		return ($req->rowCount() > 0);
    }

    function getActiveSubscription($id_user) {
        $req = self::$_sql->prepare("SELECT id FROM upgrade WHERE id_user = ? AND removed = 0");
        $req->execute([$id_user]);
        if($req->rowCount() === 0) return null;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['id'];
    }

    function renewSubscription($size, $price, $currency, $duration, $txn_id, $id_user) {
        $req = self::$_sql->prepare("SELECT `end`, size FROM upgrade WHERE id_user = ? AND removed = 0");
        $req->execute([$id_user]);
        $size = intval($size);
        $now = time();
        $old_end = $now;
        $old_size = 0;
        if($req->rowCount() > 0) {
            $res = $req->fetch(\PDO::FETCH_ASSOC);
            $old_size = intval($res['size']);
            if($res['end'] !== -1) {
                $old_end = $res['end'];
            }
        }

        $new_end = -1;
        if($duration !== -1) {
            // If the size is the same, keep time to subscription end, otherwise the old subscription is ended and user_quota updated
            if($old_size === $size) {
                $new_end = strtotime("+".$duration." months", $old_end);
            } else {
                $new_end = strtotime("+".$duration." months", $now);
                $req = self::$_sql->prepare("UPDATE storage SET user_quota = user_quota+? WHERE id_user = ?");
    			$req->execute([($size - $old_size), $id_user]);
            }
        }

        $req = self::$_sql->prepare("UPDATE upgrade SET `end` = ?, removed = 1 WHERE id_user = ? AND removed = 0");
        $req->execute([$now, $id_user]);

        $insert = $this->insert('upgrade', [
			'id' => null,
			'id_user' => $id_user,
			'txn_id' => $txn_id,
			'size' => $size,
			'price' => $price,
			'currency' => $currency,
			'start' => $now,
			'end' => $new_end,
			'removed' => 0
		]);
    }

	function addUpgrade($size, $price, $currency, $duration, $txn_id, $user_id = null) {
		//$duration in months, -1 = lifetime
		$user_id = $user_id === null ? $this->id_user : $user_id;
		if($user_id === null) return false;

		$end = ($duration === -1) ? -1 : strtotime("+".$duration." months", time());
		$insert = $this->insert('upgrade', [
			'id' => null,
			'id_user' => $user_id,
			'txn_id' => $txn_id,
			'size' => $size,
			'price' => $price,
			'currency' => $currency,
			'start' => time(),
			'end' => $end,
			'removed' => 0
		]);

		if($insert) {
			$req = self::$_sql->prepare("UPDATE storage SET user_quota = user_quota+? WHERE id_user = ?");
			$req->execute([$size, $user_id]);
		}
	}
}
