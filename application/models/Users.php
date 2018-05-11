<?php
namespace application\models;
use \library\MVC as l;

class Users extends l\Model {
    /*
        CREATE TABLE IF NOT EXISTS users (
    	user_id uuid,
    	login text,
    	password text,
    	email text,
    	lang text,
    	registration_date timestamp,
    	last_connection timestamp,
    	cek text,
    	pks tuple<text, text>,
    	double_auth boolean,
    	auth_code text,
    	PRIMARY KEY (user_id)
    );
    */

    protected $user_id = null;
    protected $login;
    protected $password;
    protected $email;
	protected $cek;
    protected $doubleAuth = 0;
    protected $code;

	function __construct($user_id = null) {
		parent::__construct();
		// user_id (uuid) can be passed at init
		$this->user_id = $user_id;
	}

    function setDoubleAuth($state) {
        if($state == 0 || $state == 1) $this->doubleAuth = $state;
    }

    function setCode($code) {
        if(strlen($code) === 8) $this->code = $code;
    }

    function getId() {
		// Returns user id with its login or email
        if(isset($this->login)) {
            $req = self::$_sql->prepare("SELECT id FROM users WHERE login = ?");
            $req->execute([$this->login]);
        }
        elseif(isset($this->email)) {
            $req = self::$_sql->prepare("SELECT id FROM users WHERE email = ?");
            $req->execute([$this->email]);
        }
        else {
            return false;
		}
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['id'];
    }

    function getEmail($id = null) {
        // Returns user email with its id, login or email if exists
		$id = $id === null ? $this->user_id : $id;
		if(is_numeric($id)) {
			$req = self::$_sql->prepare("SELECT email FROM users WHERE id = ?");
            $req->execute([$id]);
		}
        elseif(isset($this->login)) {
            $req = self::$_sql->prepare("SELECT email FROM users WHERE login = ?");
            $req->execute([$this->login]);
        }
        elseif(isset($this->email)) {
            $req = self::$_sql->prepare("SELECT email FROM users WHERE email = ?");
            $req->execute([$this->email]);
        }
        else {
            return false;
		}
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['email'];
    }

    function getPassword() {
		// Returns hashed password
		if($this->user_id === null) return false;
        $req = self::$_sql->prepare("SELECT password FROM users WHERE id = ?");
        $req->execute([$this->user_id]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['password'];
    }

    function getCek() {
		if($this->user_id === null) return false;
        $req = self::$_sql->prepare("SELECT cek FROM users WHERE id = ?");
        $req->execute([$this->user_id]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['cek'];
    }

    function getLogin($id = null) {
		$id = $id === null ? $this->user_id : $id;
		if(!is_numeric($id)) return false;
		$req = self::$_sql->prepare("SELECT login FROM users WHERE id = ?");
        $req->execute([$id]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['login'];
    }

    function getDoubleAuth() {
		if($this->user_id === null) return false;
        $req = self::$_sql->prepare("SELECT double_auth FROM users WHERE id = ? AND double_auth = '1'");
        $req->execute([$this->user_id]);
        if($req->rowCount() === 0) return false;
        return true;
    }

    function getCode() {
		if($this->user_id === null) return false;
        $req = self::$_sql->prepare("SELECT auth_code FROM users WHERE id = ?");
        $req->execute([$this->user_id]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['auth_code'];
    }

	function getInfos() {
		if($this->user_id === null) return false;
		$req = self::$_sql->prepare("SELECT id, login, email, registration_date, double_auth FROM users WHERE id = ?");
		$req->execute([$this->user_id]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res;
	}

    function EmailExists($email = null) {
		$email = $email === null ? $this->email : $email;
		if($email === null) return false;
        $req = self::$_sql->prepare("SELECT id FROM users WHERE email = ?");
        $req->execute([$email]);
        if($req->rowCount()) return true;
        return false;
    }

    function LoginExists($login = null) {
		$login = $login === null ? $this->login : $login;
		if($login === null) return false;
        $req = self::$_sql->prepare("SELECT id FROM users WHERE login = ?");
        $req->execute([$login]);
        if($req->rowCount()) return true;
        return false;
    }

    function Insertion() {
        // Password must be hashed before
		if(!isset($this->login) || !isset($this->password) || !isset($this->email) || !isset($this->cek) || !isset($this->doubleAuth)) return false;
		return $this->insert('users', [
			'id' => null,
			'login' => $this->login,
			'password' => $this->password,
			'email' => $this->email,
			'registration_date' => time(),
			'last_connection' => time(),
			'cek' => $this->cek,
			'double_auth' => $this->doubleAuth,
			'auth_code' => ''
		]);
    }

	function Connection() {
		if(!isset($this->email) || !isset($this->password)) return false;
		$req = self::$_sql->prepare("SELECT id FROM users WHERE email = ? AND password = ?");
        $req->execute([$this->email, $this->password]);
        if($req->rowCount()) return true;
        return false;
	}

    function updateLogin() {
		if($this->user_id === null) return false;
        $req = self::$_sql->prepare("UPDATE users SET login = ? WHERE id = ?");
        return $req->execute([$this->login, $this->user_id]);
    }

    function updatePassword() {
		// Password must be hashed before
		if($this->user_id === null) return false;
        $req = self::$_sql->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $req->execute([$this->password, $this->user_id]);
    }

    function updateCek() {
		if($this->user_id === null) return false;
        $req = self::$_sql->prepare("UPDATE users SET cek = ? WHERE id = ?");
        return $req->execute([$this->cek, $this->user_id]);
    }

    function updateDoubleAuth($state) {
		if($this->user_id === null || ($state != 0 && $state != 1)) return false;
        $req = self::$_sql->prepare("UPDATE users SET double_auth = ? WHERE id = ?");
        return $req->execute([$state, $this->user_id]);
    }

    function updateCode($code) {
		if($this->user_id === null || strlen($code) !== 8) return false;
        $req = self::$_sql->prepare("UPDATE users SET auth_code = ? WHERE id = ?");
        return $req->execute([$code, $this->user_id]);
    }

	function updatemail() {
	    if($this->user_id === null) return false;
	    $req = self::$_sql->prepare("UPDATE users SET email = ? WHERE id = ?");
	    return $req->execute([$this->email, $this->user_id]);
	}

	function updateLastConnection() {
		if($this->user_id === null) return false;
	    $req = self::$_sql->prepare("UPDATE users SET last_connection = ? WHERE id = ?");
	    return $req->execute([time(), $this->user_id]);
	}

    function updateLanguage($lang) {
		if($this->user_id === null) return false;
	    $req = self::$_sql->prepare("UPDATE users SET lang = ? WHERE id = ?");
	    return $req->execute([$lang, $this->user_id]);
	}

	function deleteUser() {
		if($this->user_id === null) return false;
        $req = self::$_sql->prepare("DELETE FROM users WHERE id = ?");
        return $req->execute([$this->user_id]);
    }
}
