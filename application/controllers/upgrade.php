<?php
namespace application\controllers;
use \library as h;
use \library\MVC as l;
use \application\models as m;
use \config as conf;

class upgrade extends l\Controller {
	private $_modelUpgrade;
	private $_modelStoragePlans;

    function __construct() {
        parent::__construct([
            'mustBeLogged' => true
        ]);
		$this->_modelUpgrade = new m\Upgrade($this->_uid);
		$this->_modelStoragePlans = new m\StoragePlans();
    }

    public function plansAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$resp['token'] = $this->_token;

		if($method !== 'get') {
			$resp['code'] = 405; // Method Not Allowed
		}
		else {
			$resp['code'] = 200;
			$resp['status'] = 'success';

			$resp['data']['endpoint'] = 'https://www.coinpayments.net/index.php';
			$resp['data']['plans'] = [];
			$merchant_id = conf\confPayments::merchant_id;
			$ipn_url = conf\confPayments::ipn_url;

			$storage_plans = $this->_modelStoragePlans->getPlans();
			foreach($storage_plans as $plan) {
				if($plan['product_id'] !== null) {
					$product_name = showSize($plan['size']).' - '.$plan['price'].' '.strtoupper($plan['currency']).' - '.$this->duration($plan['duration']);
					$plan['size'] = intval($plan['size']);
					$plan['price'] = floatval($plan['price']);
					$plan['duration'] = intval($plan['duration']);
					$plan['currency_symbol'] = currencySymbol($plan['currency']);
					$plan['fields'] = [
						'cmd' => '_pay_simple',
						'merchant' => $merchant_id,
						'item_name' => $product_name,
						'item_number' => $plan['product_id'],
						'currency' => strtolower($plan['currency']),
						'amountf' => floatval($plan['price']),
						'ipn_url' => $ipn_url,
						'custom'  => $this->_uid,
						'want_shipping' => '0'
					];
					unset($plan['id']);
					$resp['data']['plans'][] = $plan;
				}
			}
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
    }

    public function canSubscribeAction() {
        header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$resp['token'] = $this->_token;

		if($method !== 'get') {
			$resp['code'] = 405; // Method Not Allowed
		}
        else {
            $resp['code'] = 200;
            $resp['status'] = 'success';
            if(!($this->_modelUpgrade->hasSubscriptionActive($this->_uid))) {
                $resp['data']['can_subscribe'] = true;
            } else {
                $resp['data']['can_subscribe'] = false;
            }
        }

        http_response_code($resp['code']);
		echo json_encode($resp);
    }

    public function hasSubscriptionActiveAction() {
        header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$resp['token'] = $this->_token;

		if($method !== 'get') {
			$resp['code'] = 405; // Method Not Allowed
		}
        else {
            $resp['code'] = 200;
            $resp['status'] = 'success';
            if($this->_modelUpgrade->hasSubscriptionActive($this->_uid)) {
                $id_upgrade = $this->_modelUpgrade->getActiveSubscription($this->_uid);
                $resp['data']['subscribed'] = true;
                $resp['data']['id_upgrade'] = $id_upgrade;
            } else {
                $resp['data']['subscribed'] = false;
            }
        }

        http_response_code($resp['code']);
		echo json_encode($resp);
    }

    public function cancelAction() {
        header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$resp['token'] = $this->_token;

        if($method !== 'get') {
			$resp['code'] = 405; // Method Not Allowed
		}
        else {
            $resp['code'] = 200;
            $resp['status'] = 'success';
            if($this->_modelUpgrade->hasSubscriptionActive($this->_uid)) {
                $this->_modelUpgrade->cancelSubscription($this->_uid);
                $resp['data']['canceled'] = true;
            } else {
                $resp['data']['canceled'] = false;
            }
        }

        http_response_code($resp['code']);
		echo json_encode($resp);
    }

    public function hasSubscriptionEndedAction() {
        header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$resp['token'] = $this->_token;

        if($method !== 'get') {
			$resp['code'] = 405; // Method Not Allowed
		}
        else {
            $resp['code'] = 200;
            $resp['status'] = 'success';
            if(!$this->_modelUpgrade->hasExpired($this->_uid)) {
				$daysLeft = $this->_modelUpgrade->getDaysLeft($this->_uid);
				$resp['data']['expired'] = false;
				$resp['data']['days_left'] = $daysLeft;
                if($this->_modelUpgrade->expiresSoon($this->_uid)) {
				    $resp['data']['expires_soon'] = true;
                } else {
				    $resp['data']['expires_soon'] = false;
                }
            } else {
				$resp['data']['expired'] = true;
            }
        }

        http_response_code($resp['code']);
		echo json_encode($resp);
    }

	public function historyAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$method = h\httpMethodsData::getMethod();
		$data = h\httpMethodsData::getValues();
		$resp['token'] = $this->_token;

		if($method !== 'get') {
			$resp['code'] = 405; // Method Not Allowed
		}
		else {
            $upgrades = [];
            if($upgrades = $this->_modelUpgrade->getUpgrades()) {
                foreach($upgrades as $i => $upgrade) {
                    unset($upgrade['id']);
                    unset($upgrade['id_user']);
                    $upgrade['currency_symbol'] = currencySymbol($upgrade['currency']);
                    $upgrades[$i] = $upgrade;
                }
            }
            $resp['code'] = 200;
            $resp['status'] = 'success';
            $resp['data'] = $upgrades;
		}

		http_response_code($resp['code']);
		echo json_encode($resp);
	}

	public function DefaultAction() {
		header("Content-type: application/json");
		$resp = self::RESP;
		$resp['token'] = $this->_token;

		http_response_code($resp['code']);
		echo json_encode($resp);
	}

	private function duration($duration) {
		if($duration < 0) return self::$txt->Upgrade->lifetime;
		if($duration === 12) return $duration.' '.self::$txt->Upgrade->year;
		if($duration % 12 === 0) return ($duration/12).' '.self::$txt->Upgrade->years;
		if($duration === 1) return $duration.' '.self::$txt->Upgrade->month;
		return $duration.' '.self::$txt->Upgrade->months;
	}
}
