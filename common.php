<?php

define('S_OK', 'OK');
define('S_BAD_OTP', 'BAD_OTP');
define('S_REPLAYED_OTP', 'REPLAYED_OTP');
define('S_DELAYED_OTP', 'DELAYED_OTP');
define('S_BAD_SIGNATURE', 'BAD_SIGNATURE');
define('S_MISSING_PARAMETER', 'MISSING_PARAMETER');
define('S_NO_SUCH_CLIENT', 'NO_SUCH_CLIENT');
define('S_OPERATION_NOT_ALLOWED', 'OPERATION_NOT_ALLOWED');
define('S_BACKEND_ERROR', 'BACKEND_ERROR');

define('TS_SEC', 1/8);
define('TS_REL_TOLERANCE', 0.3);
define('TS_ABS_TOLERANCE', 20);

define('TOKEN_LEN', 32);

function unescape($s) {
	return str_replace('\\', "", $s);
}

function getHttpVal($key, $defaultVal) {
	$val = $defaultVal;
	if (array_key_exists($key, $_GET)) {
		$val = $_GET[$key];
  	} else if (array_key_exists($key, $_POST)) {
  		$val = $_POST[$key];
  	}
  	$v = unescape(trim($val));
  	return $v;
}

function query($conn, $q) {
  debug('Query: '.$q);
  $result = mysql_query($q, $conn);
  if (!$result) {
    die("Query error: " . mysql_error());
  }
  return $result;
}

function mysql_quote($value) {
	return "'" . mysql_real_escape_string($value) . "'";	
}

function debug($msg) {
  if (is_array($msg)) {
    $str = "";
    foreach($msg as $key => $value){
      $str .= "$key=$value ";
    }
  } else {
    $str = $msg;
  }
  error_log($str);
}

// Return eg. 2008-11-21T06:11:55Z0711
//            
function getUTCTimeStamp() {
	date_default_timezone_set('UTC');
	$tiny = substr(microtime(false), 2, 3);
	return date('Y-m-d\TH:i:s\Z0', time()) . $tiny;
}

// Sign a http query string in the array of key-value pairs
// return b64 encoded hmac hash
function sign($a, $apiKey) {
	ksort($a);
	$qs = '';
	$n = count($a);
	$i = 0;
	foreach (array_keys($a) as $key) {
		$qs .= trim($key).'='.trim($a[$key]);
		if (++$i < $n) {
			$qs .= '&';
		}
	}
	
	// the TRUE at the end states we want the raw value, not hexadecimal form
	$hmac = hash_hmac('sha1', utf8_encode($qs), $apiKey, true);
	$hmac = base64_encode($hmac);

	debug('SIGN: ' . $qs . ' H=' . $hmac);

	return $hmac;
		
} // sign an array of query string

function hex2b64 ($hex_str) {
  $bin = pack("H*", $hex_str);
  return base64_encode($bin);
}

function modhex2b64 ($modhex_str) {
  $hex_str = strtr ($modhex_str, "cbdefghijklnrtuv", "0123456789abcdef");
  return hex2b64($hex_str);
}

// $otp: A yubikey OTP
function decryptOTP($otp, $base_url) {
  $url = $base_url . $otp;
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_USERAGENT, "YK-VAL");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_FAILONERROR, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 5);
  $response = curl_exec($ch);
  $error = curl_error ($ch);
  $errno = curl_errno ($ch);
  debug("YK-KSM response: $response errno: " . $errno . " error: " . $error);
  $info = curl_getinfo ($ch);
  debug($info);
  curl_close($ch);

  if (sscanf ($response,
	      "OK counter=%04x high=%02x low=%04x use=%02x",
	      $ret["session_counter"], $ret["high"],
	      $ret["low"], $ret["session_use"]) != 4) {
    return false;
  }
  return $ret;
} // End decryptOTP

// $devId: The first 12 chars from the OTP
function getAuthData($conn, $devId) {
	$tokenId = modhex2b64($devId);
	$stmt = 'SELECT id, client_id, active, counter, '.
	  'sessionUse, low, high, accessed FROM yubikeys WHERE active '.
	  'AND tokenId='.mysql_quote($tokenId);
	$r = query($conn, $stmt);
	if (mysql_num_rows($r) > 0) {
		$row = mysql_fetch_assoc($r);
		mysql_free_result($r);
		return $row;
	}
	return null;
} // End getAuthData

// $clientId: The decimal client identity
function getClientData($conn, $clientId) {
	$stmt = 'SELECT id, secret, chk_time'.
	  ' FROM clients WHERE active AND id='.mysql_quote($clientId);
	$r = query($conn, $stmt);
	if (mysql_num_rows($r) > 0) {
		$row = mysql_fetch_assoc($r);
		mysql_free_result($r);
		return $row;
	}
	return null;
} // End getClientData
?>
