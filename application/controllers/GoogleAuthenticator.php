<?php

namespace application\controllers;

use \library as h;
use \library\MVC as l;
use \library\GA as ga;
use \application\models as m;
use \config as conf;

class GoogleAuthenticator extends l\Controller {

    private $_forbiddenChars = ["0", "1", "8", "9"];
    
    function __construct() {
        parent::__construct();
    }
    
    public function DefaultAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$resp['token'] = $this->_token;

		http_response_code($resp['code']);
		echo json_encode($resp);
	}
    
    public function verifyCodeAction() {
        header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$resp['token'] = $this->_token;

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
        elseif(isset($data->username) && isset($data->auth_code)) {
            
			$googleAuth = new ga\GoogleAuthenticator();
            
            $secret = strtoupper(str_replace($this->_forbiddenChars, "A", md5($data->username.conf\confGoogleAuthenticator::salt)));
            
            if ($googleAuth->checkCode($secret, $data->auth_code)) {
                $resp['code'] = 200;
                $resp['status'] = 'success';
				$resp['message'] = 'valid';
                $resp['data']['auth_code'] = $data->auth_code;
            } else {
                $resp['code'] = 403;
                $resp['status'] = 'error';
				$resp['message'] = 'authCodeNotValid';
                $resp['data']['auth_code'] = $data->auth_code;
            }
        } else {
			$resp['message'] = 'emptyField';
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
    }
    
    public function generateQRcodeAction() {
        header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$resp['token'] = $this->_token;

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
        elseif(isset($data->username)) {
            
			$googleAuth = new ga\GoogleAuthenticator();
            
            $secret = strtoupper(str_replace($this->_forbiddenChars, "A", md5($data->username.conf\confGoogleAuthenticator::salt)));
            
            $resp['data']['QRcode'] = ga\GoogleQrUrl::generate($data->username, $secret, 'Muonium');
            
        } else {
			$resp['message'] = 'emptyField';
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
    }
}

?>