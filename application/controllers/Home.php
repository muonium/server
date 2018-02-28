<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;

class Home extends l\Controller {
	public function languagesAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$resp['code'] = 200;
		$resp['status'] = 'success';
		$resp['token'] = $this->_token;
		$resp['data'] = self::$languages;
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
