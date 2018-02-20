<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \application\models as m;

class dl extends l\Controller {
    private $_modelFiles;
	private $_modelFolders;
	private $sharerID = null;
	private $filename = null;

    function __construct() {
        parent::__construct();
    }

    public function chunkAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$this->isLogged(); // It doesn't matter if user is logged or not for now but it sets the (new) token if it exists
		$resp['token'] = $this->_token;

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
		elseif(isset($data->uid) && is_numeric($data->uid) && isset($data->filename) && isset($data->folder_id) && is_numeric($data->folder_id) && isset($data->line) && is_numeric($data->line)) {
			$this->sharerID = $data->uid;
			$filename = $this->parseFilename($data->filename);
			if($filename !== false) {
				$this->filename = $filename;
				$path = $this->getUploadFolderPath($data->folder_id);
				if($path !== false) {
					$resp['code'] = 200;
					$resp['status'] = 'success';
					$filepath = NOVA.'/'.$this->sharerID.'/'.$path.$filename;
					$file = new \SplFileObject($filepath, 'r');
				    $file->seek($data->line);
				    $resp['data'] = str_replace("\r\n", "", $file->current());
				} else {
					$resp['message'] = 'notExists';
				}
			} else {
				$resp['message'] = 'notExists';
			}
		} else {
			$resp['message'] = 'emptyField';
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
	}

	public function nbChunksAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$this->isLogged(); // It doesn't matter if user is logged or not for now but it sets the (new) token if it exists
		$resp['token'] = $this->_token;

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
		elseif(isset($data->uid) && is_numeric($data->uid) && isset($data->filename) && isset($data->folder_id) && is_numeric($data->folder_id)) {
			$resp['data'] = 0;
			$this->sharerID = $data->uid;
		    $filename = $this->parseFilename($data->filename);
			if($filename !== false) {
				$this->filename = $filename;
				$path = $this->getUploadFolderPath($data->folder_id);
				if($path !== false) {
					$filepath = NOVA.'/'.$sharerID.'/'.$path.$filename;
				    if(file_exists($filepath)) {
						$resp['code'] = 200;
						$resp['status'] = 'success';
				        $file = new \SplFileObject($filepath, 'r');
				        $file->seek(PHP_INT_MAX);
						if($file->current() === "EOF") { // A line with "EOF" at the end of the file when the file is complete
							$resp['data'] = $file->key()-1;
						} else {
							$resp['data'] = $file->key();
						}
					} else {
						$resp['message'] = 'notExists';
					}
				} else {
					$resp['message'] = 'notExists';
				}
			} else {
				$resp['message'] = 'notExists';
			}
		} else {
			$resp['message'] = 'emptyField';
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
	}

	public function DefaultAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues('array'); // We need an array in this case
		$this->isLogged(); // It doesn't matter if user is logged or not for now but it sets the (new) token if it exists
		$resp['token'] = $this->_token;

		if($method !== 'get') {
			$resp['code'] = 405; // Method Not Allowed
		}
		elseif(is_array($data) && count($data) > 0) {
			$fid = getFileId(key($data));
			if(is_numeric($fid)) {
				$this->_modelFiles = new m\Files();
				$infos = $this->_modelFiles->getInfos($fid);
				if($infos !== false) {
					$resp['code'] = 200;
					$resp['status'] = 'success';
					$resp['data'] = $infos;
				} else {
					$resp['message'] = 'notShared';
				}
			}
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
	}

	private function parseFilename($f) {
		$f = str_replace(['|', '/', '\\', ':', '*', '?', '<', '>', '"'], "", $f); // not allowed chars
		if(strlen($f) > 128) { // max length 128 chars
			$f = substr($f, 0, 128);
		}
		return $f;
	}

	private function getUploadFolderPath($folder_id) {
		if($this->sharerID === null || !is_numeric($this->sharerID) || $this->filename === null) return false;
		// Check if the file is shared
		$this->_modelFiles = new m\Files($this->sharerID);
		if(!($this->_modelFiles->isShared($this->filename, $folder_id))) return false;

		// Get the full path of an uploaded file until its folder using SESSION
		if(isset($_SESSION['upload'][$folder_id]['path'])) {
			return $_SESSION['upload'][$folder_id]['path'];
		}

		$this->_modelFolders = new m\Folders($this->sharerID);

		$path = $this->_modelFolders->getFullPath($folder_id);
		if($path === false || !is_dir(NOVA.'/'.$this->sharerID.'/'.$path)) {
			return false;
		}

		if($path != '') $path .= '/';
		$_SESSION['upload'][$folder_id]['path'] = $path;
		return $path;
	}
}
