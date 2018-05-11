<?php
namespace application\models;
use \library\MVC as l;

class StoragePlans extends l\Model {
    /*
    CREATE TABLE IF NOT EXISTS storage_plans (
    	product_id text,
    	size bigint,
    	price float,
    	currency text,
    	duration int,
    	most_popular boolean,
    	PRIMARY KEY (product_id)
    );
    */

    protected $plans;

	function __construct() {
		parent::__construct();
		$req = self::$_sql->prepare("SELECT * FROM storage_plans ORDER BY size ASC");
		$req->execute();
		$this->plans = $req->fetchAll(\PDO::FETCH_ASSOC);
	}

	function getPlans() {
		return $this->plans;
	}
}
