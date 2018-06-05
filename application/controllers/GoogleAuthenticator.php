<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \Muonium\GoogleAuthenticator as ga;
use \application\models as m;
use \config as conf;

class GoogleAuthenticator extends l\Controller {
    private $redis = null;

    function __construct() {
		parent::__construct([
			'mustBeLogged' => true
		]);
		$this->redis = $this->getRedis();
	}

    public function DefaultAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$resp['token'] = $this->_token;

		http_response_code($resp['code']);
		echo json_encode($resp);
	}

    public function generateAction() {
        header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
        else {
            $user = new m\Users($this->_uid);
            if(!($user->isDoubleAuthGA())) { // Cannot regenerate when ga is already enabled
                $gaRedis = $this->redis->get('uid:'.$this->_uid.':ga');
                if(!$gaRedis) $gaRedis = 0;
                $resp['code'] = 200;
                $resp['status'] = 'success';
                $resp['token'] = $this->_token;

                if(intval($gaRedis) <= time()) {
                    $this->redis->set('uid:'.$this->_uid.':ga', time() + 30);
                    $username = $user->getLogin();

                    $googleAuth = new ga\GoogleAuthenticator();
                    $secret = bin2hex(openssl_random_pseudo_bytes(10));
                    $secret = (new ga\FixedBitNotation(5, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', true, true))->encode($secret);

                    $user->updateSecretKey($secret);
                    $user->deleteBackupCodes();
                    $user->generateBackupCodes();
                    $backupCodes = $user->getBackupCodes();

                    $url = ga\GoogleQrUrl::generate($username, $secret, 'Muonium');

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HEADER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Muonium');
                    $result = curl_exec($ch);
                    curl_close($ch);

                    $resp['data']['QRcode'] = base64_encode($result);
                    $resp['data']['secretKey'] = $secret;
                    $resp['data']['backupCodes'] = $backupCodes;
                } else { // Wait
                    $resp['message'] = 'wait';
                }
            }
        }

		http_response_code($resp['code']);
		echo json_encode($resp);
    }

    public function backupCodesAction() {
        header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();

		if($method !== 'get') {
			$resp['code'] = 405; // Method Not Allowed
		}
        else {
            $resp['token'] = $this->_token;
            
            $user = new m\Users($this->_uid);
            if($user->isDoubleAuthGA()) {
                $googleAuth = new ga\GoogleAuthenticator();
                $secret = $user->getSecretKeyGA();

                if($user->isCodeValid($data->code)) {
                    $backupCodes = $user->getBackupCodes();

                    $resp['code'] = 200;
                    $resp['status'] = 'success';
                    $resp['data']['backupCodes'] = $backupCodes;
                } else {
                    $resp['code'] = 403;
                    $resp['status'] = 'error';
                    $resp['message'] = 'badCode';
                }
            } else {
                $resp['code'] = 401;
                $resp['status'] = 'error';
                $resp['message'] = 'notDoubleAuthGA';
            }
        }

		http_response_code($resp['code']);
		echo json_encode($resp);
    }

    public function regenerateBackupCodesAction() {
        header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
        else {
            $resp['token'] = $this->_token;
            
            $user = new m\Users($this->_uid);
            if($user->isDoubleAuthGA()) {
                $googleAuth = new ga\GoogleAuthenticator();
                $secret = $user->getSecretKeyGA();

                if($user->isCodeValid($data->code)) {
                    $user->deleteBackupCodes();
                    $user->generateBackupCodes();
                    $backupCodes = $user->getBackupCodes();

                    $resp['code'] = 200;
                    $resp['status'] = 'success';
                    $resp['data']['backupCodes'] = $backupCodes;
                } else {
                    $resp['code'] = 403;
                    $resp['status'] = 'error';
                    $resp['message'] = 'badCode';
                }
            } else {
                $resp['code'] = 401;
                $resp['status'] = 'error';
                $resp['message'] = 'notDoubleAuthGA';
            }
        }

		http_response_code($resp['code']);
		echo json_encode($resp);
    }
}
