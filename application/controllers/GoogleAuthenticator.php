<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \Muonium\GoogleAuthenticator as ga;
use \application\models as m;
use \config as conf;

class GoogleAuthenticator extends l\Controller {
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

    public function generateQRcodeAction() {
        header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
        else {
            $user = new m\Users($this->_uid);
            
            $username = $user->getLogin();
            
            $googleAuth = new ga\GoogleAuthenticator();
            $secret = bin2hex(openssl_random_pseudo_bytes(10));
            $secret = (new ga\FixedBitNotation(5, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', true, true))->encode($secret);

            $user->updateSecretKey($secret);
            
            $url = ga\GoogleQrUrl::generate($username, $secret, 'Muonium');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Muonium');
            $result = curl_exec($ch);
            curl_close($ch);

            $resp['code'] = 200;
            $resp['status'] = 'success';
            $resp['data']['QRcode'] = base64_encode($result);
            $resp['data']['secretKey'] = $secret;
        }

		http_response_code($resp['code']);
		echo json_encode($resp);
    }
}
