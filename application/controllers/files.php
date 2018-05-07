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

	public function writeAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$resp['token'] = $this->_token;

		// Redis :folder contains path for a folder id and its files uploaded during this session but only which doesn't exist or not complete
		function write($fpath, $data, $resp, $redis, $uid, $jti) {
			$data_length = strlen($data);
			$user_quota = $redis->get('token:'.$jti.':user_quota');
			$size_stored = $redis->get('token:'.$jti.':size_stored');
			if($user_quota === null || $size_stored === null || intval($size_stored)+$data_length > intval($user_quota)) {
				return $resp;
			}
			$f = @fopen($fpath, 'a');
			if($f === false || fwrite($f, $data) === false) {
				return $resp;
			}
			$storage = new m\Storage($uid);
			if($storage->incrementSizeStored($data_length)) {
				$size_stored = intval($size_stored)+$data_length;
				$redis->set('token:'.$jti.':size_stored', $size_stored);
			}
			fclose($f);
			$resp['code'] = 201;
			$resp['status'] = 'success';
			return $resp;
		}

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
		elseif(isset($data->data) && isset($data->filename) && isset($data->folder_id) && is_pos_digit($data->folder_id)) {
			$cnt = $data->data;
			if($cnt !== 'EOF') $cnt .= "\r\n";
		    $filename = $this->parseFilename($data->filename);
			$folder_id = intval($data->folder_id);

			if($filename !== false) {
				$fp = $this->redis->get('token:'.$this->_jti.':folder:'.$folder_id);
				$fs = $this->redis->get('token:'.$this->_jti.':folder:'.$folder_id.':'.$filename);

				if($fs !== null) {
					// We have already write into this file in this session
                    $fp = $fp === null ? '' : $fp;
					if($fs == 0 || $fs == 1) {
						$filepath = NOVA.'/'.$this->_uid.'/'.$fp.$filename;
						$resp = write($filepath, $cnt, $resp, $this->redis, $this->_uid, $this->_jti);
					}
				}
				else {
					// Write into a new file (which exists or not)
					$path = $this->getUploadFolderPath(intval($folder_id));
					if($path !== false) {
						$filepath = NOVA.'/'.$this->_uid.'/'.$path.$filename;
						$filestatus = $this->fileStatus($filepath);
                        if($path !== '') {
						    $this->redis->set('token:'.$this->_jti.':folder:'.$folder_id, $path);
                        }
						$this->redis->set('token:'.$this->_jti.':folder:'.$folder_id.':'.$filename, $filestatus);

	                    if($filestatus !== 2) {
							// The file doesn't exist or is not complete
							// Insert into files table if this file is not present
							$this->_modelFiles = new m\Files($this->_uid);
							if(!($this->_modelFiles->exists($filename, $folder_id))) {
								$this->_modelFiles->name = $filename;
								$this->_modelFiles->size = -1;
								$this->_modelFiles->last_modification = time();
								$this->_modelFiles->addNewFile($folder_id);
							}
							$resp = write($filepath, $cnt, $resp, $this->redis, $this->_uid, $this->_jti);
						}
					}
                }

				// End of file
				$fp = $this->redis->get('token:'.$this->_jti.':folder:'.$folder_id);
				$fs = $this->redis->get('token:'.$this->_jti.':folder:'.$folder_id.':'.$filename);

				if($cnt === 'EOF' && $fs !== null) {
                    $fp = $fp === null ? '' : $fp;
					// Update files table and folders size
					if(!isset($this->_modelFiles)) {
						$this->_modelFiles = new m\Files($this->_uid);
					}
					if(!isset($this->_modelFolders)) {
						$this->_modelFolders = new m\Folders($this->_uid);
					}
					$this->_modelFiles->name = $filename;
					$this->_modelFiles->size = filesize(NOVA.'/'.$this->_uid.'/'.$fp.$filename);
					$this->_modelFiles->last_modification = time();

					if($this->_modelFiles->exists($filename, $folder_id)) {
						$this->_modelFiles->updateFile($folder_id, false);
					} else {
						$this->_modelFiles->addNewFile($folder_id, false);
					}

					$this->_modelFolders->updateFoldersSize($folder_id, $this->_modelFiles->size);
					// Remove the file from Redis because the status is now complete
					$this->redis->del('token:'.$this->_jti.':folder:'.$folder_id.':'.$filename);
                }
            }
		} else {
			$resp['message'] = 'emptyField';
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
	}

	public function readAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$resp['token'] = $this->_token;

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
		elseif(isset($data->filename) && isset($data->line) && is_pos_digit($data->line) && isset($data->folder_id) && is_pos_digit($data->folder_id)) {
		    $filename = $this->parseFilename($data->filename);
			if($filename !== false) {
				$path = $this->getUploadFolderPath(intval($data->folder_id));
				if($path !== false) {
					$filepath = NOVA.'/'.$this->_uid.'/'.$path.$filename;
					if(file_exists($filepath)) {
						$resp['code'] = 200;
						$resp['status'] = 'success';
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
				$resp['message'] = 'notExists';
			}
		} else {
			$resp['message'] = 'emptyField';
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
	}

	public function chunkAction() { // Alias
		$this->readAction();
	}

	public function nbChunksAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$resp['token'] = $this->_token;

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
		elseif(isset($data->filename) && isset($data->folder_id) && is_pos_digit($data->folder_id)) {
			$resp['data'] = 0;
			$filename = $this->parseFilename($data->filename);
			if($filename !== false) {
				$path = $this->getUploadFolderPath(intval($data->folder_id));
				if($path !== false) {
					$filepath = NOVA.'/'.$this->_uid.'/'.$path.$filename;
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

	public function statusAction() {
        // Return a message/code according to file status
		// Client side : If the file exists, ask the user if he wants to replace it
		// Also check the quota
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$resp['token'] = $this->_token;

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		}
		elseif(isset($data->filesize) && isset($data->filename) && isset($data->folder_id) && is_pos_digit($data->folder_id) && is_digit($data->filesize)) {
			// size_stored_tmp includes files currently uploading (new session variable because we can't trust a value sent by the client)
			// Used only to compare, if user sent a fake value, it will start uploading process but it will stop in the first chunk because we update size_stored for every chunk
			$user_quota = $this->redis->get('token:'.$this->_jti.':user_quota');
			$size_stored = $this->redis->get('token:'.$this->_jti.':size_stored');
			$size_stored_tmp = $this->redis->get('token:'.$this->_jti.':size_stored_tmp');
			$filename = $this->parseFilename($data->filename);
			if($size_stored !== null && $user_quota !== null && $filename !== false) {
				if($size_stored_tmp === null) {
					$size_stored_tmp = intval($size_stored);
					$this->redis->set('token:'.$this->_jti.':size_stored_tmp', $size_stored_tmp);
				}

				if(intval($size_stored_tmp)+intval($data->filesize) <= intval($user_quota)) {
					$resp['code'] = 200;
					$resp['status'] = 'success';
					$size_stored_tmp = intval($size_stored_tmp)+intval($data->filesize);
					$this->redis->set('token:'.$this->_jti.':size_stored_tmp', $size_stored_tmp);
					$path = $this->getUploadFolderPath(intval($data->folder_id));
					if($path !== false) {
						$filepath = NOVA.'/'.$this->_uid.'/'.$path.$filename;
						$status = explode('@', $this->filestatus($filepath));
						if(count($status) === 2) {
							$resp['data']['line'] = intval($status[1]);
						}
						$resp['data']['status'] = intval($status[0]);
					} else {
						$resp['data']['status'] = 0;
					}
				} else {
					$resp['message'] = 'quota';
				}
			}
		} else {
			$resp['message'] = 'emptyField';
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
			if(strlen($new) > 128) { // max file length 128 chars
				$new = substr($new, 0, 128);
			}

			if($new !== false && $old !== $new) {
				$path = $this->_modelFolders->getFullPath($data->folder_id);
				if($path !== '') $path .= '/';

				if(file_exists(NOVA.'/'.$this->_uid.'/'.$path.$old) && !file_exists(NOVA.'/'.$this->_uid.'/'.$path.$new)) {
					$resp['code'] = 200;
					$resp['status'] = 'success';
					$this->redis->del('token:'.$this->_token.':folder:'.$data->folder_id.':'.$old);
					$this->_modelFiles->rename($data->folder_id, $old, $new);
					rename(NOVA.'/'.$this->_uid.'/'.$path.$old, NOVA.'/'.$this->_uid.'/'.$path.$new);
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

	/*public function FavoritesAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$resp['token'] = $this->_token;

		if($method !== 'post') {
			$resp['code'] = 405; // Method Not Allowed
		} elseif(isset($data->id) && is_pos_digit($data->id)) {
			$resp['code'] = 200;
			$resp['status'] = 'success';
			$this->_modelFiles = new m\Files($this->_uid);
			$this->_modelFiles->setFavorite($data->id);
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
	}*/

	public function DefaultAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$resp['token'] = $this->_token;

		http_response_code($resp['code']);
		echo json_encode($resp);
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
}
