<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \application\models as m;

class validate extends l\Controller {
    private $uid;
    private $val_key;

    private $_modelUser;
    private $_modelUserVal;
    private $_mail;
	private $redis;

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
	            $this->_modelUserVal = new m\UserValidation($this->uid);

	            if($this->_modelUserVal->getKey()) { // Found key
		            if($this->_modelUserVal->getKey() === $this->val_key) { // Same keys, validate account
						$this->_modelUserVal->Delete();
						$this->redis->del('uid:'.$this->uid.':mailnbf:validate');
						$resp['code'] = 200;
						$resp['status'] = 'success';
						$resp['message'] = 'validated';
					} else { // Different key, send a new mail ?
		                $resp['message'] = 'differentKey';
		            }
				} else {
					$resp['message'] = 'alreadyValidated';
				}
			} else {
				$resp['message'] = 'emptyField';
			}
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
    }

    public function mailAction() {
        // Send registration mail with validation key
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
                $this->_modelUserVal = new m\UserValidation($this->uid);
                if($this->_modelUserVal->getKey()) {
					$this->_modelUser = new m\Users($this->uid);
                    if($user_mail = $this->_modelUser->getEmail()) {
                        self::loadLanguage();
						$resp['code'] = 200;
						$resp['status'] = 'success';

						$key = hash('sha512', uniqid(rand(), true));

	                    $this->_modelUserVal->val_key = $key;
	                    $this->_modelUserVal->Update();

	                    $this->_mail = new l\Mail();
						$this->_mail->delay(60, $this->uid, $this->redis, 'validate');
	                    $this->_mail->_to = $user_mail;
	                    $this->_mail->_subject = self::$txt->Validate->subject;
	                    $this->_mail->_message = str_replace(
	                        array("[id_user]", "[key]", "[url_app]"),
	                        array($this->uid, $key, URL_APP),
	                        self::$txt->Validate->message
	                    );

	                    $resp['message'] = $this->_mail->send(); // 'sent' or 'wait'
					}
				} else {
					$resp['message'] = 'alreadyValidated';
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
		$resp['token'] = $this->_token;

		http_response_code($resp['code']);
		echo json_encode($resp);
	}
}
