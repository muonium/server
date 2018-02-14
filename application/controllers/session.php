<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \application\models as m;

class session extends l\Controller {
    private $_message;

	function __construct() {
        parent::__construct([
            //'mustBeLogged' => false
        ]);
    }

    function authcodeAction() {
        // User sent an auth code
        sleep(1);

		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
		elseif(isset($data->uid) && isset($data->password) && isset($data->code)) {
			if(is_numeric($data->uid) && strlen($data->code) === 8) {
				$brute = new l\AntiBruteforce();
	            $brute->setFolder(ROOT.DS."tmp");
	            $brute->setNbMaxAttemptsPerHour(50);

				$user = new m\Users($data->uid);
				$user->password = $data->password;
				$pass = $user->getPassword();
				$cek = $user->getCek();

				if($pass !== false && password_verify($user->password, $pass)) {
					// Password is ok
					$user->updateLastConnection();
					$mUserVal = new m\UserValidation($data->uid);
					if($mUserVal->getKey()) {
						// Key found - User needs to validate its account (double auth only for validated accounts)
						$resp['code'] = 401;
						$resp['message'] = 'validate';
					}
					elseif($user->getDoubleAuth()) {
						$code = $user->getCode();
						if($code && $code === $data->code) {
							// Double auth code is ok, send token
							$resp['code'] = 200;
							$resp['status'] = 'success';
							$resp['token'] = $this->buildToken($data->uid);
							$resp['data']['cek'] = $cek;
						} else {
							// Wrong code
		                    $brute->setSID('doubleAuth');
		                    $brute->Control();
							$resp['code'] = 401;
		                    $resp['message'] = 'bruteforceErr'.$brute->getError();
		                }
					}
					else {
						// Double auth is disabled but password is still ok, then, send token
						$resp['code'] = 200;
						$resp['status'] = 'success';
						$resp['token'] = $this->buildToken($data->uid);
						$resp['data']['cek'] = $cek;
					}
				}
				else {
					// UID exists but incorrect password - Anti bruteforce with user id
                	$brute->setId($data->uid);
                	$brute->Control();
					$resp['code'] = 401;
                	$resp['message'] = 'bruteforceErr'.$brute->getError();
				}
			}
		} else {
            $resp['message'] = 'emptyField';
        }

		http_response_code($resp['code']);
		echo json_encode($resp);
    }

	private function login($resp) {
		sleep(2);
		$data = h\httpMethodsData::getValues();
		if(isset($data->username) && isset($data->password)) {
			$new_user = new m\Users();

			if(filter_var($data->username, FILTER_VALIDATE_EMAIL) === false) {
                $new_user->login = $data->username;
				$email = $new_user->getEmail();
            } else {
                $new_user->email = $data->username;
				$email = $data->username;
			}

			$new_user->password = $data->password;
            $brute = new l\AntiBruteforce();
            $brute->setFolder(ROOT.DS."tmp");
            $brute->setNbMaxAttemptsPerHour(50);

			if(!($id = $new_user->getId())) {
                // User doesn't exists - Anti bruteforce with session id
                $brute->setSID();
                $brute->Control();
				$resp['code'] = 401;
                $resp['message'] = 'bruteforceErr'.$brute->getError();
            }
			else {
                $new_user->id = $id;
                $pass = $new_user->getPassword();
				$cek = $new_user->getCek();

                if($pass !== false) {
                    if(password_verify($new_user->password, $pass)) {
                        // Mail, password ok, connection
						$resp['code'] = 200;
						$resp['status'] = 'success';

						$new_user->updateLastConnection();
                        $mUserVal = new m\UserValidation($id);

                        if(!($mUserVal->getKey())) {
                            // Unable to find key - Validation is done
                            if($new_user->getDoubleAuth()) {
                                // Double auth : send an email with a code
                                $code = $this->generateCode();
                                $new_user->updateCode($code);
                                $mail = new l\Mail();
                                $mail->_to = $e;
                                $mail->_subject = "Muonium - ".self::$txt->Profile->doubleAuth;
                                $mail->_message = str_replace("[key]", $code, self::$txt->Login->doubleAuthMessage);
                                $mail->send();
								$resp['message'] = 'doubleAuth';
                            }
                            else { // Logged
								$resp['token'] = $this->buildToken($id);
							}
                            $resp['data']['cek'] = $cek; //the CEK is already url encoded in the database
                        }
                        else {
                            // Key found - User needs to validate its account (double auth only for validated accounts)
							$resp['code'] = 401;
                            $resp['message'] = 'validate';
                        }
                        return $resp;
                    }
                }

				// User exists but incorrect password - Anti bruteforce with user id
                $brute->setId($id);
                $brute->Control();
				$resp['code'] = 401;
                $resp['message'] = 'bruteforceErr'.$brute->getError();
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
		$token = h\httpMethodsData::getToken();
		if($token === null) {
			return $resp;
		}
		$token = $this->verifyToken($token);
		if($token !== false) { // Token is still valid
			$decodedToken = $this->getDecodedToken();
			$resp['code'] = 200;
			$resp['status'] = 'success';
			$resp['token'] = $token; // Send it in the response because a new one could be generated when verifying
			$resp['data']['uid'] = $decodedToken['data']['uid'];
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
				$resp = $this->login($resp);
				break;
			default:
				$resp['code'] = 405; // Method Not Allowed
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
    }

    function generateCode() {
        $code = '';
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        for($i = 0; $i < 8; $i++) {
            $code .= $chars[rand(0, strlen($chars)-1)];
        }
        return $code;
    }
}
