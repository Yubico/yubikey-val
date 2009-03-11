<?php
require_once 'common.php';

header("content-type: text/plain");

if (!isset ($trace)) {
	$trace = 0;
}

//// Extract values from HTTP request
//
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
$otp = strtolower($otp);

//// Get Client info from DB
//
$cd = getClientData($client);
if ($cd == null) {
	debug('Invalid client id ' . $client);
	sendResp(S_NO_SUCH_CLIENT, $client);
	exit;
}
debug($cd);

//// Check client signature
//
$apiKey = base64_decode($cd['secret']);
$h = getHttpVal('h', '');

if ($cd['chk_sig'] && $h == '') {
	sendResp(S_MISSING_PARAMETER, 'h');
	debug('Signature missing');
	exit;
} else if ($cd['chk_sig'] || $h != '') {
	// Create the signature using the API key
	$a = array ();
	$a['id'] = $client;
	$a['otp'] = $otp;
	$hmac = sign($a, $apiKey);

	// Compare it
	if ($hmac != $h) {
		sendResp(S_BAD_SIGNATURE);
		debug('client hmac=' . $h . ', server hmac=' . $hmac);
		exit;
	}
	debug('signature ok h=' . $h);
}

//// Get Yubikey from DB
//
$devId = substr($otp, 0, 12);
$ad = getAuthData($devId);

if ($ad == null) {
	debug('Invalid Yubikey ' . $devId);
	sendResp(S_BAD_OTP, $otp);
	exit;
} else {
	debug($ad);
}

$k = b64ToModhex($ad['secret']);
//debug('aes key in modhex = '.$k);
$key16 = ModHex :: Decode($k);
//debug('aes key in hex = ['.$key16.'], length = '.strlen($key16));

//// Decode OTP from input
//
debug('OTP validation req:');
$otpinfo = Yubikey :: Decode($otp, $key16);
debug($otpinfo);
if (!is_array($otpinfo)) {
	sendResp(S_BAD_OTP, $otp);
	exit;
}

//// Check the session counter
//
$sessionCounter = $otpinfo["session_counter"]; // From the req
$seenSessionCounter = $ad['counter']; // From DB
if ($sessionCounter < $seenSessionCounter) {
	debug("Replay, session counter, seen=" . $seenSessionCounter .
	      " this=" . $sessionCounter);
	sendResp(S_REPLAYED_OTP);
	exit;
} else {
	debug("Session counter OK, seen=" . $seenSessionCounter .
	      " this=" . $sessionCounter);
}

//// Check the session use
//
$sessionUse = $otpinfo["session_use"]; // From the req
$seenSessionUse = $ad['sessionUse']; // From DB
if ($sessionCounter == $seenSessionCounter && $sessionUse <= $seenSessionUse) {
	debug("Replay, session use, seen=" . $seenSessionUse .
	      ' this=' . $sessionUse);
	sendResp(S_REPLAYED_OTP);
	exit;
} else {
	debug("Session use OK, seen=" . $seenSessionUse .
	      ' this=' . $sessionUse);
}

updateDB($ad['id'], $otpinfo['session_counter'], $otpinfo['session_use'],
	 $otpinfo['high'], $otpinfo['low']);

//// Check the time stamp
//
if ($sessionCounter == $seenSessionCounter && $sessionUse > $seenSessionUse) {
  $ts = $otpinfo['timestamp'];
  $seenTs = ($ad['high'] << 16) + $ad['low'];
  $tsDiff = $ts - $seenTs;
  $tsDelta = $tsDiff * TS_SEC;

  //// Check the real time
  //
  $lastTime = strtotime($ad['accessed']);
  $now = time();
  $elapsed = $now - $lastTime;
  $deviation = abs($elapsed - $tsDelta);
  $percent = $deviation/$elapsed;
  debug("Timestamp seen=" . $seenTs . " this=" . $ts .
	" delta=" . $tsDiff . ' secs=' . $tsDelta .
	' accessed=' . $lastTime .' (' . $ad['accessed'] . ') now='
	. $now . ' (' . strftime("%Y-%m-%d %H:%M:%S", $now)
	. ') elapsed=' . $elapsed .
	' deviation=' . $deviation . ' secs or '.
	round(100*$percent) . '%');
  if ($deviation > TS_ABS_TOLERANCE && $percent > TS_REL_TOLERANCE) {
    debug("OTP failed phishing test");
    if ($ad['chk_time']) {
      sendResp(S_DELAYED_OTP);
      exit;
    }
  }
}

sendResp(S_OK);

//////////////////////////
// 		Functions
//////////////////////////

function sendResp($status, $info = null) {
	global $ad, $apiKey;

	if ($status == null) {
		$status = S_BACKEND_ERROR;
	}

	$a['status'] = $status;
	#$a['info'] = $info;
	$a['t'] = getUTCTimeStamp();
	$h = sign($a, $apiKey);

	echo "h=" . $h . "\r\n";
	echo "t=" . ($a['t']) . "\r\n";
	echo "status=" . ($a['status']) . "\r\n";
	if ($a['info'] != null) {
		echo "info=" . ($a['info']) . "\r\n";
	}
	echo "\r\n";

} // End sendResp

function updateDB($id, $session_counter, $session_use, $ts_high, $ts_low) {
  $stmt = 'UPDATE yubikeys SET ' .
    'accessed=NOW(),' .
    'counter=' . $session_counter . ',' .
    'sessionUse=' . $session_use . ',' .
    'low=' . $ts_low . ',' .
    'high=' . $ts_high .
    ' WHERE id=' . $id;
  query($stmt);
}
?>
