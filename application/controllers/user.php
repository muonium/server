<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \application\models as m;

class user extends l\Controller {
	private $_modelUser;
	private $_modelBan;
	private $_modelFiles;
	private $_modelStorage;
	private $_modelFolders;
	private $_modelUserLostPass;
	private $_modelUserValidation;

	function __construct() {
        parent::__construct([
            'mustBeLogged' => true
        ]);
    }

	private function delete($resp) {
		function removeDirectory($path) {
			$files = glob($path . '/*');
			foreach ($files as $file) {
				is_dir($file) ? removeDirectory($file) : unlink($file);
			}
			rmdir($path);
			return;
		}

		if(!is_numeric($this->_uid)) {
			return $resp;
		}

		$this->_modelUser = new m\Users($this->_uid);
		$this->_modelStorage = new m\Storage($this->_uid);
		$this->_modelFiles = new m\Files($this->_uid);
		$this->_modelFolders = new m\Folders($this->_uid);
		$this->_modelBan = new m\Ban($this->_uid);
		$this->_modelUserValidation = new m\UserValidation($this->_uid);
		$this->_modelUserLostPass = new m\UserLostPass($this->_uid);

		if($this->_modelUserLostPass->Delete()) {
			if($this->_modelUserValidation->Delete()) {
				if($this->_modelBan->deleteBan()) {
					if($this->_modelFiles->deleteFilesfinal()) {
						if($this->_modelFolders->deleteFoldersfinal()) {
							if($this->_modelStorage->deleteStorage()) {
								if($this->_modelUser->deleteUser()) {
									removeDirectory(NOVA.'/'.$this->_uid);
									$this->removeTokens($this->_uid);

									$resp['code'] = 200;
									$resp['status'] = 'success';
									$resp['message'] = 'removeToken';
									return $resp;
								}
							}
						}
					}
				}
			}
		}
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
