<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \application\controllers as c;
use \application\models as m;

class mv extends c\FileManager {

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
		} elseif((isset($data->files) && is_array($data->files) && count($data->files) > 0) || (isset($data->folders) && is_array($data->folders) && count($data->folders) > 0)) {
		    $this->getFolderVars();
			$copy = (isset($data->copy) && $data->copy == 1) ? 1 : 0; // 0 => cut, 1 => copy

		    $this->_modelFiles = new m\Files($this->_uid);
		    $this->_modelFiles->folder_id = $this->_folderId;

		    if(!isset($data->old_folder_id)) {
		        $old_folder_id = 0;
		        $old_path = '';
		    } elseif($data->old_folder_id == 0) {
		        $old_folder_id = 0;
		        $old_path = '';
		    } elseif(is_numeric($data->old_folder_id)) {
		        $old_folder_id = intval($data->old_folder_id);
		        $old_path = $this->_modelFolders->getPath($old_folder_id);
		        if($old_path !== false) {
		        	$old_path .= $this->_modelFolders->getFoldername($old_folder_id).'/';
				}
		    }

			if(isset($old_folder_id) && isset($old_path) && is_numeric($old_folder_id) && $old_path !== false) {
				$resp['data']['warning'] = [];
				$this->_modelStorage = new m\Storage($this->_uid);
		        $quota = $this->_modelStorage->getUserQuota();
		        $stored = $this->_modelStorage->getSizeStored();
				if($quota !== false && $stored !== false) {
					$this->redis->set('token:'.$this->_token.':size_stored', $stored);
					$this->redis->set('token:'.$this->_token.':user_quota', $quota);
			        $uploaded = 0;

			        if(is_dir(NOVA.'/'.$this->_uid.'/'.$this->_path) && is_dir(NOVA.'/'.$this->_uid.'/'.$old_path)) {
			            if(isset($data->files) && is_array($data->files) && count($data->files) > 0) {
			                if($copy === 0 && $this->_path != $old_path) {
			                    //
			                    // Cut and paste files
			                    //
			                    foreach($data->files as $file) {
			                        if(is_numeric($file)) {
										$filename = $this->_modelFiles->getFilename($file);
			                            if($filename === false) {
											$resp['data']['warning'][] = 'badFilename';
											continue;
										}
			                            if(file_exists(NOVA.'/'.$this->_uid.'/'.$old_path.$filename)) {
			                                // Files copies support
			                                $dst_filename = $this->checkMultiple(NOVA.'/'.$this->_uid.'/'.$this->_path, $filename, 'file');
			                                if($dst_filename === false) {
												$resp['data']['warning'][] = 'badFilename';
												continue;
											}

											if($key = $this->redis->get('token:'.$this->_token.':folder:'.$old_folder_id.':'.$filename)) {
												$this->redis->del('token:'.$this->_token.':folder:'.$old_folder_id.':'.$filename);
											}
			                                if(rename(NOVA.'/'.$this->_uid.'/'.$old_path.$filename, NOVA.'/'.$this->_uid.'/'.$this->_path.$dst_filename)) {
				                                $this->_modelFiles->id = $file;
				                                $this->_modelFiles->name = $dst_filename;
				                                $uploaded += filesize(NOVA.'/'.$this->_uid.'/'.$this->_path.$dst_filename);
				                                $this->_modelFiles->updateDir();
											} else {
												$resp['data']['warning'][] = 'move';
											}
			                            }
			                        }
			                    }
			                    // Update parent folders size
			                    $this->_modelFolders->updateFoldersSize($old_folder_id, -1*$uploaded);
			                }
			                elseif($copy === 1) {
			                    //
			                    // Copy and paste files
			                    //
			                    foreach($data->files as $file) {
			                        if(is_numeric($file)) {
										$filename = $this->_modelFiles->getFilename($file);
			                            if($filename === false) {
											$resp['data']['warning'][] = 'badFilename';
											continue;
										}
			                            if(file_exists(NOVA.'/'.$this->_uid.'/'.$old_path.$filename)) {
			                                $this->_modelFiles->id = $file;
			                                $this->_modelFiles->size = filesize(NOVA.'/'.$this->_uid.'/'.$old_path.$filename);
			                                if($stored+$this->_modelFiles->size < $quota) {
												// Files copies support
			                                    $dst_filename = $this->checkMultiple(NOVA.'/'.$this->_uid.'/'.$this->_path, $filename, 'file');
			                                    if($dst_filename === false) {
													$resp['data']['warning'][] = 'badFilename';
													continue;
												}

			                                    $stored 	+= $this->_modelFiles->size;
			                                    $uploaded 	+= $this->_modelFiles->size;
			                                    $this->_modelFiles->last_modification = time();
			                                    $this->_modelFiles->name = $dst_filename;

			                                    $oldPath = NOVA.'/'.$this->_uid.'/'.$old_path.$filename;
			                                    $newPath = NOVA.'/'.$this->_uid.'/'.$this->_path.$dst_filename;
												if(copy($oldPath, $newPath)) {
													$this->_modelFiles->addNewFile($this->_folderId, false);
												} else {
													$resp['data']['warning'][] = 'copy';
												}
			                                } else {
												$resp['message'] = 'quota';
											}
			                            }
			                        }
			                    }
			                }
			            } // end files

			            if(isset($data->folders) && is_array($data->folders) && count($data->folders) > 0) {
			                if($copy === 0 && $this->_path != $old_path) {
			                    //
			                    //	Cut and paste folders
			                    //
			                    foreach($data->folders as $folder) {
			                        $foldername = $this->_modelFolders->getFolderName($folder);
			                        if($foldername === false) {
										$resp['data']['warning'][] = 'badFoldername';
										continue;
									}
			                        if(is_dir(NOVA.'/'.$this->_uid.'/'.$old_path.$foldername)) {
			                            $folderSize = $this->_modelFolders->getSize($folder);
			                            $old_parent = $this->_modelFolders->getParent($folder);

			                            // Folder copies support
			                            $dst_foldername = $this->checkMultiple(NOVA.'/'.$this->_uid.'/'.$this->_path, $foldername, 'folder');
			                            if($dst_foldername === false) {
											$resp['data']['warning'][] = 'badFoldername';
											continue;
										}

										if($key = $this->redis->get('token:'.$this->_token.':folder:'.$folder)) {
			                                $this->redis->del('token:'.$this->_token.':folder:'.$folder);
										}

			                            $basePath = NOVA.'/'.$this->_uid.'/';
			                            $oldFolderName = $basePath.$old_path.$foldername.'/';
			                            $newFolderName = $basePath.$this->_path.$dst_foldername.'/';
			                            if(!($oldFolderName == substr($newFolderName, 0, strlen($oldFolderName)))) { //Check if it's not a child, improvable
			                                if(rename($oldFolderName, $newFolderName)) {
			                                    $this->_modelFolders->name = $dst_foldername;
			                                    $this->_modelFolders->updateParent($folder, $this->_folderId);
			                                    $this->_modelFolders->updatePath($folder, $this->_path, $dst_foldername);

			                                    // Update parent folders size
			                                    $this->_modelFolders->updateFoldersSize($old_parent, -1*$folderSize);
			                                    $uploaded += $folderSize;
			                                } else {
			                                    $resp['data']['warning'][] = 'move';
											}
			                            } else {
			                                $resp['data']['warning'][] = 'isAChild';
										}
			                        }
			                    }
			                }
			                elseif($copy === 1) {
			                    //
			                    //	Copy and paste folders
			                    //
			                    foreach($data->folders as $folder) {
			                        $foldername = $this->_modelFolders->getFolderName($folder);
			                        if($foldername === false) {
										$resp['data']['warning'][] = 'badFoldername';
										continue;
									}
			                        if(is_dir(NOVA.'/'.$this->_uid.'/'.$old_path.$foldername)) {
			                            $dst_foldername = $this->checkMultiple(NOVA.'/'.$this->_uid.'/'.$this->_path, $foldername, 'folder');
			                            $basePath = NOVA.'/'.$this->_uid.'/';
			                            $oldFolderName = $basePath.$old_path.$foldername.'/';
			                            $newFolderName = $basePath.$this->_path.$dst_foldername.'/';

			                            if(!($oldFolderName == substr($newFolderName, 0, strlen($oldFolderName)))) { //Improvable
			                                $folderSize = $this->_modelFolders->getSize($folder);
			                                if($stored+$folderSize < $quota) {
			                                    $stored 	+= $folderSize;
			                                    $uploaded 	+= $folderSize;
			                                    //recurse_copy add also new files and subfolders in db
			                                    $this->recurse_copy($folder, $this->_folderId);
			                                } else {
			                                    $resp['message'] = 'quota';
											}
			                            } else {
			                                $resp['data']['warning'][] = 'isAChild';
										}
			                        }
			                    }
			                }
			            } // end folders

			            if($this->_modelStorage->updateSizeStored($stored)) {
							$this->redis->set('token:'.$this->_token.':size_stored', $stored);
						}
			            if($uploaded !== 0) {
							$this->_modelFolders->updateFoldersSize($this->_folderId, $uploaded);
						}
						if($resp['message'] === null) {
							$resp['code'] = 200;
							$resp['status'] = 'success';
							$resp['message'] = 'ok';
						}
			        }
				}
				$resp['data']['warning'] = array_unique($resp['data']['warning']);
			}
		} else {
			$resp['message'] = 'emptyField';
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
	}

	// Check if there are multiple versions of a file or a folder and update the name.
    // Folder, Folder (1), Folder (2)...
    // File.ext, File (1).ext, File (2).ext...
    private function checkMultiple($path, $name, $type) {
        $i = 1;
        if($type === 'folder') {
            while(is_dir($path.$name)) {
                if($i < 2) {
                    $name .= " ($i)";
					if(strlen($name) > 60) {
						$name = substr($name, 0, 60);
					}
                } else {
                    $first_pos = strrpos($name, '(');
					$last_pos = strrpos($name, ')');
					if($first_pos === false || $last_pos === false || $first_pos >= $last_pos) return false;
					if(!is_numeric(substr($name, $first_pos+1, $last_pos-$first_pos-1))) return false;
					$name = substr($name, 0, $first_pos)."($i)";
					$length = strlen($name);
					if($length > 64) { // max folder length 64 chars
						$name = substr($name, 0, ($first_pos-$length+64))."($i)";
					}
                }
                $i++;
            }
        }
        elseif($type === 'file') {
            while(file_exists($path.$name)) {
                if($i < 2) {
                    $name = $this->addSuffixe($name, " ($i)", 128);
                } else {
                    $first_pos = strrpos($name, '(');
                    $last_pos = strrpos($name, ')');
                    if($first_pos === false || $last_pos === false || $first_pos >= $last_pos) return false;
					if(!is_numeric(substr($name, $first_pos+1, $last_pos-$first_pos-1))) return false;
					$old_name = $name;
                    $name = substr($name, 0, $first_pos)."($i)".substr($name, $last_pos+1);
					$length = strlen($name);
					if($length > 128) { // max file length 128 chars
						$name = substr($old_name, 0, ($first_pos-$length+128))."($i)".substr($old_name, $last_pos+1);
					}
                }
                $i++;
            }
        }
        return $name;
    }

    // $src is the folder id of source folder
    // $dst is the folder id of dest folder where $src folder will be pasted
    private function recurse_copy($src, $dst) {
        // This is a recursive method
        // Thank you "gimmicklessgpt at gmail dot com" from php.net for the base code
        // recurse_copy add also new files in db
        if($src == 0 || $src === $dst) return false;
        $src_foldername = $this->_modelFolders->getFoldername($src);
        if($src_foldername === false) return false;
        $size = $this->_modelFolders->getSize($src);
        if($size === false) return false;
        $src_parent_path = $this->_modelFolders->getPath($src);

        $dst_parent_path = ($dst == 0) ? '' : $this->_modelFolders->getPath($dst);
        $dst_parent_name = ($dst == 0) ? '' : $this->_modelFolders->getFoldername($dst).'/';

        // Folder copies support
        $dst_foldername = $this->checkMultiple(NOVA.'/'.$this->_uid.'/'.$dst_parent_path.$dst_parent_name, $src_foldername, 'folder');
        if($dst_foldername === false) return false;

        $this->_modelFolders->name = $dst_foldername;
        $this->_modelFolders->parent = $dst;
        $this->_modelFolders->path = $dst_parent_path.$dst_parent_name;
        $this->_modelFolders->size = $size;
        $this->_modelFolders->addFolder();
        $folder_id = $this->_modelFolders->getLastInsertedId();

        $src_path = $src_parent_path.$src_foldername.'/';
        $dst_path = $this->_modelFolders->path.$dst_foldername;

        @mkdir(NOVA.'/'.$this->_uid.'/'.$dst_path, 0770);

        if($subdirs = $this->_modelFolders->getChildren($src)) {
            foreach($subdirs as $subdir) {
                $this->recurse_copy($subdir['id'], $folder_id);
			}
        }
        if($files = $this->_modelFiles->getFiles($src)) {
            foreach($files as $file) {
                $resultCopy = copy(NOVA.'/'.$this->_uid.'/'.$src_path.$file['name'], NOVA.'/'.$this->_uid.'/'.$dst_path.'/'.$file['name']);
                // Add the new file in db
                if($resultCopy) {
                    $this->_modelFiles->name = $file['name'];
                    $this->_modelFiles->last_modification = time();
                    $this->_modelFiles->size = filesize(NOVA.'/'.$this->_uid.'/'.$dst_path.'/'.$file['name']);
                    $this->_modelFiles->addNewFile($folder_id, false);
                }
            }
        }
    }
}
