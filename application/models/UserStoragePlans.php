<?php
namespace application\models;
use \library\MVC as l;

class Upgrade extends l\Model {
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
