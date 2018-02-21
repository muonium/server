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

	public function DefaultAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$resp['token'] = $this->_token;
	}

	public function shareFileAction() {
		if(isset($_POST['id']) && is_numeric($_POST['id']) && isset($_POST['dk'])) {
			$file_id = intval($_POST['id']);
			$this->_modelFiles = new m\Files($_SESSION['id']);
			if($this->_modelFiles->setDK($file_id, $_POST['dk'])) {
				echo URL_APP.'/dl/?'.setURL($file_id);
				exit;
			}
		}
		echo 'err';
	}

	public function unshareFileAction() {
		if(isset($_POST['id']) && is_numeric($_POST['id'])) {
			$this->_modelFiles = new m\Files($_SESSION['id']);
			if($this->_modelFiles->setDK(intval($_POST['id']), null)) {
				echo 'ok';
				exit;
			}
		}
		echo 'err';
	}
}
