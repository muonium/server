<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \application\models as m;
use \config as conf;

/* Called after a transaction by CoinPayments */

class IPN extends l\Controller {
	private $_modelUpgrade;
	private $_modelStoragePlans;
	private $_modelUsers;

    function __construct() {
        parent::__construct();
		$this->_modelUpgrade = new m\Upgrade();
		$this->_modelStoragePlans = new m\StoragePlans();
		$this->_modelUsers = new m\Users();
    }

    public function DefaultAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues('array');

		$merchant_id = conf\confPayments::merchant_id;
		$ipn_secret = conf\confPayments::ipn_secret;

		if($method !== 'post') {
			$resp['code'] = 405; // Method not allowed
		}
		elseif(count($data) > 0) {
			if(isset($_SERVER['HTTP_HMAC']) && isset($data['merchant']) && $data['merchant'] == $merchant_id) {
				$request = file_get_contents('php://input');
				if(isset($request) && $request !== false) {
					$hmac = hash_hmac('sha512', $request, $ipn_secret);
					if(hash_equals($hmac, $_SERVER['HTTP_HMAC'])) {
						$ipn_mode 	= 	isset($data['ipn_mode']) 								? $data['ipn_mode'] 			: null;
						$product_id = 	isset($data['item_number']) 							? $data['item_number'] 			: null;
						$user_id 	= 	isset($data['custom']) && is_pos_digit($data['custom']) ? intval($data['custom']) 		: 0;
						$txn_id 	= 	isset($data['txn_id']) 									? $data['txn_id'] 				: null;
						$status 	= 	isset($data['status']) && is_numeric($data['status']) 	? intval($data['status']) 		: 0;
						$currency1 	= 	isset($data['currency1']) 								? $data['currency1'] 			: null;
						$amount1 	= 	isset($data['amount1']) && is_numeric($data['amount1']) ? floatval($data['amount1']) 	: 0;
						$currency2	= 	isset($data['currency2']) 								? $data['currency2'] 			: null;
						$amount2 	= 	isset($data['amount2']) && is_numeric($data['amount2']) ? floatval($data['amount2']) 	: 0;

						if($ipn_mode == 'hmac' && $user_id !== 0 && $product_id !== null && $txn_id !== null && $currency1 !== null) {
							if(!($this->_modelUpgrade->transactionExists($txn_id))) {
								$plans = $this->_modelStoragePlans->getPlans();
								foreach($plans as $plan) {
									if($plan['product_id'] === $product_id) { // get price currency & name with product_id
										$price = floatval($plan['price']);
										$currency = $plan['currency'];
										$size = $plan['size'];
										$duration = $plan['duration'];
										break;
									}
								}

								if(isset($price) && isset($currency) && isset($size) && isset($duration)) {
									if(strtoupper($currency) === strtoupper($currency1) && $amount1 >= floatval($price)) {
										if($status >= 100 || $status == 2) {
											$this->_modelUpgrade->id_user = $user_id;
											$user_mail = $this->_modelUsers->getEmail($user_id);
											if($user_mail !== false) {
												$resp['code'] = 200;
												$resp['status'] = 'success';
												$this->_modelUpgrade->addUpgrade($size, $amount2, $currency2, $duration, $txn_id, $user_id);
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
	}
}
