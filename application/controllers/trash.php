<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \application\models as m;

class trash extends l\Controller {
	
    function __construct() {
        parent::__construct([
            'mustBeLogged' => true
        ]);
    }

	private function mv($resp, $toTrash = true) {
		$data = h\httpMethodsData::getValues();
        $trash = $toTrash ? 1 : 0;

        if(isset($data->files) && is_array($data->files)) {
			$this->_modelFiles = new m\Files($this->_uid);
            foreach($data->files as $file) {
                if(is_numeric($file)) $this->_modelFiles->updateTrash($file, $trash);
            }
        }

        if(isset($data->folders) && is_array($data->folders)) {
			$this->_modelFolders = new m\Folders($this->_uid);
            foreach($data->folders as $folder) {
                if(is_numeric($folder)) $this->_modelFolders->updateTrash($folder, $trash);
            }
        }

		$resp['code'] = 200;
		$resp['status'] = 'success';
		$resp['message'] = 'moved';
		return $resp;
    }

	public function DefaultAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$resp['token'] = $this->_token;

		if($method === 'post') {
			$resp = $this->mv($resp, true);
		} elseif($method === 'delete') {
			$resp = $this->mv($resp, false);
		} else {
			$resp['code'] = 405; // Method Not Allowed
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
	}
}
