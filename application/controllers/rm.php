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
			$this->_modelFolders = new m\Folders($this->_uid);
	        $this->_modelFiles = new m\Files($this->_uid);

			if(isset($data->files) && is_array($data->files)) {
				$this->rmFiles($data->files);
			}
			if(isset($data->folders) && is_array($data->folders)) {
				// ex: "folders":[{"folder_id":25,"parent":0},{"folder_id":12,"parent":1}]
				$this->rmFolders($data->folders);
			}
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
	}

	private function rmFiles($files) {
        $total_size = 0;
        $tab_folders = []; // key : folder id, value : array ( path to folder, updated size )
        $path = '';

        if(isset($_POST['files']) && isset($_POST['ids'])) {
            $files = explode("|", urldecode($_POST['files']));
            $ids = explode("|", urldecode($_POST['ids']));

            $nbFiles = count($files);
            $nbIds = count($ids);

            if($nbFiles === $nbIds && $nbFiles > 0) {
                for($i = 0; $i < $nbFiles; $i++) {
                    $folder_id = $ids[$i];
                    if(array_key_exists($folder_id, $tab_folders)) {
                        $path = $tab_folders[$folder_id][0];
					}
                    else {
                        $path = $this->_modelFolders->getFullPath($folder_id);
                        if($path === false) continue;
                        $tab_folders[$folder_id][0] = $path;
                        $tab_folders[$folder_id][1] = 0;
                    }

                    $fsize = $this->rmFile($files[$i], $path.'/', $folder_id);
                    if(count($fsize) != 2) continue;
                    $size = $fsize[0];
                    if($fsize[1] === false) {
                        $total_size += $size;
                        // It's not necessary here to update folder size because when the file is not completed, the folder size wasn't updated
                    }
                    else {
                        $total_size += $size;
                        $tab_folders[$folder_id][1] += $size;
                    }
                }

                // Decrement storage counter
                $this->_modelStorage = new m\Storage($this->_uid);
                if($this->_modelStorage->decrementSizeStored($total_size)) {
					$_SESSION['size_stored'] -= $total_size;
				}

                // Update folders size
                foreach($tab_folders as $key => $val) {
                    $this->_modelFolders->updateFoldersSize($key, -1*$val[1]);
				}
            }
        }
        echo 'done';
    }

	private function rmFolders($folders) {
        $total_size = 0;
        $tab_folders = []; // key : folder id, value : updated size
        $path = '';
// ids : parent folder id, folders : folder id
        if(isset($_POST['folders']) && isset($_POST['ids'])) {
            $folders = explode("|", urldecode($_POST['folders']));
            $ids = explode("|", urldecode($_POST['ids']));

            $nbFolders = count($folders);
            $nbIds = count($ids);

            if($nbFolders === $nbIds && $nbFolders > 0) {
                for($i = 0; $i < $nbFolders; $i++) {
                    $folder_id = $ids[$i];
                    if(!array_key_exists($folder_id, $tab_folders)) $tab_folders[$folder_id] = 0;
                    $size = $this->rmFolder($folders[$i]);
                    $total_size += $size;
                    $tab_folders[$folder_id] += $size;
                }

                // Decrement storage counter
                $this->_modelStorage = new m\Storage($this->_uid);
                if($this->_modelStorage->decrementSizeStored($total_size)) {
					$_SESSION['size_stored'] -= $total_size;
				}

                // Update folders size
                foreach($tab_folders as $key => $val) {
                    $this->_modelFolders->updateFoldersSize($key, -1*$val);
				}
            }
        }
        echo 'done';
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
                return [$fsize, $completed];
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

    private function rmFolder($id) {
        if(!is_pos_digit($id)) {
			return 0;
		}
        $size = $this->_modelFolders->getSize($id);
        if($size === false) {
			return 0;
		}
        // Delete folder, files, subfolders and also files in db
        $this->rmRdir($id);
        // Delete folders and subfolders in db and update parents folder size
        $this->_modelFolders->delete($id);
        return $size;
    }
}
