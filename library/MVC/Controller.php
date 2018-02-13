<?php
namespace library\MVC;

class Controller {
    // This class is called by all controllers (with "extends")
    // It provides sessions management, verifying/generating a token and using different languages

    // $txt contains user language json
    public static $txt = null;
    public static $userLanguage = DEFAULT_LANGUAGE;

	private $redis;
	private $addr = 'tcp://127.0.0.1:6379';
	private $exp = 1200;

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

    // Constructor loads user language json
    function __construct($tab = '') {
        if(is_array($tab)) {
            if(array_key_exists('mustBeLogged', $tab)) {
				$this->redis = new \Predis\Client($this->addr);
                if($tab['mustBeLogged'] === true && !isset($_SESSION['id'])) {
                    exit(header('Location: '.MVC_ROOT.'/Login'));
                }
            }
            if(array_key_exists('mustBeValidated', $tab)) {
				$this->redis = new \Predis\Client($this->addr);
                if($tab['mustBeValidated'] === true && isset($_SESSION['validate'])) {
                    exit(header('Location: '.MVC_ROOT.'/Validate'));
                }
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

	private function buildToken($userId) {
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

		$encoded = \Firebase\JWT\JWT::encode($data, $secretKey, 'HS384');
		return json_encode(['jwt' => $encoded]);
	}

	private function removeToken($jti, $userId) {
		if($uidTokens = $this->redis->get('uid:'.$userId)) {
			$uidTokens = str_replace($jti.';', '', $uidTokens);
			if(strlen($uidTokens) > 0) {
				$this->redis->set('uid:'.$userId, $uidTokens);
			} else {
				$this->removeTokens($userId);
			}
		}
		$this->redis->del('token:'.$jti.':uid');
		$this->redis->del('token:'.$jti.':iat');
		return $this->redis->del('token:'.$jti);
	}

	private function removeTokens($userId) {
		return $this->redis->del('uid:'.$userId);
	}

	private function verifyToken($token) {
		// Valid : return the token or a new token
		// Not valid : return false
		$secretKey = \config\secretKey::get();
		if(strlen($token) < 1) return false;
		try {
        	$decoded = \Firebase\JWT\JWT::decode($token,  $secretKey, 'HS384');
		} catch(\Exception $e) {
    		if(is_array($decoded) && isset($decoded['jti']) && isset($decoded['data']['uid'])) {
				$this->removeToken($decoded['jti'], $decoded['data']['uid']);
			}
			return false;
		}

		if($this->redis->exists('token:'.$decoded['jti']) !== true) {
			return false;
		}

		if($decoded['exp'] <= time()+$this->exp/2) {
			$this->removeToken($decoded['jti'], $decoded['data']['uid']);
			$token = $this->buildToken($decoded['data']['uid']);
		}
		return $token;
	}
}
