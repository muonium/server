<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \application\models as m;

class user extends l\Controller {
	function __construct() {
        parent::__construct([
            'mustBeLogged' => true
        ]);
    }

	private function delete($resp) {
		return $resp;
	}

	private function get($resp) {
		return $resp;
	}

	private function register($resp) {
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
