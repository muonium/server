<?php
// This file is always called
use \library\MVC as l;

if($_SERVER['REQUEST_METHOD'] === 'OPTIONS' && isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
    // Handling preflight requests
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: X-Requested-With');
    exit;
}

require_once("./vendor/autoload.php");

// Defines

// Mui Version
define('VERSION', '2018.05.07.0');

define('DS', DIRECTORY_SEPARATOR);
define('ROOT', __DIR__);
define('MVC_ROOT', str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']));
define('NOVA', dirname(dirname(__FILE__)).'/nova');
define('URL_SERVER', $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . MVC_ROOT);
// Webclient
define('URL_APP', $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . '/app');

// Default controller
define('DEFAULT_CONTROLLER', 'home');
define('DEFAULT_FUNCTION', 'DefaultAction');

define('DIR_CLASS', ROOT.'/application/controllers/');
define('DIR_MODEL', ROOT.'/application/models/');

define('DEFAULT_LANGUAGE', 'en');
define('DIR_LANGUAGE', ROOT.'/public/translations/');

require_once("./library/MVC/Functions.php");

$_routing = l\Routing::getInstance();
$_routing->route();
