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
define('TS_SEC', 0.119);
define('TS_REL_TOLERANCE', 0.3);
define('TS_ABS_TOLERANCE', 20);
define('DEVICE_ID_LEN', 12);

require_once 'config.php';

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

///////////////////// 
//
// DB Related
// 
///////////////////

$conn = mysql_connect($baseParams['__DB_HOST__'],
		      $baseParams['__DB_USER__'],
		      $baseParams['__DB_PW__'])
  or die('Could not connect to database: ' . mysql_error());
mysql_select_db($baseParams['__DB_NAME__'], $conn)
  or die('Could not select database');

function query($q) {
	global $conn;
	debug('Query: '.$q);
	$result = mysql_query($q, $conn);
	if (!$result) {
		$err = "Invalid query -- $q -- ";
		writeLog($err);
		die($err . mysql_error());
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
function sign($a, $apiKey, $debug=false) {
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

	// Generate the signature
//	debug('API key: '.base64_encode($apiKey)); // API key of the client
	debug('SIGN: '.$qs);
	
	// the TRUE at the end states we want the raw value, not hexadecimal form
	$hmac = hash_hmac('sha1', utf8_encode($qs), $apiKey, true);
	$hmac = base64_encode($hmac);
	if ($debug) {
		debug('h='.$hmac);		
	}
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

// decryptOTP using YK-KSM
function decryptOTP($otp) {
  global $baseParams;

  $url = $baseParams['__YKKMS_URL__'] . $otp;
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_USERAGENT, "YK-VAL");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  $response = curl_exec($ch);
  curl_close($ch);

  debug($response);

  if (sscanf ($response,
	      "OK counter=%04x high=%02x low=%04x use=%02x",
	      $ret["session_counter"], $ret["high"],
	      $ret["low"], $ret["session_use"]) != 4) {
    return false;
  }
  return $ret;
} // End decryptOTP

// $devId: The first 12 chars from the OTP
function getAuthData($devId) {
	$tokenId = modhex2b64($devId);
	$stmt = 'SELECT id, client_id, active, counter, '.
	  'sessionUse, low, high, accessed FROM yubikeys WHERE active '.
	  'AND tokenId='.mysql_quote($tokenId);
	$r = query($stmt);
	if (mysql_num_rows($r) > 0) {
		$row = mysql_fetch_assoc($r);
		mysql_free_result($r);
		return $row;
	}
	return null;
} // End getAuthData

// $clientId: The decimal client identity
function getClientData($clientId) {
	$stmt = 'SELECT secret, chk_sig, chk_owner, chk_time'.
	  ' FROM clients WHERE active AND id='.mysql_quote($clientId);
	$r = query($stmt);
	if (mysql_num_rows($r) > 0) {
		$row = mysql_fetch_assoc($r);
		mysql_free_result($r);
		return $row;
	}
	return null;
} // End getClientData
?>
