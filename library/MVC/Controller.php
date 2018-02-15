<?php
namespace library\MVC;
use \library as h;

class Controller {
    // This class is called by all controllers (with "extends")
    // It provides sessions management, verifying/generating a token and using different languages

    // $txt contains user language json
    public static $txt = null;
    public static $userLanguage = DEFAULT_LANGUAGE;

	private $redis;
	private $addr = 'tcp://127.0.0.1:6379';
	private $exp = 1200;
	private $decoded = null;

	// Current token and UID - can be used in controllers that require a logged user
	public $_token = null;
	public $_uid = null;

    // Available languages
    public static $languages = [
        'en' => 'English',
		'de' => 'Deutsch',
		'es' => 'Español',
        'fr' => 'Français',
        'it' => 'Italiano',
		'pl' => 'Polskie',
        'ru' => 'Русский',
		'zh-cn' => '简体中文'
    ];

	// Default response (bad request)
	const RESP = [
		'code' => 400,
		'status' => 'error',
		'data' => [],
		'message' => null,
		'token' => null
	];

    // Constructor loads user language json
    function __construct($tab = '') {
		$this->redis = new \Predis\Client($this->addr);
		$resp = self::RESP;
        if(is_array($tab)) {
			// Authentication middleware
            if(array_key_exists('mustBeLogged', $tab) && $tab['mustBeLogged'] === true) {
				$token = h\httpMethodsData::getToken();
				if($token !== null) {
					$token = $this->verifyToken($token);
					if($token !== false) { // Token is still valid
						$decodedToken = $this->getDecodedToken();
						$this->_uid = $decodedToken['data']['uid'];
						$this->_token = $token;
						return true;
					}
				}
				// Not authorized
				header("Content-type: application/json");
				$resp['code'] = 401;
				http_response_code($resp['code']);
				exit(json_encode($resp));
            }
        }
        // Get user language
		$lang = !empty($_COOKIE['lang']) ? htmlentities($_COOKIE['lang']) : DEFAULT_LANGUAGE;
        self::loadLanguage($lang);
    }

	public static function loadLanguage($lang) {
		if(file_exists(DIR_LANGUAGE.$lang.".json")) {
			$_json = file_get_contents(DIR_LANGUAGE.$lang.".json");
			self::$userLanguage = $lang;
		} elseif($lang === DEFAULT_LANGUAGE) {
			exit('Unable to load DEFAULT_LANGUAGE JSON !');
		} else {
			self::loadLanguage(DEFAULT_LANGUAGE);
		}

		self::$txt = json_decode($_json);
		if(json_last_error() !== 0) {
			if($lang === DEFAULT_LANGUAGE) {
				exit('Error in the DEFAULT_LANGUAGE JSON !');
			}
			self::loadLanguage(DEFAULT_LANGUAGE);
		}
		return true;
	}

    // Returns a language selector (select)
    public static function getLanguageSelector() {
        $html = '
			<select onchange="changeLanguage(this.value)">';
        foreach(self::$languages as $iso => $lang) {
            $html .= '
				<option value="'.$iso.'"'.($iso == self::$userLanguage ? ' selected': '').'>'.$lang.'</option>';
        }
        $html .= '</select>';
		return $html;
    }

	public function getRedis() {
		return $this->redis;
	}

	public function buildToken($userId) {
		$secretKey  = \config\secretKey::get();
		$jti        = base64_encode(mcrypt_create_iv(32));
		$issuedAt   = time();
		$expire     = $issuedAt + $this->exp;
		$serverName = $_SERVER['SERVER_NAME'];

		$data = [
			'iat'  => $issuedAt,     // Issued at: time when the token was generated
			'jti'  => $jti,          // Json Token Id: an unique identifier for the token
			'iss'  => $serverName,   // Issuer
			'nbf'  => $issuedAt,     // Not before
			'exp'  => $expire,       // Expire
			'data' => [              // Data related to the signer user
				'uid'   => $userId
			]
		];

		$this->redis->set('token:'.$jti, true);
		$this->redis->set('token:'.$jti.':uid', $userId);
		$this->redis->set('token:'.$jti.':iat', $issuedAt);
		$this->redis->append('uid:'.$userId, $jti.';');

		$this->decoded = $data;
		return \Firebase\JWT\JWT::encode($data, $secretKey, 'HS384');
	}

	public function removeToken($jti, $userId) {
		if($uidTokens = $this->redis->get('uid:'.$userId)) {
			$uidTokens = str_replace($jti.';', '', $uidTokens);
			if(strlen($uidTokens) > 0) {
				$this->redis->set('uid:'.$userId, $uidTokens);
			} else {
				$this->redis->del('uid:'.$userId);
			}
		}
		$this->redis->del('token:'.$jti.':uid');
		$this->redis->del('token:'.$jti.':iat');
		return $this->redis->del('token:'.$jti);
	}

	public function removeTokens($userId) {
		if($uidTokens = $this->redis->get('uid:'.$userId)) {
			$uidTokens = substr($uidTokens, -1) === ';' ? substr($uidTokens, 0, -1) : $uidTokens;
			$uidTokens = explode(';', $uidTokens);
			foreach($uidTokens as $jti) {
				$this->redis->del('token:'.$jti.':uid');
				$this->redis->del('token:'.$jti.':iat');
				$this->redis->del('token:'.$jti);
			}
		}
		return $this->redis->del('uid:'.$userId);
	}

	public function verifyToken($token, $auto_generate = true) {
		// Valid : return the token or a new token
		// Not valid : return false
		$secretKey = \config\secretKey::get();
		if(strlen($token) < 1) return false;
		$decoded = null;
		try {
        	$decoded = \Firebase\JWT\JWT::decode($token,  $secretKey, ['HS384']);
		} catch(\Exception $e) {
    		if(is_object($decoded) && isset($decoded->jti) && isset($decoded->data->uid)) {
				$this->decoded = json_decode(json_encode($decoded), true); // Cast to array (recursively)
				$this->removeToken($decoded->jti, $decoded->data->uid);
			}
			return false;
		}
		$this->decoded = json_decode(json_encode($decoded), true); // Cast to array (recursively)

		if(!$this->redis->exists('token:'.$decoded->jti)) {
			return false;
		}

		if($decoded->exp <= time()+$this->exp/2) {
			$this->removeToken($decoded->jti, $decoded->data->uid);
			if($auto_generate === false) { // Do not generate a new token
				return false;
			}
			$token = $this->buildToken($decoded->data->uid);
		}
		return $token;
	}

	public function getDecodedToken() {
		// Return the decoded token sent by user (encoded), /!\ it can be expired or removed, check the validity before
		return $this->decoded;
	}
}
