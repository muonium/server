<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \application\models as m;

class bug extends l\Controller {
    private $_modelUser;
    private $_mail;
    private $_message;

    function __construct() {
        parent::__construct([
            'mustBeLogged' => true
        ]);
    }

    public function DefaultAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$resp['token'] = $this->_token;

		if($method === 'post') {
			sleep(2);
			if(isset($data->os) && isset($data->browser) && isset($data->message)) {
				$version = isset($data->version) ? $data->version : '';
				if(strlen($data->message) > 50) {
					$os = $data->os;
					$browser = $data->browser;
					$this->_modelUser = new m\Users($this->_uid);
	                if($mail = $this->_modelUser->getEmail()) {
						$resp['code'] = 200;
						$resp['status'] = 'success';

	                    // Send the mail
	                    $this->_mail = new l\Mail();
						$this->_mail->delay(60, $this->_uid, $this->getRedis());
	                    $this->_mail->_to = "bug@muonium.ee";
	                    $this->_mail->_subject = "[Bug report] ".$mail." - ".substr(htmlspecialchars($data->message), 0, 20);
	                    $this->_mail->_message = "====================<br>
	                    <strong>User mail :</strong> ".$mail."<br>
	                    <strong>User ID :</strong> ".$this->_uid."<br>
	                    <strong>O.S :</strong> ".$os."<br>
	                    <strong>Browser :</strong> ".$browser."<br>
	                    <strong>Browser version :</strong> ".htmlspecialchars($version)."
	                    <br>====================<br>"
	                        .nl2br(htmlspecialchars($data->message));

						$resp['message'] = $this->_mail->send(); // 'sent' or 'wait'
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
}
