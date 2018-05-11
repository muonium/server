<?php
namespace application\models;
use \library\MVC as l;

class Folders extends l\Model {
    /*
    CREATE TABLE IF NOT EXISTS folders (
    	folder_id uuid,
    	owner_id uuid,
    	parent uuid,
    	updated_at timestamp,
    	name text,
    	size bigint,
    	favorite boolean,
    	trash boolean,
    	path text,
    	PRIMARY KEY (folder_id, owner_id)
    );
    */

    protected $id = null;
    protected $owner_id = null;
    protected $name;
    protected $size = 0;
    protected $parent = 0;
    protected $trash;
    protected $path;

	function __construct($owner_id = null) {
		parent::__construct();
		// owner_id (uuid) can be passed at init
		$this->owner_id = $owner_id;
	}

    function getId($path) {
		// path (string) - Returns folder id from path
		if($this->owner_id === null) return false;
        $req = self::$_sql->prepare("SELECT id FROM folders WHERE owner_id = ? AND `path` = ?");
        $req->execute([$this->owner_id, $path]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['id'];
    }

    function getFoldername($id, $folder_id = null) {
		// id (int) - Returns folder name from its id, folder_id (int) - optionnal - folder_id to check if this folder is really from it and not from trash
		if($this->owner_id === null) return false;
		if(is_pos_digit($folder_id)) {
			$req = self::$_sql->prepare("SELECT name FROM folders WHERE owner_id = ? AND id = ? AND parent = ? AND trash = 0");
        	$req->execute([$this->owner_id, $id, $folder_id]);
		} else {
        	$req = self::$_sql->prepare("SELECT name FROM folders WHERE owner_id = ? AND id = ?");
        	$req->execute([$this->owner_id, $id]);
		}
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['name'];
    }

    function getPath($id) {
		// id (int) - Returns folder path from its id (without folder name)
		if($this->owner_id === null) return false;
        $req = self::$_sql->prepare("SELECT `path` FROM folders WHERE owner_id = ? AND id = ?");
        $req->execute([$this->owner_id, $id]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['path'];
    }

    function getFullPath($id) {
        // id (int) - Returns folder path with folder name included
		if($this->owner_id === null) return false;
		$id = intval($id);
		if($id === 0) return '';
        $req = self::$_sql->prepare("SELECT `path`, name FROM folders WHERE owner_id = ? AND id = ?");
        $req->execute([$this->owner_id, $id]);
		if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['path'].$res['name'];
    }

    function getParent($id) {
		// id (int) - Returns parent id from folder id
		if($this->owner_id === null) return false;
        $req = self::$_sql->prepare("SELECT parent FROM folders WHERE owner_id = ? AND id = ?");
        $req->execute([$this->owner_id, $id]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['parent'];
    }

    // Not used for now, get number of subfolders
	function getSubfoldernum($id) {
		if($this->owner_id === null) return false;
        $req = self::$_sql->prepare("SELECT COUNT(id) AS nb FROM folders WHERE owner_id = ? AND parent = ?");
        $req->execute([$this->owner_id, $id]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['nb'];
    }

    function getChildren($id, $trash = '') {
		// id (int) - Folder id, trash - Get from anywhere/trash/outside trash
		if($this->owner_id === null) return false;
        if($trash === '' || $trash === 'all') {
            $req = self::$_sql->prepare("SELECT id, name, size, parent, `path` FROM folders WHERE owner_id = ? AND parent = ? ORDER BY name ASC");
            $req->execute([$this->owner_id, $id]);
        }
        elseif($trash == 0 || ($trash == 1 && $id !== 0)) {
            $req = self::$_sql->prepare("SELECT id, name, size, parent, `path` FROM folders WHERE owner_id = ? AND parent = ? AND trash = 0 ORDER BY name ASC");
            $req->execute([$this->owner_id, $id]);
        }
        else { // trash == 1 && $id == 0
            $req = self::$_sql->prepare("SELECT id, name, size, parent, `path` FROM folders WHERE owner_id = ? AND trash = 1 ORDER BY name ASC");
            $req->execute([$this->owner_id]);
        }
        if($req->rowCount() === 0) return false;
        return $req->fetchAll(\PDO::FETCH_ASSOC);
    }

    function getTrash($id) {
		// id (int) - Folder id, Get trash state for selected folder
		if($this->owner_id === null) return false;
        $req = self::$_sql->prepare("SELECT trash FROM folders WHERE owner_id = ? AND id = ?");
        $req->execute([$this->owner_id, $id]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['trash'];
    }

    function getSize($id) {
		// id (int) - Get folder size
		if($this->owner_id === null) return false;
        $req = self::$_sql->prepare("SELECT size FROM folders WHERE owner_id = ? AND id = ?");
        $req->execute([$this->owner_id, $id]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['size'];
    }

    function updateTrash($id, $trash) {
		if($this->owner_id === null) return false;
        $req = self::$_sql->prepare("UPDATE folders SET trash = ? WHERE owner_id = ? AND id = ?");
        return $req->execute([$trash, $this->owner_id, $id]);
    }

    // Update the size of the folder with an increment of $size
    function updateFolderSize($id, $size) {
		if($this->owner_id === null) return false;
        $req = self::$_sql->prepare("UPDATE folders SET size = size + ? WHERE owner_id = ? AND id = ?");
        return $req->execute([$size, $this->owner_id, $id]);
    }

    // Update the size of folder and each parent folder until root with an increment of $size
    function updateFoldersSize($id, $size) {
        do {
            if(!($this->updateFolderSize($id, $size))) break;
            $id = $this->getParent($id);
        } while($id != 0 && $id !== false);
    }

    // Update path of a folder and its subfolders
    // Maybe better to use UPDATE with LIKE ?
    function updatePath($id, $path, $foldername) {
		if($this->owner_id === null) return false;
        $subdirs = $this->getChildren($id);
        if($subdirs !== false) {
            foreach($subdirs as $subdir) {
                $this->updatePath($subdir['id'], $path.$foldername.'/', $subdir['name']);
			}
        }
        $req = self::$_sql->prepare("UPDATE folders SET `path` = ? WHERE owner_id = ? AND id = ?");
        $req->execute([$path, $this->owner_id, $id]);
    }

    // Experimental method, rename folder which has specified path
    function rename($path, $old, $new) {
		if($this->owner_id === null) return false;
        $req = self::$_sql->prepare("UPDATE folders SET name = ? WHERE owner_id = ? AND name = ? AND `path` = ?");
        $req->execute([$new, $this->owner_id, $old, $path]);
		// Children
        $req = self::$_sql->prepare("UPDATE folders SET `path` = CONCAT(?, SUBSTR(`path`, ?)) WHERE owner_id = ? AND `path` LIKE ?");
        $req->execute([$path.$new, strlen($path.$old)+1, $this->owner_id, $path.$old.'%']);
    }

    function updateParent($id, $parent) {
		// Update folder parent and also folder name
		if($this->owner_id === null) return false;
        $req = self::$_sql->prepare("UPDATE folders SET parent = ?, name = ? WHERE owner_id = ? AND id = ?");
        return $req->execute([$parent, $this->name, $this->owner_id, $id]);
    }

    function addFolder() {
		if($this->owner_id === null) return false;
		if(isset($this->name) && isset($this->size) && isset($this->parent) && isset($this->path)) {
			return $this->insert('folders', [
				'id' => null,
				'owner_id' => intval($this->owner_id),
				'name' => $this->name,
				'size' => intval($this->size),
				'parent' => intval($this->parent),
				'trash' => 0,
				'path' => $this->path
			]);
		}
		return false;
    }

    function delete($id) {
        // Delete folder with id $id and its children in database
		if($this->owner_id === null || $id == 0) return false;
        $size = $this->getSize($id);
        if($size === false) return false;
        $path = $this->getPath($id);
        if($path === false) return false;
        if(!($name = $this->getFoldername($id))) return false;
        $path .= $name;
        //$this->updateFoldersSize($id, -1*$size);
        $req = self::$_sql->prepare("DELETE FROM folders WHERE `path` LIKE ? AND owner_id = ?");
        $req->execute([$path.'%', $this->owner_id]);
        $req2 = self::$_sql->prepare("DELETE FROM folders WHERE id = ? AND owner_id = ?");
        $req2->execute([$id, $this->owner_id]);
        return $size;
    }

	function deleteFoldersfinal() {
		if($this->owner_id === null) return false;
		$req = self::$_sql->prepare("DELETE FROM folders WHERE owner_id = ?");
		return $req->execute([$this->owner_id]);
	}
}
