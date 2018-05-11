<?php
namespace application\models;
use \library\MVC as l;

class SharedWith extends l\Model {
    /*
    CREATE TABLE IF NOT EXISTS shared_with (
    	file_id uuid,
    	users list<uuid>,
    	PRIMARY KEY (file_id)
    );
    */

    protected $file_id = null;

    function __construct($file_id = null) {
		parent::__construct();
		// file_id (uuid) can be passed at init
		$this->file_id = $file_id;
	}
}
