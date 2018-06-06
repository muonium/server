<?php
/* Functions that can be used everywhere */

/* Convert file id to b64 encoded for download url */
function setURL($id) {
	return rtrim(strtr(base64_encode($id), '+/', '-_'), '=');
}

/* Convert b64 encoded from download url to file id */
function getFileId($b) {
	return base64_decode(str_pad(strtr($b, '-_', '+/'), strlen($b) % 4, '=', STR_PAD_RIGHT));
}

/* Return human readable size */
function showSize($size, $precision = 2) {
	// $size => size in bytes
	if(!is_numeric($size)) return 0;
	if($size <= 0) return 0;
	$base = log($size, 1000);
    // We need to load language to get units but this method is only called on plansAction which already loads it
	$suffixes = array_values((array)\library\MVC\Controller::$txt->Units);
	return round(pow(1000, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
}

function currencySymbol($currency) {
	$currencies = [
		'EUR' => '€',
		'USD' => '$',
		'GBP' => '£',
		'JPY' => '¥',
		'CNY' => '¥',
		'RUB' => '₽',
		'BTC' => '฿'
	];
	return array_key_exists(strtoupper($currency), $currencies) ? $currencies[strtoupper($currency)] : $currency;
}

function is_digit($digit, $allow_negative = true) {
	if(is_int($digit)) {
		return !$allow_negative && $digit < 0 ? false : true;
	} elseif(is_string($digit)) {
		return $allow_negative && $digit[0] === '-' ? ctype_digit(substr($digit, 1)) : ctype_digit($digit);
	}
	return false;
}
function is_pos_digit($digit) {
	return is_digit($digit, false);
}
