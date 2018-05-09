<?php
namespace application\controllers;
use \library\MVC as l;

class version extends l\Controller {
    function __construct() {
        header("Content-type: application/json");
        $resp = self::RESP;
        $resp['code'] = 200;
        $resp['status'] = 'success';
        $resp['data'] = VERSION;

        http_response_code($resp['code']);
		echo json_encode($resp);
    }
}
