<?php require_once '../yms/appinclude.php';
	  require_once '../yms/yubi_lib.php';

if (!isset($trace)) { $trace = 0; }

$id = getHttpVal('id', 0);
if ($id <= 0) {
	echo 'Client ID is missing';
	exit;
}

$otp = getHttpVal('otp', '');
if ($otp == '') {
	echo 'OTP is missing';
	exit;
}

//// Get Yubikey from DB
//
$devId = substr($otp, 0, 12);
$ad = getAuthData($devId);
debug('<h3>Auth Data from DB</h3>');

if ($ad == null) {
	echo 'Invalid Yubikey '.$devId;
	exit;
} else {
	debug($ad);
}

$k = b64ToModhex($ad['secret']);
//debug('aes key in modhex = '.$k);
$key16 = ModHex::Decode($k);
//debug('aes key in hex = ['.$key16.'], length = '.strlen($key16));

//// Decode OTP from input
//
debug('<h3>From OTP decoded</h3>');
$decoded_token = Yubikey::Decode($otp, $key16);
debug($decoded_token);
if ( ! is_array($decoded_token) ) {
	die ('DECODING FAILED: '.$decoded_token."\n");
}

//// Sanity check key status
//
if ($ad['active'] < 1) {
	die ('The Yubikey is not activated!');
}

// Sanity check client status
//
if ($ad['c_active'] < 1) {
	die ('The Client is not activated!');
}

// Sanity check token ID
//
if (strlen($decoded_token["public_id"]) == 12 ) {
	debug("Token ID OK (".$decoded_token["public_id"].")");
} else { die("TOKEN ID FAILED, ".$decoded_token["public_id"]); }

// Sanity check the OTP
//
if ( strlen($decoded_token["token"]) == 32) {
	debug("OTP len OK (".$decoded_token["token"].")");
} else { die(" OTP len FAILED,".strlen($decoded_token["token"])); }

// Check the session counter
//
$sessionCounter = $decoded_token["session_counter"]; // From the req
$seenSessionCounter = $ad['counter']; // From DB
$scDiff = $seenSessionCounter - $sessionCounter;
if ($scDiff > 0) {
	die("Replayed session counter=".$sessionCounter.', seen='.$seenSessionCounter);
} else {
	debug("Counter OK (".$sessionCounter.")");
}

$hi = $decoded_token["high"]; // From the req
$seenHi = $ad['high']; // From DB
$hiDiff = $seenHi - $hi;
if ($scDiff == 0 && $hiDiff > 0) {
	die("Replayed high counter=".$hi.', seen='.$seenHi);
} else {
	debug("High counter OK (".$hi.")");
}

$lo = $decoded_token["low"]; // From the req
$seenLo = $ad['low']; // From DB
$loDiff = $seenLo - $lo;
if ($scDiff == 0 && $loDiff >= 0) {
	die("Replayed low counter=".$lo.', seen='.$seenLo);
} else {
	debug("Low counter OK (".$lo.")");
}

echo 'Validation OK for Yubikey: '.$decoded_token["public_id"];

if (updDB($ad['id'], $decoded_token)) {
	debug('Validation database updated');	
}

function debug($msg, $exit=false) {
	global $trace;
	if ($trace) {
		if (is_array($msg)) {
			echo '<pre>';
			print_r($msg);
			echo '</pre>';
		} else {
			echo $msg;
		}
		echo "<p>\n";
	}
	if ($exit) {
		die ('<font color=red><h4>Exit</h4></font>');
	}
}

function updDB($id, $new) {
	$stmt = 'UPDATE yubikeys SET '.
		'accessed=NOW(),'.
		'counter='.$new['session_counter'].','.
		'low='.$new['low'].','.
		'high='.$new['high'].
		' WHERE id='.$id;
	if (!query($stmt)) {
		$err = 'Failed to update validation data of key: '.$id.' by '.$stmt;
		debug($err);
		writeLog($err);
		return false;
	}
	return true;
}
?>
