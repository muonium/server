<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \application\models as m;

class lostpass extends l\Controller {
    private $uid;
    private $val_key;

    private $_modelUser;
    private $_modelUserLostPass;
    private $_mail;
    private $redis = null;

    function __construct() {
        parent::__construct();
		$this->redis = $this->getRedis();
    }

    public function keyAction($uid = null, $key = null) {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();

		if($method !== 'post' && $method !== 'get') {
			$resp['code'] = 405; // Method Not Allowed
		}
		elseif($this->isLogged() === false) {
			if($method === 'get' && $uid !== null && $key !== null) {
				$data->uid = $uid;
				$data->key = $key;
			}

			if(isset($data->uid) && isset($data->key) && is_pos_digit($data->uid) && strlen($data->key) >= 128) {
        		$this->uid = intval($data->uid);
        		$this->val_key = $data->key;
        		$this->_modelUserLostPass = new m\UserLostPass($this->uid);

		        if($key = $this->_modelUserLostPass->getKey()) { // Found key
			        if($key === $this->val_key && $this->_modelUserLostPass->getExpire() >= time()) {
						// Same keys
						$resp['code'] = 200;
						$resp['status'] = 'success';
						$resp['message'] = 'ok';
			        } else { // Different key, send a new mail ?
			            $resp['message'] = 'differentKey';
			        }
				}
			} else {
				$resp['message'] = 'emptyField';
			}
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
    }

    public function mailAction() {
    	// Send lost pass mail with validation key
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
		elseif($this->isLogged() === false) {
        	sleep(2);
			if(isset($data->uid) && is_pos_digit($data->uid)) {
            	$this->uid = intval($data->uid);
                $this->_modelUser = new m\Users($this->uid);
				if($user_mail = $this->_modelUser->getEmail()) {
					$resp['code'] = 200;
					$resp['status'] = 'success';

					$key = hash('sha512', uniqid(rand(), true));

                	$this->_modelUserLostPass = new m\UserLostPass($this->uid);
                	$new = $this->_modelUserLostPass->getKey() ? false : true;
                	$this->_modelUserLostPass->val_key = $key;
                	$this->_modelUserLostPass->expire = time()+3600;

                	if($new) {
						$this->_modelUserLostPass->Insertion();
                	} else {
						$this->_modelUserLostPass->Update();
					}

                	$this->_mail = new l\Mail();
					$this->_mail->delay(60, $this->uid, $this->redis, 'lostpass');
                	$this->_mail->_to = $user_mail;
                	$this->_mail->_subject = self::$txt->LostPass->subject;
	                $this->_mail->_message = str_replace(
	                    ["[id_user]", "[key]", "[url_app]"],
	                    [$this->uid, $key, URL_APP],
	                    self::$txt->LostPass->message
	                );

					$resp['message'] = $this->_mail->send(); // 'sent' or 'wait'
				}
            } else {
				$resp['message'] = 'emptyField';
			}
        }

		http_response_code($resp['code']);
		echo json_encode($resp);
    }

	public function DefaultAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
		elseif($this->isLogged() === false) {
			if(isset($data->uid) && isset($data->key) && is_pos_digit($data->uid) && strlen($data->key) >= 128 && isset($data->password)) {
				$this->_modelUserLostPass = new m\UserLostPass($data->uid);
				if($key = $this->_modelUserLostPass->getKey()) {
					if($key === $data->key && $this->_modelUserLostPass->getExpire() >= time()) {
						$this->_modelUser = new m\Users($data->uid);
						$this->_modelUser->password = password_hash(urldecode($data->password), PASSWORD_BCRYPT);
						if($this->_modelUser->updatePassword()) {
							$this->_modelUserLostPass->Delete();
							$this->redis->del('uid:'.$this->uid.':mailnbf:lostpass');
							$resp['code'] = 200;
							$resp['status'] = 'success';
							$resp['message'] = 'updated';
						}
					} else {
			            $resp['message'] = 'differentKey';
			        }
				}
			} else {
				$resp['message'] = 'emptyField';
			}
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
	}
}
