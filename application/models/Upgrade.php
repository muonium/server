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
        $req = self::$_sql->prepare("SELECT * FROM upgrade WHERE id_user = ? AND removed = 0");
        $req->execute([$user_id]);
        if($req->rowCount() > 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return floor(($res['end'] - time())/(7 * 24 * 60 * 60));
    }
    
    function expiresSoon($id_user) {
        $req = self::$_sql->prepare("SELECT * FROM upgrade WHERE id_user = ? AND removed = 0");
        $req->execute([$user_id]);
        if($req->rowCount() > 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        if($res['end'] < (time() - 7 * 24 * 60 * 60)) return false;
        return true;
    }
    
    function hasExpired($id_user) {
        $req = self::$_sql->prepare("SELECT * FROM upgrade WHERE id_user = ? AND removed = 0");
        $req->execute([$user_id]);
        if($req->rowCount() > 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        if($res['end'] < time()) return false;
        $this->expires($res['id'], $id_user, $res['size']);
        return true;
    }

    function expires($id_plan, $id_user, $storage_size) {
        $req = self::$_sql->prepare("UPDATE upgrade SET removed = 1 WHERE id = ? AND removed = 0");
        $req->execute([$id_plan]);
        
        $req = self::$_sql->prepare("UPDATE storage SET user_quota = user_quota-? WHERE id_user = ?");
        $req->execute([$storage_size, $user_id]);
    }
    
    function cancelSubscription($id_user) {
        $req = self::$_sql->prepare("SELECT * FROM upgrade WHERE id_user = ? AND removed = 0");
        $req->execute([$id_user]);
        if($req->rowCount() == 0) $storage_size = 0;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        $storage_size = $res['size'];
        
        $req = self::$_sql->prepare("UPDATE upgrade SET removed = 1 WHERE id_user = ? AND removed = 0");
        $req->execute([$id_user]);
        
        $req = self::$_sql->prepare("UPDATE storage SET user_quota = user_quota-? WHERE id_user = ?");
        $req->execute([$storage_size, $user_id]);
    }
    
    function hasSubscriptionActive($id_user) {
        $req = self::$_sql->prepare("SELECT id FROM upgrade WHERE id_user = ? AND removed = 0");
		$req->execute([$id_user]);
		if($req->rowCount() > 0) return true;
		return false;
    }
    
    function getActiveSubscription($id_user) {
        $req = self::$_sql->prepare("SELECT * FROM upgrade WHERE id_user = ? AND removed = 0");
        $req->execute([$user_id]);
        if($req->rowCount() > 0) return null;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['id_storage_plan'];
    }
    
    function renewSubscription($size, $price, $currency, $duration, $txn_id) {
        $req = self::$_sql->prepare("SELECT * FROM upgrade WHERE id_user = ? AND removed = 0");
        $req->execute([$id_user]);
        if($req->rowCount() == 0) $old_end = time();
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        $old_end = $res['end'];
        
        $req = self::$_sql->prepare("UPDATE upgrade SET end = ? WHERE id_user = ? AND removed = 0");
        $req->execute([$end, $user_id]);
        
        $req = self::$_sql->prepare("UPDATE upgrade SET removed = 1 WHERE id_user = ? AND removed = 0");
        $req->execute([$user_id]);
        
        $end = time() + ($old_end - time()); //get days left before renew
        
        $insert = $this->insert('upgrade', [
			'id' => null,
			'id_user' => $this->_uid,
			'txn_id' => $txn_id,
			'size' => $size,
			'price' => $price,
			'currency' => $currency,
			'start' => time(),
			'end' => $end,
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
