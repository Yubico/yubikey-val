<?php

require_once('ykval-log.php');

define('S_OK', 'OK');
define('S_BAD_OTP', 'BAD_OTP');
define('S_REPLAYED_OTP', 'REPLAYED_OTP');
define('S_DELAYED_OTP', 'DELAYED_OTP');
define('S_BAD_SIGNATURE', 'BAD_SIGNATURE');
define('S_MISSING_PARAMETER', 'MISSING_PARAMETER');
define('S_NO_SUCH_CLIENT', 'NO_SUCH_CLIENT');
define('S_OPERATION_NOT_ALLOWED', 'OPERATION_NOT_ALLOWED');
define('S_BACKEND_ERROR', 'BACKEND_ERROR');
define('S_NOT_ENOUGH_ANSWERS', 'NOT_ENOUGH_ANSWERS');
define('S_REPLAYED_REQUEST', 'REPLAYED_REQUEST');


define('TS_SEC', 1/8);
define('TS_REL_TOLERANCE', 0.3);
define('TS_ABS_TOLERANCE', 20);

define('TOKEN_LEN', 32);

global $ykval_common_log;
$ykval_common_log = new Log('ykval-common');

function logdie ($str)
{
  global $ykval_common_log;
  $ykval_common_log->log(LOG_EMERG, $str);
  die($str . "\n");
}

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

function debug() {
  $str = "";
  foreach (func_get_args() as $msg)
    {
      if (is_array($msg)) {
	foreach($msg as $key => $value){
	  $str .= "$key=$value ";
	}
      } else {
	$str .= $msg . " ";
      }
    }
  global $ykval_common_log;
  $ykval_common_log->log(LOG_DEBUG, $str);
}

// Return eg. 2008-11-21T06:11:55Z0711
//            
function getUTCTimeStamp() {
	date_default_timezone_set('UTC');
	$tiny = substr(microtime(false), 2, 3);
	return date('Y-m-d\TH:i:s\Z0', time()) . $tiny;
}

# NOTE: When we evolve to using general DB-interface, this functinality
# should be moved there. 
function DbTimeToUnix($db_time)
{
  $unix=strptime($db_time, '%F %H:%M:%S');
  return mktime($unix[tm_hour], $unix[tm_min], $unix[tm_sec], $unix[tm_mon]+1, $unix[tm_mday], $unix[tm_year]+1900);
}

function UnixToDbTime($unix)
{
  return date('Y-m-d H:i:s', $unix);
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
function KSMdecryptOTP($urls) {
  $ret = array();
  $response = retrieveURLasync ($urls);
  if ($response) {
    debug("YK-KSM response: " . $response);
  }
  if (sscanf ($response,
	      "OK counter=%04x low=%04x high=%02x use=%02x",
	      $ret["session_counter"], $ret["low"], $ret["high"],
	      $ret["session_use"]) != 4) {
    return false;
  }
  return $ret;
} // End decryptOTP

function sendResp($status, $apiKey = '', $extra = null) {
  if ($status == null) {
    $status = S_BACKEND_ERROR;
  }

  $a['status'] = $status;
  $a['t'] = getUTCTimeStamp();
  if ($extra){
    foreach ($extra as $param => $value) $a[$param] = $value;
  }
  $h = sign($a, $apiKey);

  echo "h=" . $h . "\r\n";
  echo "t=" . ($a['t']) . "\r\n";
  if ($extra){
    foreach ($extra as $param => $value) {
      echo $param . "=" . $value . "\r\n";
    }
  }
  echo "status=" . ($a['status']) . "\r\n";
  echo "\r\n";
}
?>
