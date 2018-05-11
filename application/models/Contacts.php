<?php
namespace application\models;
use \library\MVC as l;

class Contacts extends l\Model {
    /*
    CREATE TABLE IF NOT EXISTS contacts (
    	user_id uuid,
    	contacts list<uuid>,
    	PRIMARY KEY (user_id)
    );
    */

    protected $user_id = null;

    function __construct($user_id = null) {
		parent::__construct();
		// user_id (uuid) can be passed at init
		$this->user_id = $user_id;
	}
}
