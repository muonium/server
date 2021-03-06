<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \application\models as m;
use \Muonium\GoogleAuthenticator as ga;
use \config as conf;

class session extends l\Controller {
    private $_message;
    private $_forbiddenChars = ["0", "1", "8", "9"];

	function __construct() {
        parent::__construct();
    }

    public function authcodeAction() {
        // User sent an auth code
        sleep(1);

		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
		elseif($this->isLogged() === false) {
			// User is not logged
			if(isset($data->uid) && isset($data->password) && isset($data->code)) {
				if(is_pos_digit($data->uid) && (strlen($data->code) === 6 || strlen($data->code) === 8 || strlen($data->code) === 10)) {
					$user = new m\Users($data->uid);
					$user->password = urldecode($data->password);
					$pass = $user->getPassword();
					$cek = $user->getCek();
                    $lang = $user->getLang();

					if($pass !== false && password_verify($user->password, $pass)) {
						// Password is ok
						$user->updateLastConnection();
						$mUserVal = new m\UserValidation($data->uid);
						if($mUserVal->getKey()) {
							// Key found - User needs to validate its account (double auth only for validated accounts)
							$resp['code'] = 401;
                            $resp['data'] = $data->uid;
							$resp['message'] = 'validate';
						}
						elseif($user->getDoubleAuth()) {
                            $isValid = false;
                            if($user->isDoubleAuthGA()) {
                                $googleAuth = new ga\GoogleAuthenticator();
                                $secret = $user->getSecretKeyGA();

                                if($googleAuth->checkCode($secret, $data->code)) {
                                    $isValid = true; // Double auth code is ok, send token
                                } else {
                                    if($user->isBackupCodeValid($data->code)) {
                                        $user->deleteBackupCode($data->code);
                                        $isValid = true;
                                    }
                                }
                            } else { //2FA with mail
                                $code = $user->getCode();
                                if($code && $code === $data->code) {
                                    $isValid = true; // Double auth code is ok, send token
                                }
                            }
                            if($isValid) {
                                $resp['code'] = 200;
                                $resp['status'] = 'success';
                                $resp['token'] = $this->buildToken($data->uid);
                                $resp['data']['cek'] = $cek;
                                $resp['data']['uid'] = intval($data->uid);
                                $resp['data']['lang'] = $lang;
                            } else {
                                // Wrong code
                                $resp['code'] = 401;
                                $resp['message'] = 'badCode';
                            }
						}
						else {
							// Double auth is disabled but password is still ok, then, send token
							$resp['code'] = 200;
							$resp['status'] = 'success';
							$resp['token'] = $this->buildToken($data->uid);
							$resp['data']['cek'] = $cek;
                            $resp['data']['uid'] = intval($data->uid);
                            $resp['data']['lang'] = $lang;
						}
					}
					else {
						// UID exists but incorrect password
						$resp['code'] = 401;
	                	$resp['message'] = 'badPass';
					}
				}
			} else {
	            $resp['message'] = 'emptyField';
	        }
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
    }

    public function jtiAction($jti) { // Delete session $jti for current user
        header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();

        if($method !== 'delete') {
			$resp['code'] = 405; // Method Not Allowed
        } elseif($this->isLogged() === true) {
            $jti = str_replace(['-', '_', ':'], ['+', '/', ''], $jti);
            $this->removeToken($jti, $this->_uid);
            $resp['code'] = 200;
            $resp['status'] = 'success';
            if($jti === $this->_token) {
                $resp['message'] = 'removeToken';
            } else {
                $resp['token'] = $this->_token;
            }
        } else {
            $resp['message'] = 'emptyField';
        }

        http_response_code($resp['code']);
		echo json_encode($resp);
    }

    public function allAction() { // Delete all sessions (except current) for current user
        header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();

        if($method !== 'delete') {
			$resp['code'] = 405; // Method Not Allowed
		} elseif($this->isLogged() === true) {
            $this->removeTokens($this->_uid, false);
            $resp['code'] = 200;
            $resp['status'] = 'success';
        } else {
            $resp['message'] = 'emptyField';
        }

        http_response_code($resp['code']);
		echo json_encode($resp);
    }

	private function login($resp) {
		sleep(2);
		$data = h\httpMethodsData::getValues();
		if($this->isLogged() === true || is_numeric($this->_uid)) {
			return $resp;
		}
		if(isset($data->username) && isset($data->password)) {
			$new_user = new m\Users();

			if(filter_var($data->username, FILTER_VALIDATE_EMAIL) === false) {
                $new_user->login = $data->username;
				$email = $new_user->getEmail();
            } else {
                $new_user->email = $data->username;
				$email = $data->username;
			}

			$new_user->password = urldecode($data->password);

			if(!($id = $new_user->getId())) {
                // User doesn't exists
				$resp['code'] = 401;
                $resp['message'] = 'badUser';
            }
			else {
                $new_user->id = $id;
                $pass = $new_user->getPassword();
				$cek = $new_user->getCek();
                $lang = $new_user->getLang();

                if($pass !== false) {
                    if(password_verify($new_user->password, $pass)) {
                        // Mail, password ok, connection
						$resp['code'] = 200;
						$resp['status'] = 'success';

						$new_user->updateLastConnection();
                        $mUserVal = new m\UserValidation($id);

                        if(!($mUserVal->getKey())) {
                            // Unable to find key - Validation is done
                            $resp['data'] = [];
                            if($new_user->getDoubleAuth()) {
                                if(!$new_user->isDoubleAuthGA()) {
                                    // Double auth : send an email with a code
                                    $code = $this->generateCode();
                                    $mail = new l\Mail();
                                    $mail->delay(60, $id, $this->getRedis());
                                    $mail->_to = $email;
                                    $mail->_subject = "Muonium - ".self::$txt->Profile->doubleAuth;
                                    $mail->_message = str_replace("[key]", $code, self::$txt->Login->doubleAuthMessage);
                                    if($mail->send() === 'wait') {
                                        $resp['message'] = 'wait';
                                    } else {
                                        $new_user->updateCode($code);
                                        $resp['message'] = 'doubleAuth';
                                        $resp['data']['doubleAuthMethod'] = 1;
                                    }
                                } else {
                                    $resp['message'] = 'doubleAuth';
                                    $resp['data']['doubleAuthMethod'] = 2;
                                }
                            }
                            else { // Logged
								$resp['token'] = $this->buildToken($id);
							}
                            $resp['data']['cek'] = $cek; // the CEK is already url encoded in the database
                            $resp['data']['uid'] = $id;
                            $resp['data']['lang'] = $lang;
                        }
                        else {
                            // Key found - User needs to validate its account (double auth only for validated accounts)
							$resp['code'] = 401;
                            $resp['data'] = $id;
                            $resp['message'] = 'validate';
                        }
                        return $resp;
                    }
                }

				// User exists but incorrect password
				$resp['code'] = 401;
                $resp['message'] = 'badPass';
			}
		}
		else {
            $resp['message'] = 'emptyField';
        }
		return $resp;
	}

	private function delete($resp) {
		// Delete the token and do not generate a new one
		$token = h\httpMethodsData::getToken();
		if($token === null) {
			return $resp;
		}
		$resp['code'] = 200;
		$resp['status'] = 'success';
		$resp['message'] = 'removeToken';
		$token = $this->verifyToken($token, false);
		// If $token === false, the token is not valid, expired or already removed
		if($token !== false) { // Token is still valid
			$decodedToken = $this->getDecodedToken();
			$this->removeToken($decodedToken['jti'], $decodedToken['data']['uid']);
		}
		return $resp;
	}

	private function get($resp) {
		if($this->isLogged() === false) {
			return $resp;
		}
		// Token is still valid
		$resp['code'] = 200;
		$resp['status'] = 'success';
		$resp['token'] = $this->_token; // Send it in the response because a new one could be generated when verifying
		$resp['data']['uid'] = $this->_uid;
        $resp['data']['tokens'] = $this->getTokens($this->_uid);
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
				$resp = $this->login($resp);
				break;
			default:
				$resp['code'] = 405; // Method Not Allowed
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
    }

    private function generateCode() {
        $code = '';
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        for($i = 0; $i < 8; $i++) {
            $code .= $chars[rand(0, strlen($chars)-1)];
        }
        return $code;
    }
}
