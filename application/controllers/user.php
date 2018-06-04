<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \Muonium\GoogleAuthenticator as ga;
use \application\models as m;

class user extends l\Controller {
	private $_modelUser;
	private $_modelBan;
	private $_modelFiles;
	private $_modelStorage;
	private $_modelFolders;
	private $_modelUserLostPass;
	private $_modelUserValidation;

	function __construct() {
		// 'mustBeLogged' is not set because in one case we don't need a logged user (for creating a new account), we will check with isLogged() instead (it set _uid and _token too)
        parent::__construct();
    }

	public function changeLoginAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
		elseif($this->isLogged() === true && isset($data->login)) {
			$resp['token'] = $this->_token;
			$login = urldecode($data->login);
			if(preg_match("/^[A-Za-z0-9_.-]{2,19}$/", $login)) {
				$this->_modelUser = new m\Users($this->_uid);
				$this->_modelUser->login = $login;
				if(!($this->_modelUser->LoginExists())) {
					if($this->_modelUser->updateLogin()) {
						$resp['code'] = 200;
						$resp['status'] = 'success';
					}
				} else {
					$resp['message'] = 'loginExists';
				}
			} else {
				$resp['message'] = 'loginFormat';
			}
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
	}

	public function changeMailAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
		elseif($this->isLogged() === true && isset($data->mail)) {
			$resp['token'] = $this->_token;
			$mail = urldecode($data->mail);
			if(strlen($mail) > 2 && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
				$this->_modelUser = new m\Users($this->_uid);
				$this->_modelUser->email = $mail;
				if(!($this->_modelUser->EmailExists())) {
					if($this->_modelUser->updateMail()) {
						$resp['code'] = 200;
						$resp['status'] = 'success';
					}
				} else {
					$resp['message'] = 'mailExists';
				}
			} else {
				$resp['message'] = 'mailFormat';
			}
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
	}

	public function changePasswordAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
		elseif($this->isLogged() === true && isset($data->old_pwd) && isset($data->new_pwd)) {
			$resp['token'] = $this->_token;
            $this->_modelUser = new m\Users($this->_uid);
            if($user_pwd = $this->_modelUser->getPassword()) {
				$old_pwd = urldecode($data->old_pwd);
                if(password_verify($old_pwd, $user_pwd)) {
                    $this->_modelUser->password = password_hash(urldecode($data->new_pwd), PASSWORD_BCRYPT);
                    if($this->_modelUser->updatePassword()) {
						$this->removeTokens($this->_uid);
						$resp['code'] = 200;
						$resp['status'] = 'success';
						$resp['message'] = 'removeToken';
						$resp['token'] = null;
                    }
                } else {
                    $resp['message'] = 'badOldPass';
                }
            }
        }

		http_response_code($resp['code']);
		echo json_encode($resp);
    }

	public function changeCekAction() {
		/*
			- receive the new base64encoded encrypted CEK
			- store it in the database
			- DO NOT FORGET: THE PASSPHRASE MUST NOT BE SENT TO THE SERVER!
			- keep the cek as an urlencoded string, it's urldecoded at the frontend anyway
		*/
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
		elseif($this->isLogged() === true && isset($data->cek)) {
			$resp['token'] = $this->_token;
			$this->_modelUser = new m\Users($this->_uid);
			$this->_modelUser->cek = $data->cek;
			if($this->_modelUser->updateCek()) { // try to update
                $this->removeTokens($this->_uid);
				$resp['code'] = 200;
				$resp['status'] = 'success';
                $resp['message'] = 'removeToken';
                $resp['token'] = null;
			}
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
	}

	public function changeAuthAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
		elseif($this->isLogged() === true) {
			$resp['token'] = $this->_token;
	        $this->_modelUser = new m\Users($this->_uid);
	        $s = 0;
            $isValid = true;
            
	        if(isset($data->doubleAuth) && ($data->doubleAuth === 1 || $data->doubleAuth === 2)) {
				$s = $data->doubleAuth;
                if($this->_modelUser->isDoubleAuthGA() || $data->doubleAuth === 2) {
                    $googleAuth = new ga\GoogleAuthenticator();
                    $secret = $this->_modelUser->getSecretKeyGA();
                    
                    if(!($googleAuth->checkCode($secret, $data->code))) {
                        $isValid = false;
                    }
                    
                    if($this->_modelUser->isDoubleAuthGA()) {
                        $this->_modelUser->deleteBackupCodes();
                        $this->_modelUser->deleteSecretKey();
                    }
                }
			}
            
            if(!($isValid)) {
                $resp['code'] = 403;
				$resp['status'] = 'badCode';
            } else {
                if($this->_modelUser->updateDoubleAuth($s)) {
				    $resp['code'] = 200;
				    $resp['status'] = 'success';
	           }
            }
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
    }

    public function changeLangAction() {
        header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();

        if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
		elseif($this->isLogged() === true) {
			$resp['token'] = $this->_token;
	        $this->_modelUser = new m\Users($this->_uid);
	        if($this->_modelUser->updateLanguage(self::$userLanguage)) {
				$resp['code'] = 200;
				$resp['status'] = 'success';
	        }
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
    }

	private function delete($resp) {
		function removeDirectory($path) {
			$files = glob($path . '/*');
			foreach ($files as $file) {
				is_dir($file) ? removeDirectory($file) : unlink($file);
			}
			rmdir($path);
			return;
		}

		if($this->isLogged() === false || !is_numeric($this->_uid)) {
			return $resp;
		}

		$this->_modelUser = new m\Users($this->_uid);
		$this->_modelStorage = new m\Storage($this->_uid);
		$this->_modelFiles = new m\Files($this->_uid);
		$this->_modelFolders = new m\Folders($this->_uid);
		$this->_modelBan = new m\Ban($this->_uid);
		$this->_modelUserValidation = new m\UserValidation($this->_uid);
		$this->_modelUserLostPass = new m\UserLostPass($this->_uid);

		if($this->_modelUserLostPass->Delete()) {
			if($this->_modelUserValidation->Delete()) {
				if($this->_modelBan->deleteBan()) {
					if($this->_modelFiles->deleteFilesfinal()) {
						if($this->_modelFolders->deleteFoldersfinal()) {
							if($this->_modelStorage->deleteStorage()) {
								if($this->_modelUser->deleteUser()) {
									removeDirectory(NOVA.'/'.$this->_uid);
									$this->removeTokens($this->_uid);
									$resp['code'] = 200;
									$resp['status'] = 'success';
									$resp['message'] = 'removeToken';
									$resp['token'] = null;
									return $resp;
								}
							}
						}
					}
				}
			}
		}
		return $resp;
	}

	private function get($resp) {
		if($this->isLogged() === false) {
			return $resp;
		}
		$resp['token'] = $this->_token;
		$this->_modelUser = new m\Users($this->_uid);
		$infos = $this->_modelUser->getInfos();
		if($infos !== false) {
			$infos['double_auth'] = ($infos['double_auth'] == 1 || $infos['double_auth'] == 2) ? $infos['double_auth'] : 0;
			$resp['code'] = 200;
			$resp['status'] = 'success';
			$resp['data'] = $infos;
		}
		return $resp;
	}

	private function register($resp) {
		if($this->isLogged() === true || is_numeric($this->_uid)) {
			return $resp;
		}
		$data = h\httpMethodsData::getValues();
		if(isset($data->mail) && isset($data->login) && isset($data->password) && isset($data->cek)) {
			sleep(2);
			if(filter_var($data->mail, FILTER_VALIDATE_EMAIL)) {
				if(preg_match("/^[A-Za-z0-9_.-]{2,19}$/", $data->login)) {
					$this->_modelUser = new m\Users();
					$this->_modelUser->email = htmlspecialchars($data->mail);
					$this->_modelUser->password = password_hash(urldecode($data->password), PASSWORD_BCRYPT);
					$this->_modelUser->login = htmlspecialchars($data->login);
					$this->_modelUser->cek = htmlspecialchars($data->cek);

					if(!($this->_modelUser->EmailExists())) {
						if(!($this->_modelUser->LoginExists())) {
							if(isset($data->doubleAuth) && ($data->doubleAuth === 1 || $data->doubleAuth === 2)) {
								$this->_modelUser->setDoubleAuth($data->doubleAuth);
							}

							if($this->_modelUser->Insertion()) {
								// Send registration mail with validation key
								$uid = $this->_modelUser->getLastInsertedId();
								$key = hash('sha512', uniqid(rand(), true));

								$this->_modelStorage = new m\Storage($uid);
								$this->_modelStorage->Insertion();

								$this->_modelUserVal = new m\UserValidation($uid);
								$this->_modelUserVal->val_key = $key;
								$this->_modelUserVal->Insertion();

								$this->_mail = new l\Mail();
								$this->_mail->_to = htmlspecialchars($data->mail);
								$this->_mail->_subject = self::$txt->Validate->subject;
								$this->_mail->_message = str_replace(
									["[id_user]", "[key]", "[url_app]"],
									[$uid, $key, URL_APP],
									self::$txt->Validate->message
								);
								$this->_mail->send();

								// Create user folder
								if(mkdir(NOVA.'/'.$uid, 0770)) {
									$resp['code'] = 201;
									$resp['status'] = 'success';
									$resp['message'] = 'created';
                                    $resp['data'] = $uid;
								}
							}
						} else {
							$resp['message'] = 'loginExists';
						}
					} else {
						$resp['message'] = 'mailExists';
					}
				} else {
					$resp['message'] = 'loginFormat';
				}
			} else {
				$resp['message'] = 'mailFormat';
			}
		} else {
			$resp['message'] = 'emptyField';
		}
		return $resp;
	}

	public function DefaultAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();

		switch($method) {
			case 'delete':
				$resp = $this->delete($resp);
				break;
			case 'get':
				$resp = $this->get($resp);
				break;
			case 'post':
				$resp = $this->register($resp);
				break;
			default:
				$resp['code'] = 405; // Method Not Allowed
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
    }
}
