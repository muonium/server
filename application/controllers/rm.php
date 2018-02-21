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
	}

	private function rmFile($id, $path, $folder_id) {
		// $folder_id is used only to delete session var
        if(is_numeric($id)) {
            $filename = $this->_modelFiles->getFilename($id);
            if($filename !== false) {
                if(file_exists(NOVA.'/'.$_SESSION['id'].'/'.$path.$filename)) {
					if(isset($_SESSION['upload'][$folder_id]['files'][$filename])) {
						unset($_SESSION['upload'][$folder_id]['files'][$filename]);
                    }
                    // deleteFile() returns file size
                    $fsize = $this->_modelFiles->deleteFile($id);
                    $completed = true;
                    if($fsize == -1) {
                        $completed = false;
                        $fsize = @filesize(NOVA.'/'.$_SESSION['id'].'/'.$path.$filename);
                    }
                    unlink(NOVA.'/'.$_SESSION['id'].'/'.$path.$filename);
                    return [$fsize, $completed];
                }
            }
        }
        return [0, true];
    }

    public function RmFilesAction() {
        $this->_modelFolders = new m\Folders($_SESSION['id']);
        $this->_modelFiles = new m\Files($_SESSION['id']);

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
                $this->_modelStorage = new m\Storage($_SESSION['id']);
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

    private function rmRdir($id) {
        // This is a recursive method
        if(is_numeric($id)) {
            $path = $this->_modelFolders->getFullPath($id);
            if($path !== false) {
                $full_path = NOVA.'/'.$_SESSION['id'].'/'.$path;
                if(is_dir($full_path)) {
					if(isset($_SESSION['upload'][$id])) unset($_SESSION['upload'][$id]);
                    // Delete subfolders
                    if($subdirs = $this->_modelFolders->getChildren($id)) {
                        foreach($subdirs as $subdir) {
                            $this->rmRdir($subdir['id']);
						}
                    }

                    // Delete files
                    foreach(glob("{$full_path}/*") as $file) {
                        if(is_file($file)) unlink($file);
                    }

                    // Delete files in db
                    $this->_modelFiles->deleteFiles($id);

                    // Delete folder
                    rmdir($full_path);
                }
            }
        }
    }

    private function rmFolder($id) {
        if(!is_numeric($id)) return 0;

        $size = $this->_modelFolders->getSize($id);
        if($size === false) return 0;

        // Delete folder, files, subfolders and also files in db
        $this->rmRdir($id);

        // Delete folders and subfolders in db and update parents folder size
        $this->_modelFolders->delete($id);
        return $size;
    }

    public function RmFoldersAction() {
        $this->_modelFolders = new m\Folders($_SESSION['id']);
        $this->_modelFiles = new m\Files($_SESSION['id']);

        $total_size = 0;
        $tab_folders = []; // key : folder id, value : updated size
        $path = '';

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
                $this->_modelStorage = new m\Storage($_SESSION['id']);
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
}
