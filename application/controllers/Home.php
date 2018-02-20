<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;

class Home extends l\Controller {
	public function DefaultAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$resp['token'] = $this->_token;

		http_response_code($resp['code']);
		echo json_encode($resp);
	}
}
