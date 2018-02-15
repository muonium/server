<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \application\models as m;

class Bug extends l\Controller {

    private $_modelUser;
    private $_mail;
    private $_message;

    // Different possible values for select tag

    private $values = [
        "os" => [
            "Linux" => "GnuLinux/Unix/BSD",
            "Mac" => "Mac",
            "Win" => "Windows",
            "Android" => "Android",
            "iOS" => "iOS",
            "other" => ""
        ],
        "browser" => [
            "Chrome" => "Google Chrome/Chromium",
            "Firefox" => "Firefox",
            "Edge" => "Microsoft Edge",
            "Safari" => "Apple Safari",
            "Opera" => "Opera",
            "Explorer" => "Internet Explorer",
            "other" => ""
        ]
    ];

    ///////////////////

    function __construct() {
        parent::__construct([
            'mustBeLogged' => true
        ]);
    }

    /*function printValues($key) {
        // Print values from values array for the selected key
        if(array_key_exists($key, $this->values)) {
            foreach($this->values[$key] as $key => $value) {
                if($key == 'other') $value = self::$txt->Bug->other;
                echo '\n<option value="'.htmlentities($key).'">'.htmlentities($value).'</option>';
            }
        }
    }*/

    function checkValue($value, $key) {
        // Check if the entered value is in the array
        if(array_key_exists($value, $this->values[$key])) {
            return htmlentities($this->values[$key][$value]);
		}
        return false;
    }

    function DefaultAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$resp['token'] = $this->_token;

		if($method === 'get') {
			$resp['code'] = 200;
			$resp['status'] = 'success';
			$resp['data']['os'] = $this->values['os'];
			$resp['data']['browser'] = $this->values['browser'];
		}
		elseif($method === 'post') {
			sleep(2);
			if(isset($data->os) && isset($data->browser) && isset($data->message)) {
				$version = isset($data->version) ? $data->version : '';
				if(strlen($data->message) > 50) {
					if(($os = $this->checkValue($data->os, 'os')) && ($browser = $this->checkValue($data->browser, 'browser'))) {
						$this->_modelUser = new m\Users($this->_uid);
	                    if($mail = $this->_modelUser->getEmail()) {
							$resp['code'] = 200;
							$resp['status'] = 'success';

	                        // Send the mail
	                        $this->_mail = new l\Mail();
							$this->_mail->delay(60, $this->_uid, $this->getRedis());
	                        $this->_mail->_to = "bug@muonium.ee";
	                        $this->_mail->_subject = "[Bug report] ".$mail." - ".substr(htmlentities($data->message), 0, 20);
	                        $this->_mail->_message = "====================<br>
	                        <strong>User mail :</strong> ".$mail."<br>
	                        <strong>User ID :</strong> ".$this->_uid."<br>
	                        <strong>O.S :</strong> ".$os."<br>
	                        <strong>Browser :</strong> ".$browser."<br>
	                        <strong>Browser version :</strong> ".htmlentities($version)."
	                        <br>====================<br>"
	                            .nl2br(htmlentities($data->message));

							$resp['message'] = $this->_mail->send(); // 'sent' or 'wait'
						}
					}
				} else {
					$resp['message'] = 'messageLength';
				}
			} else {
				$resp['message'] = 'emptyField';
			}
		} else {
			$resp['code'] = 405; // Method Not Allowed
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
    }
};
?>
