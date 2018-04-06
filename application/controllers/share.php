<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \application\controllers as c;
use \application\models as m;

class share extends c\FileManager {

    function __construct() {
        parent::__construct();
    }

	public function idAction($id) {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$data->id = $id;
		$resp['token'] = $this->_token;

		if($method === 'post') {
			$resp = $this->post($resp, $data);
		} elseif($method === 'delete') {
			$resp = $this->delete($resp, $data);
		} else {
			$resp['code'] = 405; // Method Not Allowed
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
	}

	public function DefaultAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$resp['token'] = $this->_token;

		if($method === 'post') {
			$resp = $this->post($resp, $data);
		} else {
			$resp['code'] = 405; // Method Not Allowed
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
	}

	private function post($resp, $data) {
		if(isset($data->id) && is_pos_digit($data->id) && isset($data->dk)) {
			$this->_modelFiles = new m\Files($this->_uid);
			if($this->_modelFiles->setDK(intval($data->id), $data->dk)) {
				$resp['code'] = 200;
				$resp['status'] = 'success';
				$resp['data'] = URL_APP.'/#/dl/'.setURL(intval($data->id));
			}
		} else {
			$resp['message'] = 'emptyField';
		}
		return $resp;
	}

	private function delete($resp, $data) {
		if(isset($data->id) && is_pos_digit($data->id)) {
			$this->_modelFiles = new m\Files($this->_uid);
			if($this->_modelFiles->setDK(intval($data->id), null)) {
				$resp['code'] = 200;
				$resp['status'] = 'success';
			}
		} else {
			$resp['message'] = 'emptyField';
		}
		return $resp;
	}
}
