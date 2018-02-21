<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \application\controllers as c;
use \application\models as m;

class files extends c\FileManager {

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

	public function writeChunkAction() {
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

	public function getChunkAction() {
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

	public function getNbChunksAction() {
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

	public function getFileStatusAction() {
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

	private function fileStatus($f) {
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

	public function FavoritesAction() {
		if(isset($_POST['id']) && is_numeric($_POST['id'])) {
			$id = $_POST['id'];
			$this->_modelFiles = new m\Files($_SESSION['id']);
			$this->_modelFiles->setFavorite($id);
		}
	}

	public function RenameAction() {
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
