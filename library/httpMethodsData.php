<?php
namespace library;

class httpMethodsData {

	/* Array of objects containing data related to a method */
	private static $methods = [
		'post' => null,
		'get' => null,
		'put' => null,
		'delete' => null,
		'head' => null,
		'patch' => null
	];

    private static $init = false;
    private static $method;		// Method used to perform the request
    private static $headers;	// HTTP Headers
	private static $token = null;

    public static function init() {
		if(self::$init) {
			return true;
		}
		$method = strtolower($_SERVER['REQUEST_METHOD']);
        self::$method = array_key_exists($method, self::$methods) ? $method : null;

        if(self::$method === null) {
            return false;
		}

        $values = json_decode(file_get_contents("php://input"));
        if($values === null) {
            if(self::$method === 'post') {
                $values = (object)$_POST;
            } elseif(self::$method === 'get') {
                $values = (object)$_GET;
			}
        }

        self::$methods[$method] = $values;

        self::$headers = getallheaders();
		if(array_key_exists('Authorization', self::$headers)) {
			// Get token
			$auth = trim(self::$headers['Authorization']);
			if(substr($auth, 0, 7) === 'Bearer ') {
				self::$token = trim(substr($auth, 7));
			}
		}

        self::$init = true;
        return true;
    }

    /* Get a value from HTTP header */
    public static function getHeader($name) {
        self::init();
        if(!array_key_exists($name, self::$headers)) {
            return false;
		}
        return self::$headers[$name];
    }

	/* Get HTTP headers (return an array) */
	public static function getHeaders() {
		self::init();
		return self::$headers;
	}

	public static function getToken() {
		self::init();
		return self::$token;
	}

    /* Get the requested method */
    public static function getMethod() {
        self::init();
        return self::$method;
    }

    /* An example with getMethod() inside a controller :

        function categorieAction() {
			$values = \library\httpMethodsData::getValues();
            switch(\library\httpMethodsData::getMethod()) {
                case 'put':
                    $this->addCategorie($values->name);
                    break;
                case 'delete':
                    $this->deleteCategorie($values->id);
                    break;
                case 'get':
                    $this->getCategorie($values->id);
                    break;
            }
        }

    */

	/* Get values for the requested method (or chosen method in $method) in the requested format ($return) (object, array or json) */
	public static function getValues($return = 'object', $method = null) {
		self::init();
		$resp = $method === null || !array_key_exists($method, self::$methods) ? self::$methods[self::$method] : self::$methods[$method];

		if($return === 'array') {
			return (array)$resp;
		} elseif($return === 'json') {
			return $resp === null ? '{}' : json_encode($resp);
		}
		return $resp;
	}
	public static function getValue($return = 'object', $method = null) { // Alias
		return self::getValues($return, $method);
	}
	public static function getData($return = 'object', $method = null) { // Alias
		return self::getValues($return, $method);
	}

    /* Get data sent with post in an object */
    public static function post($return = 'object') {
        self::init();
        return self::getValues($return, 'post');
    }

    /* Get data sent with get in an object */
    public static function get($return = 'object') {
        self::init();
        return self::getValues($return, 'get');
    }

    /* Get data sent with put in an object */
    public static function put($return = 'object') {
        self::init();
        return self::getValues($return, 'put');
    }

    /* Get data sent with delete in an object */
    public static function delete($return = 'object') {
        self::init();
        return self::getValues($return, 'delete');
    }

    /* Get data sent with head in an object */
    public static function head($return = 'object') {
        self::init();
        return self::getValues($return, 'head');
    }

    /* Get data sent with patch in an object */
    public static function patch($return = 'object') {
        self::init();
        return self::getValues($return, 'patch');
    }
};
