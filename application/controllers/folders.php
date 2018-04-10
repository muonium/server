<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \application\controllers as c;
use \application\models as m;

class folders extends c\FileManager {

    function __construct() {
        parent::__construct();
    }

	public function addAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$resp['token'] = $this->_token;

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
        elseif(isset($data->name) && isset($data->folder_id) && is_pos_digit($data->folder_id)) {
			if($this->getFolderVars()) {
	            $folder = $this->parseFilename(urldecode($data->name));
	            if(strlen($folder) > 64) { // max length 64 chars
	                $folder = substr($folder, 0, 64);
				}

	            if(is_dir(NOVA.'/'.$this->_uid.'/'.$this->_path) && !is_dir(NOVA.'/'.$this->_uid.'/'.$this->_path.$folder)) {
	                $this->_modelFolders->name = $folder;
	                $this->_modelFolders->parent = $this->_folderId;
	                $this->_modelFolders->path = $this->_path;
	                if($this->_modelFolders->addFolder()) {
						$resp['code'] = 201;
						$resp['status'] = 'success';
	                	mkdir(NOVA.'/'.$this->_uid.'/'.$this->_path.$folder, 0770);
						$resp['data'] = $this->_modelFolders->getLastInsertedId();
	                }
	            } else {
					$resp['message'] = 'exists';
				}
			}
        } else {
			$resp['message'] = 'emptyField';
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
    }

	public function openAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$resp['token'] = $this->_token;

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		} else {
			$folder_id = isset($data->folder_id) && is_pos_digit($data->folder_id) ? intval($data->folder_id) : 0;
			$this->_trash = isset($data->trash) && $data->trash == 1 ? 1 : 0;
			if($folder_id === 0) { // root
				$this->_path = '';
				$this->_folderId = 0;
				$resp = $this->getTree($resp);
			} else {
				$this->_modelFolders = new m\Folders($this->_uid);
				$path = $this->_modelFolders->getPath($folder_id);
				if($path !== false) {
					$path .= $this->_modelFolders->getFoldername($folder_id);
					if(is_dir(NOVA.'/'.$this->_uid.'/'.$path)) {
						$this->_path = $path;
						$this->_folderId = $folder_id;
						$resp = $this->getTree($resp);
					}
				}
			}
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
    }

	public function renameAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$resp['token'] = $this->_token;

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
		elseif(isset($data->old) && isset($data->new) && isset($data->folder_id) && is_pos_digit($data->folder_id)) {
			$this->_modelFiles = new m\Files($this->_uid);
			$this->_modelFolders = new m\Folders($this->_uid);

			$old = urldecode($data->old);
			$new = $this->parseFilename(urldecode($data->new));
			if(strlen($new) > 64) { // max folder length 128 chars
				$new = substr($new, 0, 128);
			}

            if($new !== false && $old !== $new) {
                $path = $this->_modelFolders->getFullPath($data->folder_id);
				if($path !== false) {
	                if($path !== '') $path .= '/';
	                if(is_dir(NOVA.'/'.$this->_uid.'/'.$path.$old) && !is_dir(NOVA.'/'.$this->_uid.'/'.$path.$new)) {
						$resp['code'] = 200;
						$resp['status'] = 'success';
						$this->_modelFolders->rename($path, $old, $new);
						rename(NOVA.'/'.$this->_uid.'/'.$path.$old, NOVA.'/'.$this->_uid.'/'.$path.$new);
	                } else {
						$resp['message'] = 'exists';
					}
				}
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
		$data = h\httpMethodsData::getValues();
		$resp['token'] = $this->_token;
	}

	private function getTree($resp) {
		$resp['code'] = 200;
		$resp['status'] = 'success';

		if(!isset($this->_modelFiles)) {
			$this->_modelFiles = new m\Files($this->_uid);
		}
		if(!isset($this->_modelFolders)) {
			$this->_modelFolders = new m\Folders($this->_uid);
		}

        $this->_modelStorage = new m\Storage($this->_uid);
        $quota = $this->_modelStorage->getUserQuota();
        $stored = $this->_modelStorage->getSizeStored();

		if($quota !== false && $stored !== false) {
			$this->redis->set('token:'.$this->_token.':user_quota', $quota);
			$this->redis->set('token:'.$this->_token.':size_stored', $stored);
		}

		$path = htmlspecialchars($this->_modelFolders->getFullPath($this->_folderId));
		$path_d = explode('/', $path);

		$resp['data']['path']    = $path;
		$resp['data']['title']   = $path === '' ? null : end($path_d);
		$resp['data']['stored']  = $stored;
		$resp['data']['quota']   = $quota;
		$resp['data']['folders'] = [];
		$resp['data']['files']   = [];

		if($this->_folderId != 0) {
            $resp['data']['parent'] = $this->_modelFolders->getParent($this->_folderId);
        }

        if($subdirs = $this->_modelFolders->getChildren($this->_folderId, $this->_trash)) {
            foreach($subdirs as $subdir) {
				$folder = [];
				$folder['id'] = $subdir['id'];
				$folder['name'] = htmlspecialchars($this->parseFilename($subdir['name']));
				$folder['size'] = $subdir['size'];
				$folder['path'] = htmlspecialchars($subdir['path']);
				$folder['parent'] = intval($subdir['parent']);
                $folder['nb_elements'] = count(glob(NOVA.'/'.$this->_uid.'/'.$subdir['path'].$subdir['name']."/*"));
				$resp['data']['folders'][] = $folder;
            }
        }

        if($files = $this->_modelFiles->getFiles($this->_folderId, $this->_trash)) {
            foreach($files as $file) {
				$fpath = $path;
                if(array_key_exists('path', $file) && array_key_exists('dname', $file)) {
                    $fpath = $file['path'].$file['dname'];
                }
				$f = [];
				$f['id'] = $file['id'];
				$f['is_shared'] = $file['dk'] === null || strlen($file['dk']) === 0 ? false : true;
				$f['name'] = htmlspecialchars($this->parseFilename($file['name']));
				$f['folder_id'] = intval($file['folder_id']);
				$f['path'] = htmlspecialchars($fpath);
				$f['is_completed'] = $file['size'] < 0 ? false : true;
				$f['size'] = $f['is_completed'] ? $file['size'] : @filesize(NOVA.'/'.$this->_uid.'/'.$fpath.'/'.$file['name']);
				$f['lastmod'] = $file['last_modification'];
				$f['url'] = URL_APP.'/#/dl/'.setURL($file['id']);
				$resp['data']['files'][] = $f;
            }
        }

        return $resp;
    }
}
