<?php
// This file is always called
use \library\MVC as l;

require_once("./vendor/autoload.php");

// Defines

// Mui Version
define('VERSION', '2018.02.28.0');

define('DS', DIRECTORY_SEPARATOR);
define('ROOT', __DIR__);
define('MVC_ROOT', str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']));
define('URL_APP', $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . MVC_ROOT);
define('IMG', MVC_ROOT.'/public/pictures/');
define('NOVA', dirname(dirname(__FILE__)).'/nova');

// Default controller
define('DEFAULT_CONTROLLER', 'Home');
define('DEFAULT_FUNCTION', 'DefaultAction');

define('DIR_CLASS', ROOT.'/application/controllers/');
define('DIR_MODEL', ROOT.'/application/models/');
define('DIR_VIEW',  ROOT.'/application/views/');

define('DEFAULT_LANGUAGE', 'en');
define('DIR_LANGUAGE', ROOT.'/public/translations/');

require_once("./library/MVC/Functions.php");

$_routing = l\Routing::getInstance();
$_routing->route();
