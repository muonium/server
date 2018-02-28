<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \application\controllers as c;
use \application\models as m;

class rm extends c\FileManager {

    function __construct() {
        parent::__construct();
    }

	public function DefaultAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$resp['token'] = $this->_token;

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		} else {
			$resp['code'] = 200;
			$resp['status'] = 'success';
			$this->_modelFolders = new m\Folders($this->_uid);
	        $this->_modelFiles = new m\Files($this->_uid);

			if(isset($data->files) && is_array($data->files) && count($data->files) > 0) {
				$data->files = array_unique($data->files);
				$this->rmFiles($data->files);
			}
			if(isset($data->folders) && is_array($data->folders) && count($data->folders) > 0) {
				$data->folders = array_unique($data->folders);
				$this->rmFolders($data->folders);
			}
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
	}

	private function rmFiles($files) {
        $total_size = 0;
        $tab_folders = []; // key: folder id, value: array (path to folder, updated size)
		$removed_files = [];
        $path = '';

		foreach($files as $file) {
			if(isset($file->folder_id) && isset($file->id) && is_pos_digit($file->folder_id) && is_pos_digit($file->id)) {
				if(in_array(intval($file->id), $removed_files)) {
					continue; // Already removed
				}
				if(array_key_exists(intval($file->folder_id), $tab_folders)) {
					$path = $tab_folders[intval($file->folder_id)][0];
				} else {
					$path = $this->_modelFolders->getFullPath($file->folder_id);
					if($path === false) {
						continue;
					}
					$tab_folders[intval($file->folder_id)] = [$path, 0];
				}
				$fsize = $this->rmFile(intval($file->id), $path.'/', intval($file->folder_id));
				if(count($fsize) !== 2) {
					continue;
				}
				$total_size += $fsize[0];
				if($fsize[1] === true) { // Update folder size only for completed files.
					$tab_folders[intval($file->folder_id)][1] += $fsize[0];
				}
			}
		}
        // Decrement storage counter
		if($total_size > 0) {
	        $this->_modelStorage = new m\Storage($this->_uid);
	        if($this->_modelStorage->decrementSizeStored($total_size)) {
				$size_stored = $this->redis->get('token:'.$this->_token.':size_stored');
				if($size_stored !== null) {
					$this->redis->set('token:'.$this->_token.':size_stored', intval($size_stored)-$total_size);
				}
			}
		}
        // Update folders size
        foreach($tab_folders as $folder => $infos) {
            $this->_modelFolders->updateFoldersSize($folder, -1*$infos[1]);
		}
		return true;
    }

	private function rmFolders($folders) {
		$total_size = 0;
		$tab_folders = []; // key: folder id (parent), value: updated size
		$removed_folders = [];

		foreach($folders as $folder) {
			if(isset($folder->id) && isset($folder->parent) && is_pos_digit($folder->id) && is_pos_digit($folder->parent)) {
				if(in_array(intval($folder->id), $removed_folders)) {
					continue; // Already removed
				}
				if(!array_key_exists(intval($folder->parent), $tab_folders)) {
					$tab_folders[intval($folder->parent)] = 0;
				}
				$size = $this->rmFolder(intval($folder->id), intval($folder->parent)); // Add parent to ensure that the parent is correct
				$removed_folders[] = intval($folder->id);
				if(is_numeric($size) && $size > 0) {
					$total_size += $size;
					$tab_folders[intval($folder->parent)] += $size;
				}
			}
		}
		// Decrement storage counter
		if($total_size > 0) {
			$this->_modelStorage = new m\Storage($this->_uid);
			if($this->_modelStorage->decrementSizeStored($total_size)) {
				$size_stored = $this->redis->get('token:'.$this->_token.':size_stored');
				if($size_stored !== null) {
					$this->redis->set('token:'.$this->_token.':size_stored', intval($size_stored)-$total_size);
				}
			}
		}
		// Update folders size
		foreach($tab_folders as $folder => $removed_size) {
			$this->_modelFolders->updateFoldersSize($folder, -1*$removed_size);
		}
		return true;
    }

	private function rmFile($id, $path, $folder_id) {
		// $folder_id is used only to delete Redis record
        if(!is_pos_digit($id) || !is_pos_digit($folder_id)) {
			return [0, true];
		}
        $filename = $this->_modelFiles->getFilename($id);
        if($filename !== false) {
            if(file_exists(NOVA.'/'.$this->_uid.'/'.$path.$filename)) {
				$this->redis->del('token:'.$this->_token.':folder:'.$folder_id.':'.$filename);
                // deleteFile() returns file size
                $fsize = $this->_modelFiles->deleteFile($id);
                $completed = true;
                if($fsize === -1) {
                    $completed = false;
                    $fsize = @filesize(NOVA.'/'.$this->_uid.'/'.$path.$filename);
                }
                unlink(NOVA.'/'.$this->_uid.'/'.$path.$filename);
                return [intval($fsize), $completed];
            }
        }
        return [0, true];
    }

    private function rmRdir($id) {
        // This is a recursive method
        if(!is_pos_digit($id)) {
			return;
		}
        $path = $this->_modelFolders->getFullPath($id);
        if($path === false) {
			return;
		}
        $full_path = NOVA.'/'.$this->_uid.'/'.$path;
        if(!is_dir($full_path)) {
			return;
		}
		$this->redis->del('token:'.$this->_token.':folder:'.$id);

        // Delete subfolders
        if($subdirs = $this->_modelFolders->getChildren($id)) {
            foreach($subdirs as $subdir) {
                $this->rmRdir($subdir['id']);
			}
        }
        // Delete files
        foreach(glob("{$full_path}/*") as $file) {
            if(is_file($file)) {
				unlink($file);
			}
        }
        // Delete files in db
        $this->_modelFiles->deleteFiles($id);
        // Delete folder
        rmdir($full_path);
    }

    private function rmFolder($id, $parent) {
        if(!is_pos_digit($id)) {
			return 0;
		}
        $size = $this->_modelFolders->getSize($id);
        if($size === false) {
			return 0;
		}
		$fp = $this->_modelFolders->getParent($id);
		if($fp === false || intval($parent) !== intval($fp)) {
			return 0;
		}
        // Delete folder, files, subfolders and also files in db
        $this->rmRdir($id);
        // Delete folders and subfolders in db and update parents folder size
        $this->_modelFolders->delete($id);
        return intval($size);
    }
}
