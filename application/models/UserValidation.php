<?php
namespace application\models;
use \library\MVC as l;

class UserValidation extends l\Model {
    /*
    CREATE TABLE IF NOT EXISTS user_validation (
    	user_id uuid,
    	val_key text,
    	PRIMARY KEY (user_id)
    );
    */

    protected $id = null;
    protected $user_id = null;
    protected $val_key;

	function __construct($user_id = null) {
		parent::__construct();
		// user_id (uuid) can be passed at init
		$this->user_id = $user_id;
	}

    function getKey() {
		if($this->user_id === null) return false;
        $req = self::$_sql->prepare("SELECT val_key FROM user_validation WHERE user_id = ?");
        $req->execute([$this->user_id]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['val_key'];
    }

    function Delete() {
		if($this->user_id === null) return false;
        $req = self::$_sql->prepare("DELETE FROM user_validation WHERE user_id = ?");
        return $req->execute([$this->user_id]);
    }

    function Insertion() {
		if($this->user_id === null || !isset($this->val_key)) return false;
		return $this->insert('user_validation', [
			'id' => null,
			'user_id' => $this->user_id,
			'val_key' => $this->val_key
		]);
    }

    function Update() {
		if($this->user_id === null || !isset($this->val_key)) return false;
        $req = self::$_sql->prepare("UPDATE user_validation SET val_key = ? WHERE user_id = ?");
        return $req->execute([$this->val_key, $this->user_id]);
    }
}
