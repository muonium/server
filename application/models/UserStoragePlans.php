<?php
namespace application\models;
use \library\MVC as l;

class UserStoragePlans extends l\Model {
    /* upgrade table
        1	id              int(11)			AUTO_INCREMENT
        2	id_user         int(11)
        3	id_storage_plan	int(11)	
    */

    protected $userStoragePlans = null;
	protected $id_user = null;

	function __construct($id_user = null) {
		parent::__construct();
        if(is_numeric($id_user)) {
			$this->id_user = $id_user;
			$req = self::$_sql->prepare("SELECT * FROM user_storage_plans WHERE id_user = ?");
			$req->execute([$this->id_user]);
			$this->userStoragePlans = $req->fetchAll(\PDO::FETCH_ASSOC);
		}
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
        
        $req = self::$_sql->prepare("DELETE FROM user_storage_plans WHERE id_user = ?");
        $req->execute([$user_id]);
    }
    
    function hasSubscriptionActive($id_user) {
        $req = self::$_sql->prepare("SELECT id FROM user_storage_plans WHERE id_user = ?");
		$req->execute([$id_user]);
		if($req->rowCount() > 0) return true;
		return false;
    }
    
    function getActiveSubscription($id_user) {
        $req = self::$_sql->prepare("SELECT * FROM user_storage_plans WHERE id_user = ?");
        $req->execute([$user_id]);
        if($req->rowCount() > 0) return null;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['id_storage_plan'];
    }
}
