<?php require_once '../yms/appinclude.php';
	  require_once '../yms/yubi_lib.php';

define('S_OK', 'OK');
define('S_BAD_OTP', 'BAD_OTP');
define('S_BAD_CLIENT', 'BAD_CLIENT'); // New, added by paul 20080920
define('S_REPLAYED_OTP', 'REPLAYED_OTP');
define('S_BAD_SIGNATURE', 'BAD_SIGNATURE');
define('S_MISSING_PARAMETER', 'MISSING_PARAMETER');
//define('S_NO_SUCH_CLIENT', 'NO_SUCH_CLIENT'); // Deprecated by paul 20080920
define('S_OPERATION_NOT_ALLOWED', 'OPERATION_NOT_ALLOWED');
define('S_BACKEND_ERROR', 'BACKEND_ERROR');

header("content-type: text/plain");

if (!isset($trace)) { $trace = 0; }

$client = getHttpVal('id', 0);
if ($client <= 0) {
	debug('Client ID is missing');
	sendResp(S_MISSING_PARAMETER, 'id');
	exit;
}

$otp = getHttpVal('otp', '');
if ($otp == '') {
	debug('OTP is missing');
	sendResp(S_MISSING_PARAMETER, 'otp');
	exit;
}

//// Get Yubikey from DB
//
$devId = substr($otp, 0, 12);
$ad = getAuthData($devId);
debug('Auth Data from DB:');

if ($ad == null) {
	debug('Invalid Yubikey '.$devId);
	sendResp(S_BAD_OTP, $otp);
	exit;
} else {
	debug($ad);
}

//// Check the client ID
//
if ($ad['client_id'] != $client) {
	debug('Client-'.$client.' is not the owner of the Yubikey! The key will be suspended with excessive failed attempts.');
	sendResp(S_BAD_CLIENT, 'Not owner of the Yubikey');
	exit;
}

$k = b64ToModhex($ad['secret']);
//debug('aes key in modhex = '.$k);
$key16 = ModHex::Decode($k);
//debug('aes key in hex = ['.$key16.'], length = '.strlen($key16));

//// Decode OTP from input
//
debug('From the OTP validation request:');
$decoded_token = Yubikey::Decode($otp, $key16);
debug($decoded_token);
if ( ! is_array($decoded_token) ) {
	sendResp(S_BAD_OTP, $otp);
	exit;
}

//// Sanity check key status
//
if ($ad['active'] < 1) {
	sendResp(S_BAD_OTP, 'Suspended Yubikey');
	exit;
}

//// Sanity check client status
//
if ($ad['c_active'] < 1) {
	sendResp(S_BAD_CLIENT, 'Suspended client');
	exit;
}

//// Sanity check token ID
//
if (strlen($decoded_token["public_id"]) == 12 ) {
	debug("Token ID OK (".$decoded_token["public_id"].")");
} else { 
	debug("TOKEN ID FAILED, ".$decoded_token["public_id"]); 
	sendResp(S_BAD_OTP, $otp);
	exit;
}

//// Sanity check the OTP
//
if ( strlen($decoded_token["token"]) != 32) {
	debug("Wrong OTP length,".strlen($decoded_token["token"]));
	sendResp(S_BAD_OTP, $otp);
	exit;	 
}

//// Check the session counter
//
$sessionCounter = $decoded_token["session_counter"]; // From the req
$seenSessionCounter = $ad['counter']; // From DB
$scDiff = $seenSessionCounter - $sessionCounter;
if ($scDiff > 0) {
	debug("Replayed session counter=".$sessionCounter.', seen='.$seenSessionCounter);
	sendResp(S_REPLAYED_OTP, 'session counter');
	exit;
} else {
	debug("Session counter OK (".$sessionCounter.")");
}

//// Check the high counter
//
$hi = $decoded_token["high"]; // From the req
$seenHi = $ad['high']; // From DB
$hiDiff = $seenHi - $hi;
if ($scDiff == 0 && $hiDiff > 0) {
	debug("Replayed hi counter=".$hi.', seen='.$seenHi);
	sendResp(S_REPLAYED_OTP, 'hi counter');
	exit;
} else {
	debug("Hi counter OK (".$hi.")");
}

//// Check the low counter
//
$lo = $decoded_token["low"]; // From the req
$seenLo = $ad['low']; // From DB
$loDiff = $seenLo - $lo;
if ($scDiff == 0 && $hiDiff == 0 && $loDiff >= 0) {
	debug("Replayed low counter=".$lo.', seen='.$seenLo);
	sendResp(S_REPLAYED_OTP, 'lo counter');
	exit;
} else {
	debug("Lo counter OK (".$lo.")");
}

//// Update the DB only upon validation success
//
if (updDB($ad['id'], $decoded_token)) {
	debug('Validation database updated');	
	sendResp(S_OK);
} else {
	debug('Failed to update validation database');
	sendResp(S_BACKEND_ERROR);
}

//////////////////////////
// 		Functions
//////////////////////////

function sendResp($status, $info=null) {
	global $ad;

	if ($status == null) {
		$status = S_BACKEND_ERROR;
	}

	date_default_timezone_set('UTC');
	$timestamp = date('Y-m-d\TH:i:s\ZZ', time());

	//// Prepare the response to the user
	//
	$respParams = 'status='.$status.'&t='.$timestamp;

	// Generate the signature
	debug('API key: '.$ad['c_secret']); // API key of the client
	$apiKey = base64_decode($ad['c_secret']);
	debug('Signing: '.$respParams);
	// the TRUE at the end states we want the raw value, not hexadecimal form
	$hmac = hash_hmac('sha1', utf8_encode($respParams), $apiKey, true);
	//outputToFile('hmac', $hmac, "b");
	// now take that byte value and base64 encode it
	$hmac = base64_encode($hmac);
	debug('h: '.$hmac);

	echo 'h='.$hmac.PHP_EOL;
	echo 't='.$timestamp.PHP_EOL;
	if ($info != null) {
		echo 'info='.$info.PHP_EOL;
	}
	echo 'status='.$status.PHP_EOL.PHP_EOL;

} // End sendResp

function debug($msg, $exit=false) {
	global $trace;
	if ($trace) {
		if (is_array($msg)) {
			print_r($msg);
		} else {
			echo 'debug> '.$msg;
		}
		echo "\n";
	}
	if ($exit) {
		die ('<font color=red><h4>Exit</h4></font>');
	}
}

function updDB($keyid, $new) {
	$stmt = 'UPDATE yubikeys SET '.
		'accessed=NOW(),'.
		'counter='.$new['session_counter'].','.
		'low='.$new['low'].','.
		'high='.$new['high'].
		' WHERE id='.$keyid;
	if (!query($stmt)) {
		$err = 'Failed to update validation data of key: '.$keyid.' by '.$stmt;
		debug($err);
		writeLog($err);
		return false;
	}
	return true;
}

function outputToFile($outFname, $content, $mode, $append=false) {
    $out = fopen($outFname, ($append ? "a" : "w"));
    fwrite($out, $content);
    fclose($out);
}
?>
