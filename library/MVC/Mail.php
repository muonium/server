<?php
namespace library\MVC;
use \config as conf;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mail {
	private $redis = null;
	private $uid = null;
	private $delay = 0;
	private $nbf = 0;
	private $suffix = null;
  private $debug = false;

    protected $_to;
    protected $_subject;
    protected $_message;

    function __set($attr, $val) {
        $this->$attr = $val;
    }

    function setDebug($debug) {
      $this->debug = $debug;
    }

	function delay($delay, $uid, $redis, $suffix = null) {
		$suffix = $suffix === null ? '' : ':'.$suffix;
		// Add a delay before sending a new mail (spam protection)
		$this->delay = $delay;
		$this->uid = $uid;
		$this->redis = $redis;
		$this->suffix = $suffix;

		$lastMail = $redis->get('uid:'.$uid.':mailnbf'.$suffix);
		if(!$lastMail) $lastMail = 0;
		$this->nbf = intval($lastMail);
	}

    function send() {
		if($this->uid !== null && $this->redis !== null) {
			// Spam protection - return 'wait' if nbf > current time
			if($this->nbf > time()) {
				return 'wait';
			}
			$this->nbf = time() + $this->delay;
			$this->redis->set('uid:'.$this->uid.':mailnbf'.$this->suffix, $this->nbf);
		}

        $passage_line = "\n";
        // We filter servers that encounter bugs.
        if (!preg_match("#^[a-z0-9._-]+@(hotmail|live|msn).[a-z]{2,4}$#", $this->_to)) {
            $passage_line = "\r\n";
        }

        // Message in txt format and in HTML format
        $message_txt = strip_tags(str_replace(['<br>','<br />','<br/>'], $passage_line, $this->_message));
        $message_html = $this->_message;

        $mail = new PHPMailer();
        if($this->debug) $mail->SMTPDebug = 3; // Debug
        $mail->isSMTP();
        $mail->Host = conf\confMail::smtp_host;
        $mail->SMTPAuth = conf\confMail::smtp_auth;
        if(conf\confMail::smtp_auth) {
          $mail->Username = conf\confMail::user;
          $mail->Password = conf\confMail::password;
        }
        if(defined('conf\confMail::smtp_secure') && conf\confMail::smtp_secure !== null && conf\confMail::smtp_secure !== false) {
          $mail->SMTPSecure = conf\confMail::smtp_secure;
        }
        $mail->Port = conf\confMail::port;

        $mail->setFrom(conf\confMail::user, conf\confMail::username);
        $mail->AddReplyTo(conf\confMail::user, conf\confMail::username);
        $mail->addAddress($this->_to);
        $mail->isHTML(true);
		$mail->CharSet = 'utf-8';
        $mail->Subject = $this->_subject;
        $mail->Body    = $message_html;
        $mail->AltBody = $message_txt;

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        $mail->send();
		return 'sent';
    }
}
