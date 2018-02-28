<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \application\models as m;

class FileManager extends l\Controller {
	/* Common class between files, folders, mv and rm that inherit it */

	public $_filename = ''; 	// current file uploaded
	public $_path = ''; 		// current path
	public $_folderId = 0; 		// current folder id (0 = root)
	public $_trash = 0; 		// 0 : view contents not in the trash || 1 : view contents in the trash

	protected $_modelFiles;
	protected $_modelFolders;
	protected $_modelStorage;
	protected $_modelUsers;

	protected $redis = null;

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

	protected function getFolderVars() {
		// User sent folder_id, initialize model folders, check if folder exists and set folder_id and path in class attributes
		$data = h\httpMethodsData::getValues();
		$this->_modelFolders = new m\Folders($this->_uid);

		if(!isset($data->folder_id) || $data->folder_id == 0) {
			$this->_path = '';
			$this->_folderId = 0;
		} else {
			$folder_id = urldecode($data->folder_id);
			if(!is_pos_digit($folder_id)) return false;
			$path = $this->_modelFolders->getPath($folder_id);
			if($path === false) return false;
			$path .= $this->_modelFolders->getFoldername($folder_id);
			$this->_path = $path.'/';
			$this->_folderId = intval($folder_id);
		}
		return true;
	}

	protected function getUploadFolderPath($folder_id) {
		// Get the full path of an uploaded file until its folder using Redis
		if($folder_id === 0) return '';
		if($path = $this->redis->get('token:'.$this->_token.':folder:'.$folder_id)) {
			return $path;
		}
		$this->_modelFolders = new m\Folders($this->_uid);

		$path = $this->_modelFolders->getFullPath($folder_id);
		if($path === false || !is_dir(NOVA.'/'.$this->_uid.'/'.$path)) {
			return false;
		}

		if($path !== '') $path .= '/';
		$this->redis->set('token:'.$this->_token.':folder:'.$folder_id, $path);
		return $path;
	}

	protected function parseFilename($f) {
		$f = str_replace(['|', '/', '\\', ':', '*', '?', '<', '>', '"'], "", $f); // not allowed chars
		if(strlen($f) > 128) { // max length 128 chars
			$f = substr($f, 0, 128);
		}
		return $f;
	}

	protected function addSuffixe($file, $suffixe, $max = 128) {
        $double_extensions = ['tar.gz', 'tar.bz', 'tar.xz', 'tar.bz2'];

        $pos = strpos($file, '.');
		$max -= strlen($suffixe);
        if($pos === false) return substr($file, 0, $max).$suffixe;

        $pathinfo = pathinfo($file);
        if(empty($pathinfo['extension'])) return substr($file, 0, $max).$suffixe;

        $file_length = strlen($file);
        for($i = 0; $i < count($double_extensions); $i++) {
            $length = strlen($double_extensions[$i])+1;
            if($file_length > $length) {
                $end = substr($file, -1*$length);
                if('.'.$double_extensions[$i] == $end) {
					$max -= strlen($end);
                    $start = substr($file, 0, $file_length-$length);
                    return substr($start, 0, $max).$suffixe.$end;
                }
            }
        }
		$max -= strlen('.'.$pathinfo['extension']);
        return substr($pathinfo['filename'], 0, $max).$suffixe.'.'.$pathinfo['extension'];
    }
}
