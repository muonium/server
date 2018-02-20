<?php
namespace application\controllers;
use \library\MVC as l;
use \application\models as m;

class User extends l\Controller {

    private $_modelFiles;
    private $_modelFolders;
    private $_modelStorage;

    function __construct() {
        parent::__construct([
            'mustBeLogged' => true,
            'mustBeValidated' => true
        ]);
    }

    function DefaultAction() {
        require_once(DIR_VIEW."User.php");
    }

	function writeChunkAction() {
		// SESSION upload contains path for a folder id and its files uploaded during this session but only which doesn't exist or not complete

		function write($fpath, $data) {
			$data_length = strlen($data);
			if($_SESSION['size_stored']+$data_length > $_SESSION['user_quota']) {
				echo 'error';
			} else {
				$f = @fopen($fpath, "a");
				if($f === false || fwrite($f, $data) === false) {
					echo 'error';
				} else {
					$storage = new m\Storage($_SESSION['id']);
					if($storage->incrementSizeStored($data_length)) {
						$_SESSION['size_stored'] += $data_length;
					}
					echo 'ok';
				}
				fclose($f);
			}
		}

		if(isset($_POST['data']) && isset($_POST['filename']) && isset($_POST['folder_id'])) {
		    // Chunk sent by Ajax
		    $data = $_POST['data'];
			if($data !== 'EOF') $data .= "\r\n";
		    $filename = $this->parseFilename($_POST['filename']);
			$folder_id = $_POST['folder_id'];

			if($filename !== false && is_numeric($folder_id)) {
				if(isset($_SESSION['upload'][$folder_id]['files'][$filename]) && isset($_SESSION['upload'][$folder_id]['path'])) {
					// We have already write into this file in this session
					if($_SESSION['upload'][$folder_id]['files'][$filename] == 0 || $_SESSION['upload'][$folder_id]['files'][$filename] == 1) {
						$filepath = NOVA.'/'.$_SESSION['id'].'/'.$_SESSION['upload'][$folder_id]['path'].$filename;
						write($filepath, $data);
					}
				}
				else {
					// Write into a new file (which exists or not)
					$path = $this->getUploadFolderPath($folder_id);
					if($path === false) {
						echo 'error'; exit;
					}

					$filepath = NOVA.'/'.$_SESSION['id'].'/'.$path.$filename;
					$filestatus = $this->fileStatus($filepath);
					$_SESSION['upload'][$folder_id]['files'][$filename] = $filestatus;
					$_SESSION['upload'][$folder_id]['path'] = $path;

                    if($filestatus == 2) { // The file exists, exit
                        return;
                    }
					else {
						// The file doesn't exist or is not complete
						// Insert into files table if this file is not present
						$this->_modelFiles = new m\Files($_SESSION['id']);

						if(!($this->_modelFiles->exists($filename, $folder_id))) {
							$this->_modelFiles->name = $filename;
							$this->_modelFiles->size = -1;
							$this->_modelFiles->last_modification = time();
							$this->_modelFiles->addNewFile($folder_id);
						}

						write($filepath, $data);
					}
				}

				// End of file
				if($data === 'EOF' && isset($_SESSION['upload'][$folder_id]['files'][$filename]) && isset($_SESSION['upload'][$folder_id]['path'])) {
					// Update files table and folders size
					if(!isset($this->_modelFiles)) {
						$this->_modelFiles = new m\Files($_SESSION['id']);
					}
					if(!isset($this->_modelFolders)) {
						$this->_modelFolders = new m\Folders($_SESSION['id']);
					}

					$this->_modelFiles->name = $filename;
					$this->_modelFiles->size = filesize(NOVA.'/'.$_SESSION['id'].'/'.$_SESSION['upload'][$folder_id]['path'].$filename);
					$this->_modelFiles->last_modification = time();

					if($this->_modelFiles->exists($filename, $folder_id)) {
						$this->_modelFiles->updateFile($folder_id, false);
					} else {
						$this->_modelFiles->addNewFile($folder_id, false);
					}

					$this->_modelFolders->updateFoldersSize($folder_id, $this->_modelFiles->size);

					// Remove the file from SESSION upload because the status is now complete
					unset($_SESSION['upload'][$folder_id]['files'][$filename]);
				}
			}
		}
	}

	function getChunkAction() {
		if(isset($_POST['filename']) && isset($_POST['line']) && isset($_POST['folder_id'])) {
			// Get a chunk with Ajax
		    $line = $_POST['line'];
		    $filename = $this->parseFilename($_POST['filename']);
			$folder_id = $_POST['folder_id'];

			if($filename !== false && is_numeric($folder_id)) {
				$path = $this->getUploadFolderPath($folder_id);
				if($path === false) {
					echo 'error';
					exit;
				}

				$filepath = NOVA.'/'.$_SESSION['id'].'/'.$path.$filename;
				$file = new \SplFileObject($filepath, 'r');
			    $file->seek($line);

			    echo str_replace("\r\n", "", $file->current());
			}
		}
	}

	function getNbChunksAction() {
		if(isset($_POST['filename']) && isset($_POST['folder_id'])) {
		    // Get number of chunks with Ajax
		    $filename = $this->parseFilename($_POST['filename']);
			$folder_id = $_POST['folder_id'];

			if($filename !== false && is_numeric($folder_id)) {
				$path = $this->getUploadFolderPath($folder_id);
				if($path === false) {
					echo '0';
					exit;
				}

				$filepath = NOVA.'/'.$_SESSION['id'].'/'.$path.$filename;
			    if(file_exists($filepath)) {
			        $file = new \SplFileObject($filepath, 'r');
			        $file->seek(PHP_INT_MAX);

					if($file->current() === "EOF") { // A line with "EOF" at the end of the file when the file is complete
						echo $file->key()-1;
					} else {
						echo $file->key();
					}
				}
				else {
					echo '0';
				}
			}
			else {
				echo '0';
			}
		}
	}

	function shareFileAction() {
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

	function unshareFileAction() {
		if(isset($_POST['id']) && is_numeric($_POST['id'])) {
			$this->_modelFiles = new m\Files($_SESSION['id']);
			if($this->_modelFiles->setDK(intval($_POST['id']), null)) {
				echo 'ok';
                exit;
			}
		}
		echo 'err';
	}

	function getFileStatusAction() {
        // Return a message/code according to file status
		// Client side : If the file exists, ask the user if he wants to replace it
		// Also check the quota
		if(isset($_POST['filesize']) && isset($_POST['filename']) && isset($_POST['folder_id'])) {
			// size_stored_tmp includes files currently uploading (new session variable because we can't trust a value sent by the client)
			// Used only to compare, if user sent a fake value, it will start uploading process but it will stop in the first chunk because we update size_stored for every chunk
			if(empty($_SESSION['size_stored_tmp'])) {
				$_SESSION['size_stored_tmp'] = $_SESSION['size_stored'];
			}

			$filename = $this->parseFilename($_POST['filename']);
			$folder_id = $_POST['folder_id'];
			$filesize = $_POST['filesize'];

			if($filename !== false && is_numeric($folder_id) && is_numeric($filesize)) {
				if($_SESSION['size_stored_tmp']+$filesize > $_SESSION['user_quota']) {
					echo 'quota';
					exit;
				}
				$_SESSION['size_stored_tmp'] += $filesize;

				$path = $this->getUploadFolderPath($folder_id);
				if($path === false) {
					echo '0';
					exit;
				}

				$filepath = NOVA.'/'.$_SESSION['id'].'/'.$path.$filename;
				echo $this->filestatus($filepath);
			} else {
				echo 'err';
			}
		} else {
			echo 'err';
		}
	}

	function fileStatus($f) {
		// Returns 0 when the file doesn't exist, 1 when it exists and not complete, 2 when it exists and is complete
		if(file_exists($f)) {
		    $file = new \SplFileObject($f, 'r');
		    $file->seek(PHP_INT_MAX);
            $file->seek($file->key()); // Point to the last line

			if($file->current() === "EOF") { // A line with "EOF" at the end of the file when the file is complete
				return 2;
			}
			return '1@'.$file->key(); // Returns 1 (not complete) + last line number
		}
		return 0;
	}

    function AddFolderAction() {
        $this->getFolderVars();
        if(!empty($_POST['folder'])) {
            $folder = urldecode($_POST['folder']);
            $folder = $this->parseFilename($folder);
            if(strlen($folder) > 64) { // max length 64 chars
                $folder = substr($folder, 0, 64);
			}

            if(is_dir(NOVA.'/'.$_SESSION['id'].'/'.$this->_path) && !is_dir(NOVA.'/'.$_SESSION['id'].'/'.$this->_path.$folder)) {
                $this->_modelFolders->name = $folder;
                $this->_modelFolders->parent = $this->_folderId;
                $this->_modelFolders->path = $this->_path;
                $this->_modelFolders->addFolder();
                echo $this->_modelFolders->getLastInsertedId();
                mkdir(NOVA.'/'.$_SESSION['id'].'/'.$this->_path.$folder, 0770);
                return;
            }
        }
        echo 'error';
    }

    function getTree() {
        $i = 0;
        $this->_modelFiles = new m\Files($_SESSION['id']);

        if(empty($this->_modelFolders)) {
            $this->_modelFolders = new m\Folders($_SESSION['id']);
        }

        $this->_modelStorage = new m\Storage($_SESSION['id']);
        $quota = $this->_modelStorage->getUserQuota();
        $stored = $this->_modelStorage->getSizeStored();

		if($quota !== false && $stored !== false) {
			$_SESSION['size_stored'] = $stored;
			$_SESSION['user_quota'] = $quota;
		}

		$path = $this->_modelFolders->getFullPath($this->_folderId);
		$path_d = explode('/', $path);
		echo '<h1 class="inline" title="'.$path.'">'.($path == '' ? self::$txt->Global->home : end($path_d)).'</h1>';
        // Link to parent folder
		if($this->_folderId != 0) {
            $parent = $this->_modelFolders->getParent($this->_folderId);
            echo '<a id="parent-'.$parent.'" onclick="Folders.open('.$parent.')"><i class="fa fa-caret-up" aria-hidden="true"></i></a>';
        }

		$pct = round($stored/$quota*100, 2);
        echo '
			<div class="quota">
				<div class="progress_bar">
					<div class="used" style="width:'.$pct.'%"></div>
				</div>
        	'.str_replace(['[used]', '[total]'], ['<strong>'.showSize($stored).'</strong>', '<strong>'.showSize($quota).'</strong>'], self::$txt->User->quota_of).' - '.$pct.'%
			</div>
		';
        echo '
			<table id="tree">
				<tr id="tree_head">
					<th width="44px"><input type="checkbox" id="sel_all"><label for="sel_all"></label></th>
					<th></th>
					<th>Name</th>
					<th>Size</th>
					<th>Uploaded</th>
					<th>Options</th>
				</tr>
		';

        if($subdirs = $this->_modelFolders->getChildren($this->_folderId, $this->trash)) {
            foreach($subdirs as $subdir) {
                $elementnum = count(glob(NOVA.'/'.$_SESSION['id'].'/'.$subdir['path'].$subdir['name']."/*"));
                $subdir['name'] = $this->parseFilename($subdir['name']);

                echo '
				<tr class="folder" id="d'.$subdir['id'].'" name="'.htmlentities($subdir['name']).'"
	                title="'.showSize($subdir['size']).'"
	                data-folder="'.htmlentities($subdir['parent']).'"
	                data-path="'.htmlentities($subdir['path']).'"
	                data-title="'.htmlentities($subdir['name']).'"
					onclick="Selection.addFolder(event, \'d'.$subdir['id'].'\')"
					ondblclick="Folders.open('.$subdir['id'].')"
					draggable="true"
				>
					<td><input type="checkbox" id="sel_d'.$subdir['id'].'"><label for="sel_d'.$subdir['id'].'"></label></td>
					<td><img src="'.IMG.'desktop/extensions/folder.svg" class="icon"></td>
					<td>
						<strong>'.htmlentities($subdir['name']).'</strong>
						['.$elementnum.' '.($elementnum > 1 ? self::$txt->User->elements : self::$txt->User->element).']
					</td>
					<td></td>
					<td></td>
					<td><a href="#" class="btn btn-actions"></a></td>
				</tr>
				';
            }
        }
		echo '<tr class="break"></tr>';
        if($files = $this->_modelFiles->getFiles($this->_folderId, $this->trash)) {
            foreach($files as $file) {
				$is_shared = ($file['dk'] === null || strlen($file['dk']) === 0) ? 0 : 1;
                $fpath = $path;
                $file['name'] = $this->parseFilename($file['name']);
                if(array_key_exists('path', $file) && array_key_exists('dname', $file)) {
                    $fpath = $file['path'].$file['dname'];
                }

                if($file['size'] < 0) {
                    $filesize = '['.self::$txt->User->notCompleted.'] '.showSize(@filesize(NOVA.'/'.$_SESSION['id'].'/'.$fpath.'/'.$file['name']));
                } else {
                    $filesize = showSize($file['size']);
                }
				$lastmod = date(self::$txt->Dates->date.' '.self::$txt->Dates->time, $file['last_modification']);

                echo '
				<tr class="file" id="f'.$file['id'].'" '.($file['size'] < 0 ? 'style="color:red" ' : '').'
                	title="'.$filesize.'&#10;'.self::$txt->User->lastmod.': '.$lastmod.'"
	                data-folder="'.htmlentities($file['folder_id']).'"
	                data-path="'.htmlentities($fpath).'"
	                data-title="'.htmlentities($file['name']).'"
					data-shared="'.$is_shared.'"
					data-url="'.URL_APP.'/dl/?'.setURL($file['id']).'"
					onclick="Selection.addFile(event, \'f'.$file['id'].'\')"
					ondblclick="Selection.dl(\'f'.$file['id'].'\')"
					draggable="true"
				>
					<td><input type="checkbox" id="sel_f'.$file['id'].'"><label for="sel_f'.$file['id'].'"></label></td>
					<td></td>
					<td><strong>'.htmlentities($file['name']).'</strong></td>
					<td>'.$filesize.'</td>
					<td>'.$lastmod.'</td>
					<td><a href="#" class="btn btn-actions"></a></td>
				</tr>
				';
            }
        }

        echo '</table>';
    }

    function ChangePathAction() {
        if(!isset($_POST['folder_id'])) {
            $folder_id = 0;
        } elseif(!is_numeric($_POST['folder_id'])) {
            return false;
        } else {
            $folder_id = urldecode($_POST['folder_id']);
		}

        $this->trash = empty($_POST['trash']) ? 0 : 1;

        if($folder_id == 0) {
            // root
            $this->_path = '';
            $this->_folderId = 0;
            $this->getTree();
        }
        else {
            $this->_modelFolders = new m\Folders($_SESSION['id']);

            $path = $this->_modelFolders->getPath($folder_id);
            if($path === false) return false;
            $path .= $this->_modelFolders->getFoldername($folder_id);

            if(is_dir(NOVA.'/'.$_SESSION['id'].'/'.$path)) {
                $this->_path = $path;
                $this->_folderId = $folder_id;
                $this->getTree();
            }
        }
    }

    function FavoritesAction() {
        if(isset($_POST['id']) && is_numeric($_POST['id'])) {
            $id = $_POST['id'];
            $this->_modelFiles = new m\Files($_SESSION['id']);
            $this->_modelFiles->setFavorite($id);
        }
    }

    function rmFile($id, $path, $folder_id) {
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

    function RmFilesAction() {
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

    function rmRdir($id) {
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

    function rmFolder($id) {
        if(!is_numeric($id)) return 0;

        $size = $this->_modelFolders->getSize($id);
        if($size === false) return 0;

        // Delete folder, files, subfolders and also files in db
        $this->rmRdir($id);

        // Delete folders and subfolders in db and update parents folder size
        $this->_modelFolders->delete($id);
        return $size;
    }

    function RmFoldersAction() {
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

    function RenameAction() {
        $this->_modelFiles = new m\Files($_SESSION['id']);
        $this->_modelFolders = new m\Folders($_SESSION['id']);

        if(isset($_POST['old']) && isset($_POST['new']) && isset($_POST['folder_id'])) {
            $folder_id = urldecode($_POST['folder_id']);
            if(!is_numeric($folder_id)) return false;
            $old = urldecode($_POST['old']);
            $new = urldecode($_POST['new']);
            $new = $this->parseFilename($new);

            if($old != $new && !empty($old) && !empty($new)) {
                $path = $this->_modelFolders->getFullPath($folder_id);
                if($path != '') $path .= '/';

                if(is_dir(NOVA.'/'.$_SESSION['id'].'/'.$path.$old) && !is_dir(NOVA.'/'.$_SESSION['id'].'/'.$path.$new)) {
                    if(strlen($new) > 64) { // max folder length 64 chars
                        $new = substr($new, 0, 64);
						if(is_dir(NOVA.'/'.$_SESSION['id'].'/'.$path.$new)) return false;
					}
                    // Rename folder in db
                    $this->_modelFolders->rename($path, $old, $new);
                }
                elseif(file_exists(NOVA.'/'.$_SESSION['id'].'/'.$path.$old) && !file_exists(NOVA.'/'.$_SESSION['id'].'/'.$path.$new)) {
                    if(strlen($new) > 128) { // max file length 128 chars
                        $new = substr($new, 0, 128);
						if(file_exists(NOVA.'/'.$_SESSION['id'].'/'.$path.$new)) return false;
					}

                    // Rename file in db
					if(isset($_SESSION['upload'][$folder_id]['files'][$old])) {
						unset($_SESSION['upload'][$folder_id]['files'][$old]);
					}
                    $this->_modelFiles->rename($folder_id, $old, $new);
                }
                else {
                    return false;
                }

                rename(NOVA.'/'.$_SESSION['id'].'/'.$path.$old, NOVA.'/'.$_SESSION['id'].'/'.$path.$new);
				echo 'ok';
            }
        }
    }
}
