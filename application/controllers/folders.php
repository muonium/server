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

        if(isset($data->name)) {
			$this->getFolderVars();
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
        } else {
			$resp['message'] = 'emptyField';
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
    }

	public function ChangePathAction() {
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

	public function DefaultAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$resp['token'] = $this->_token;
	}

	private function getTree() {
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
}
