<?php
namespace application\models;
use \library\MVC as l;

class Upgrade extends l\Model {
    /*
    CREATE TABLE IF NOT EXISTS upgrade (
    	user_id uuid,
    	txn_id text,
    	size bigint,
    	price float,
    	currency text,
    	start timestamp,
    	end timestamp,
    	removed boolean,
    	PRIMARY KEY (user_id)
    );
    */

    protected $upgrades = null;
	protected $user_id = null;

	function __construct($user_id = null) {
		parent::__construct();
		if(is_numeric($user_id)) {
			$this->user_id = $user_id;
			$req = self::$_sql->prepare("SELECT * FROM upgrade WHERE user_id = ? ORDER BY `end` DESC");
			$req->execute([$this->user_id]);
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

    function getDaysLeft($user_id) {
        $req = self::$_sql->prepare("SELECT `end` FROM upgrade WHERE user_id = ? AND removed = 0");
        $req->execute([$user_id]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return floor(($res['end'] - time())/(24 * 60 * 60));
    }

    function expiresSoon($user_id) {
        $req = self::$_sql->prepare("SELECT `end` FROM upgrade WHERE user_id = ? AND removed = 0");
        $req->execute([$user_id]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        if($res['end'] > (time() + (7 * 24 * 60 * 60))) return false;
        return true;
    }

    function hasExpired($user_id) {
        $req = self::$_sql->prepare("SELECT * FROM upgrade WHERE user_id = ? AND removed = 0");
        $req->execute([$user_id]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        if($res['end'] > time()) return false;
        $this->expires($res['id'], $user_id, $res['size']);
        return true;
    }

    function expires($id_plan, $user_id, $storage_size) {
        $req = self::$_sql->prepare("UPDATE upgrade SET removed = 1 WHERE id = ? AND user_id = ? AND removed = 0");
        $req->execute([$id_plan, $user_id]);

        $req = self::$_sql->prepare("UPDATE storage SET user_quota = user_quota-? WHERE user_id = ?");
        $req->execute([$storage_size, $user_id]);
    }

    function cancelSubscription($user_id) {
        $req = self::$_sql->prepare("SELECT * FROM upgrade WHERE user_id = ? AND removed = 0");
        $req->execute([$user_id]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        $storage_size = $res['size'];

        $req = self::$_sql->prepare("UPDATE upgrade SET removed = 1 WHERE user_id = ? AND removed = 0");
        $req->execute([$user_id]);

        $req = self::$_sql->prepare("UPDATE storage SET user_quota = user_quota-? WHERE user_id = ?");
        $req->execute([$storage_size, $user_id]);
    }

    function hasSubscriptionActive($user_id) {
        $req = self::$_sql->prepare("SELECT id FROM upgrade WHERE user_id = ? AND removed = 0");
		$req->execute([$user_id]);
		return ($req->rowCount() > 0);
    }

    function getActiveSubscription($user_id) {
        $req = self::$_sql->prepare("SELECT id FROM upgrade WHERE user_id = ? AND removed = 0");
        $req->execute([$user_id]);
        if($req->rowCount() === 0) return null;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['id'];
    }

    function renewSubscription($size, $price, $currency, $duration, $txn_id, $user_id) {
        $req = self::$_sql->prepare("SELECT `end`, size FROM upgrade WHERE user_id = ? AND removed = 0");
        $req->execute([$user_id]);
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
                $req = self::$_sql->prepare("UPDATE storage SET user_quota = user_quota+? WHERE user_id = ?");
    			$req->execute([($size - $old_size), $user_id]);
            }
        }

        $req = self::$_sql->prepare("UPDATE upgrade SET `end` = ?, removed = 1 WHERE user_id = ? AND removed = 0");
        $req->execute([$now, $user_id]);

        $insert = $this->insert('upgrade', [
			'id' => null,
			'user_id' => $user_id,
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
		$user_id = $user_id === null ? $this->user_id : $user_id;
		if($user_id === null) return false;

		$end = ($duration === -1) ? -1 : strtotime("+".$duration." months", time());
		$insert = $this->insert('upgrade', [
			'id' => null,
			'user_id' => $user_id,
			'txn_id' => $txn_id,
			'size' => $size,
			'price' => $price,
			'currency' => $currency,
			'start' => time(),
			'end' => $end,
			'removed' => 0
		]);

		if($insert) {
			$req = self::$_sql->prepare("UPDATE storage SET user_quota = user_quota+? WHERE user_id = ?");
			$req->execute([$size, $user_id]);
		}
	}
}
