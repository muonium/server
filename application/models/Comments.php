<?php
namespace application\models;
use \library\MVC as l;

class Comments extends l\Model {
    /*
    CREATE TABLE IF NOT EXISTS comments (
    	comment_id uuid,
    	file_id uuid,
    	user_id uuid,
    	added_date timestamp,
    	ct text,
    	PRIMARY KEY ((comment_id), added_date, file_id)
    ) WITH CLUSTERING ORDER BY (added_date DESC);
    */

    function __construct() {
		parent::__construct();
	}
}
