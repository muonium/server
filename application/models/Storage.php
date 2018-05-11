<?php
namespace application\models;
use \library\MVC as l;

class Storage extends l\Model {
    /*
    CREATE TABLE IF NOT EXISTS storage (
    	user_id uuid,
    	user_quota bigint,
    	size_stored bigint,
    	PRIMARY KEY (user_id)
    );
    */

    protected $id = null;
    protected $user_id = null;
    protected $user_quota;
    protected $size_stored;

	function __construct($user_id = null) {
		parent::__construct();
		// user_id (uuid) can be passed at init
		$this->user_id = $user_id;
	}

    function incrementSizeStored($i) {
		// i (int) - Increment the size stored of $i B
		// size_stored is incremented in the controller
		if($this->user_id === null || !is_numeric($i)) return false;
        $req = self::$_sql->prepare("UPDATE storage SET size_stored = size_stored+? WHERE user_id = ?");
        return $req->execute([$i, $this->user_id]);
    }

    function decrementSizeStored($i) {
		// i (int) - Decrement the size stored of $i B
        if(is_numeric($i)) return $this->incrementSizeStored(-1*$i);
        return false;
    }

    function updateSizeStored($i) {
		// i (int) - Set the size stored to $i B
		// size_stored is set in the controller
		if($this->user_id === null || !is_numeric($i) || $i < 0) return false;
        $req = self::$_sql->prepare("UPDATE storage SET size_stored = ? WHERE user_id = ?");
        return $req->execute([$i, $this->user_id]);
    }

    function Insertion() {
		// Create a record for a new user
		if($this->user_id === null) return false;
		return $this->insert('storage', [
			'id' => null,
			'user_id' => $this->user_id,
			'user_quota' => 2*1000*1000*1000,
			'size_stored' => 0
		]);
    }

    function getUserQuota() {
		// Returns user quota
		// size_stored is set in the controller
		if($this->user_id === null) return false;
        $req = self::$_sql->prepare("SELECT user_quota FROM storage WHERE user_id = ?");
        $req->execute([$this->user_id]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['user_quota'];
    }

    function getSizeStored() {
		// Returns size stored
		// size_stored is set in the controller
		if($this->user_id === null) return false;
        $req = self::$_sql->prepare("SELECT size_stored FROM storage WHERE user_id = ?");
        $req->execute([$this->user_id]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['size_stored'];
    }

	function deleteStorage() {
		if($this->user_id === null) return false;
        $req = self::$_sql->prepare("DELETE FROM storage WHERE user_id = ?");
        return $req->execute([$this->user_id]);
    }
}
