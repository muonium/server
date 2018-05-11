<?php
namespace application\models;
use \library\MVC as l;

class Files extends l\Model {
    /*
    CREATE TABLE IF NOT EXISTS files (
    	file_id uuid,
    	owner_id uuid,
    	owner_login text,
    	folder_id uuid,
    	updated_at timestamp,
    	name text,
    	size bigint,
    	favorite boolean,
    	trash boolean,
    	expires timestamp,
    	path text,
    	dk text,
    	lek map<uuid, text>,
    	dk_asym map<uuid, text>,
    	PRIMARY KEY ((file_id, owner_id), updated_at)
    ) WITH CLUSTERING ORDER BY (updated_at DESC);
    */

    protected $id = null;
    protected $owner_id = null;
    protected $folder_id;
    protected $name;
    protected $size;
    protected $last_modification;
    protected $favorite;
    protected $trash;
	protected $dk;

	function __construct($owner_id = null) {
		parent::__construct();
		// owner_id (uuid) can be passed at init
		$this->owner_id = $owner_id;
	}

	function exists($name, $folder_id) {
		// name (string) - Filename, folder_id (int) - Folder id
		// Returns true if the file exists for the defined owner, otherwise false
		if($this->owner_id === null) return false;
        $req = self::$_sql->prepare("SELECT id FROM files WHERE owner_id = ? AND name = ? AND folder_id = ?");
        $req->execute([$this->owner_id, $name, $folder_id]);
        if($req->rowCount() === 0) return false;
        return true;
    }

	function isShared($name, $folder_id) {
		// name (string) - Filename, folder_id (int) - Folder id
		// Returns true if the file is shared, otherwise false
		if($this->owner_id === null) return false;
		$req = self::$_sql->prepare("SELECT id FROM files WHERE owner_id = ? AND name = ? AND folder_id = ? AND dk IS NOT NULL");
		$req->execute([$this->owner_id, $name, $folder_id]);
		if($req->rowCount() === 0) return false;
		return true;
	}

	function getInfos($id) {
		// Get infos from a shared file
		$req = self::$_sql->prepare("SELECT U.id AS uid, U.login, F.name, F.size, F.folder_id, F.id AS fid, F.last_modification, F.dk
			FROM files F, users U WHERE F.id = ? AND F.owner_id = U.id AND F.trash = 0 AND F.expires IS NULL AND F.dk IS NOT NULL");
		$req->execute([$id]);
		if($req->rowCount() === 0) return false;
        return $req->fetch(\PDO::FETCH_ASSOC);
	}

    function getFilename($id = null, $folder_id = null) {
		// id (int) - File id, folder_id (int) - optionnal - folder_id to check if this file is really from it and not from trash
		// Returns filename, or false if it doesn't exist
		if($this->owner_id === null) return false;
		$id = ($id === null) ? $this->id : $id;
		if(!is_pos_digit($id)) return false;
		if(is_pos_digit($folder_id)) {
			$req = self::$_sql->prepare("SELECT name FROM files WHERE owner_id = ? AND id = ? AND folder_id = ? AND trash = 0");
        	$req->execute([$this->owner_id, $id, $folder_id]);
		} else {
        	$req = self::$_sql->prepare("SELECT name FROM files WHERE owner_id = ? AND id = ?");
        	$req->execute([$this->owner_id, $id]);
		}
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['name'];
    }

    function getFolderId($id = null) {
		// id (int) - File id
		// Returns folder id of the file, or false if it doesn't exist
		if($this->owner_id === null) return false;
		$id = ($id === null) ? $this->id : $id;
		if(!is_pos_digit($id)) return false;
        $req = self::$_sql->prepare("SELECT folder_id FROM files WHERE owner_id = ? AND id = ?");
        $req->execute([$this->owner_id, $id]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['folder_id'];
    }

    function getFolderFromId($id = null) {
		// id (int) - File id
		// Returns folder id of the file, or false if it doesn't exist
		$id = ($id === null) ? $this->id : $id;
		if(!is_pos_digit($id)) return false;
        $req = self::$_sql->prepare("SELECT folder_id FROM files WHERE id = ?");
        $req->execute([$id]);
        if($req->rowCount() === 0) return false;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['folder_id'];
    }

    function getSize($id = null) {
		// id (int) - File id (not necessary if name and folder id are defined)
		// Returns size of the file, 0 if it doesn't exist
		if($this->owner_id === null) return false;
        if($id === null && isset($this->name) && isset($this->folder_id)) {
            $req = self::$_sql->prepare("SELECT size FROM files WHERE owner_id = ? AND name = ? AND folder_id = ?");
            $req->execute([$this->owner_id, $this->name, $this->folder_id]);
        }
		else {
			$id = ($id === null) ? $this->id : $id;
			if(!is_pos_digit($id)) return 0;
        	$req = self::$_sql->prepare("SELECT size FROM files WHERE owner_id = ? AND id = ?");
        	$req->execute([$this->owner_id, $id]);
		}
        if($req->rowCount() === 0) return 0;
        $res = $req->fetch(\PDO::FETCH_ASSOC);
        return $res['size'];
    }

    function getFavorites() {
		// Returns an array containing favorites files for current user
		if($this->owner_id === null) return false;
        $req = self::$_sql->prepare("SELECT name, id, size, last_modification, folder_id FROM files WHERE owner_id = ? AND favorite = 1");
        $req->execute([$this->owner_id]);
        return $req->fetchAll(\PDO::FETCH_ASSOC);
    }

    function getFiles($folder_id, $trash = null) {
		// folder_id (int) - Folder id, trash (not necessary, null/'all', 0 or 1) - Show from trash or not
		// Returns an array of files for a folder id, from trash if trash = 1
		if($this->owner_id === null) return false;
        if($trash === null || $trash === 'all') {
            $req = self::$_sql->prepare("SELECT name, id, size, last_modification, favorite, trash, folder_id, dk FROM files WHERE owner_id = ? AND folder_id = ? ORDER BY name ASC");
            $req->execute([$this->owner_id, $folder_id]);
        }
        elseif($trash == 0 || ($trash == 1 && $folder_id !== 0)) {
            $req = self::$_sql->prepare("SELECT name, id, size, last_modification, favorite, trash, folder_id, dk FROM files WHERE owner_id = ? AND folder_id = ? AND trash = 0 ORDER BY name ASC");
            $req->execute([$this->owner_id, $folder_id]);
        }
        else { // trash == 1 && $folder_id == 0
            $req = self::$_sql->prepare("SELECT files.name, files.id, files.size, files.last_modification, files.favorite, files.trash, files.folder_id, files.dk, folders.path, folders.name AS dname
				FROM files LEFT JOIN folders ON files.folder_id = folders.id WHERE files.owner_id = ? AND files.trash = 1 ORDER BY files.name ASC");
            $req->execute([$this->owner_id]);
        }
        if($req->rowCount() === 0) return false;

        // Example
        /*
            Array (
            	[0] => Array (
                	[name] => test.jpg, [id] => 1, [size] => 34, [last_modification] => 0 ...
				)
                [1] => Array (
                	[name] => a.png, [id] => 2, [size] => 30, [last_modification] => 0 ...
                )
            )
        */
        return $req->fetchAll(\PDO::FETCH_ASSOC);
    }

    function addNewFile($folder_id, $expires = true) {
		// folder_id (int) - Folder id, expires (not necessary) - If false, the file cannot expires if not completed
		// Insert a new file in the database, name, size, last_modification need to be set before !
		if($this->owner_id === null) return false;
        $expires = ($expires === false) ? null : time()+86400;
		if(!isset($this->last_modification)) $this->last_modification = time();
		if(isset($this->last_modification) && isset($this->name) && isset($this->size)) {
			return $this->insert('files', [
				'id' => null,
				'owner_id' => intval($this->owner_id),
				'folder_id' => intval($folder_id),
				'name' => $this->name,
				'size' => intval($this->size),
				'last_modification' => $this->last_modification,
				'favorite' => 0,
				'trash' => 0,
				'expires' => $expires
			]);
		}
        return false;
    }

    function updateTrash($id, $trash) {
		// id (int) - File id, trash (int) - Trash state
		// Update trash state for chosen file
		if($this->owner_id === null) return false;
        $req = self::$_sql->prepare("UPDATE files SET trash = ? WHERE owner_id = ? AND id = ?");
        return $req->execute([$trash, $this->owner_id, $id]);
    }

    function updateFile($folder_id, $expires = true) {
		// folder_id (int) - Folder id, expires (not necessary) - If false, the file cannot expires if not completed
        // Update file and returns the difference beetween the size of the new file and the size of the old file
		// name, size, last_modification need to be set before !
		if($this->owner_id === null) return false;
		if(!isset($this->last_modification)) $this->last_modification = time();
		if(isset($this->last_modification) && isset($this->name) && isset($this->size)) {
	        $this->folder_id = $folder_id; // Set current folder id, also needed for getSize
	        $old_size = $this->getSize();
	        if($expires === false) {
	            $req = self::$_sql->prepare("UPDATE files SET size = ?, last_modification = ?, expires = NULL WHERE owner_id = ? AND name = ? AND folder_id = ?");
	        }
	        else {
	            $req = self::$_sql->prepare("UPDATE files SET size = ?, last_modification = ? WHERE owner_id = ? AND name = ? AND folder_id = ?");
	        }
	        $req->execute([$this->size, $this->last_modification, $this->owner_id, $this->name, $folder_id]);
	        return (($this->size)-$old_size);
		}
		return false;
    }

    function updateDir() {
        // Update folder id and filename according to owner and file id
		// name, folder id and file id need to be set before !
		if($this->owner_id === null || !isset($this->folder_id) || !isset($this->name) || !isset($this->id)) return false;
        $req = self::$_sql->prepare("UPDATE files SET folder_id = ?, name = ? WHERE owner_id = ? AND id = ?");
        return $req->execute([$this->folder_id, $this->name, $this->owner_id, $this->id]);
    }

    // Not used for now
    function updateFolderId($old, $new) {
		// Update folder where the file is
		// old - Old folder id (int), new - New folder id (int)
		if($this->owner_id === null) return false;
        $req = self::$_sql->prepare("UPDATE files SET folder_id = ? WHERE owner_id = ? AND folder_id = ?");
        return $req->execute([$new, $this->owner_id, $old]);
    }

    function deleteFile($id) {
		// Delete chosen file
		// id - File id (int)
		if($this->owner_id === null) return false;
		$size = $this->getSize($id);
        if($size === false) return false;
        $req = self::$_sql->prepare("DELETE FROM files WHERE owner_id = ? AND id = ?");
        $req->execute([$this->owner_id, $id]);
        return $size;
    }

    function deleteFiles($folder_id) {
		// Delete files from chosen folder
		// folder_id - Folder id (int)
		if($this->owner_id === null) return false;
        $req = self::$_sql->prepare("DELETE FROM files WHERE owner_id = ? AND folder_id = ?");
        $ret = $req->execute([$this->owner_id, $folder_id]);
        return $ret;
    }

    function setFavorite($id) {
		// Set or unset file as favorite
		// id - File id (int)
		if($this->owner_id === null) return false;
        $req = self::$_sql->prepare("UPDATE files SET favorite = ABS(favorite-1) WHERE owner_id = ? AND id = ?");
        return $req->execute([$this->owner_id, $id]);
    }

	function setDK($id, $dk) {
		// Set a DK for a file in order to share it
		if($this->owner_id === null) return false;
		$req = self::$_sql->prepare("UPDATE files SET dk = ? WHERE owner_id = ? AND id = ? AND trash = 0");
        return $req->execute([$dk, $this->owner_id, $id]);
	}

    function rename($folder_id, $old, $new) {
		// Rename a file
		// folder_id - Folder id (int), old - Old name (string), new - New name (string)
		if($this->owner_id === null) return false;
        $req = self::$_sql->prepare("UPDATE files SET name = ? WHERE owner_id = ? AND folder_id = ? AND name = ?");
        return $req->execute([$new, $this->owner_id, $folder_id, $old]);
    }

    function getFullPath($id) {
        // Used for download feature
		// id - File id (int)
		if($this->owner_id === null || !is_pos_digit($id)) return false;
        $folder_id = $this->getFolderId($id);
        if($folder_id === false) return false;
        if($folder_id !== 0) {
            $req = self::$_sql->prepare("SELECT `path`, folders.name AS dname, files.name AS fname FROM files, folders
				WHERE files.owner_id = ? AND files.id = ? AND folders.id = ? AND folders.owner_id = files.owner_id");
            $req->execute([$this->owner_id, $id, $folder_id]);
            if($req->rowCount() === 0) return false;
            $res = $req->fetch(\PDO::FETCH_ASSOC);
            return NOVA.'/'.$this->owner_id.'/'.$res['path'].$res['dname'].'/'.$res['fname'];
        }
        $filename = $this->getFilename($id);
        if($filename === false) return false;
        return NOVA.'/'.$this->owner_id.'/'.$filename;
    }

	function deleteFilesfinal() {
		if($this->owner_id === null) return false;
		$req2 = self::$_sql->prepare("DELETE FROM files WHERE owner_id = ?");
		return $req2->execute([$this->owner_id]);
	}
}
