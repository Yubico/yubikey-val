<?php
require_once 'common.php';
require_once 'config.php';

header("content-type: text/plain");

debug("Request: " . $_SERVER['QUERY_STRING']);

$conn = mysql_connect($baseParams['__DB_HOST__'],
		      $baseParams['__DB_USER__'],
		      $baseParams['__DB_PW__'])
  or die('Could not connect to database: ' . mysql_error());
mysql_select_db($baseParams['__DB_NAME__'], $conn)
  or die('Could not select database');

//// Extract values from HTTP request
//
$client = getHttpVal('id', 0);
if ($client <= 0) {
	debug('Client ID is missing');
	sendResp(S_MISSING_PARAMETER);
	exit;
}

$otp = getHttpVal('otp', '');
if ($otp == '') {
	debug('OTP is missing');
	sendResp(S_MISSING_PARAMETER);
	exit;
}
$otp = strtolower($otp);

//// Get Client info from DB
//
$cd = getClientData($conn, $client);
if ($cd == null) {
	debug('Invalid client id ' . $client);
	sendResp(S_NO_SUCH_CLIENT);
	exit;
}
debug($cd);

//// Check client signature
//
$apiKey = base64_decode($cd['secret']);
$h = getHttpVal('h', '');

if ($cd['chk_sig'] && $h == '') {
	debug('Signature missing');
	sendResp(S_MISSING_PARAMETER);
	exit;
} else if ($cd['chk_sig'] || $h != '') {
	// Create the signature using the API key
	$a = array ();
	$a['id'] = $client;
	$a['otp'] = $otp;
	$hmac = sign($a, $apiKey);

	// Compare it
	if ($hmac != $h) {
		debug('client hmac=' . $h . ', server hmac=' . $hmac);
		sendResp(S_BAD_SIGNATURE);
		exit;
	}
}

//// Decode OTP from input
//
$otpinfo = decryptOTP($otp, $baseParams['__YKKMS_URL__']);
if (!is_array($otpinfo)) {
	sendResp(S_BACKEND_ERROR);
	exit;
}
debug($otpinfo);

//// Get Yubikey from DB
//
$devId = substr($otp, 0, DEVICE_ID_LEN);
$ad = getAuthData($conn, $devId);
if (!is_array($ad)) {
	debug('Invalid Yubikey ' . $devId);
	sendResp(S_BAD_OTP);
	exit;
}
debug($ad);

//// Check the session counter
//
$sessionCounter = $otpinfo["session_counter"]; // From the req
$seenSessionCounter = $ad['counter']; // From DB
if ($sessionCounter < $seenSessionCounter) {
	debug("Replay, session counter, seen=" . $seenSessionCounter .
	      " this=" . $sessionCounter);
	sendResp(S_REPLAYED_OTP);
	exit;
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
}

//// Valid OTP, update database
//
$stmt = 'UPDATE yubikeys SET accessed=NOW()' .
  ', counter=' .$otpinfo['session_counter'] .
  ', sessionUse=' . $otpinfo['session_use'] .
  ', low=' . $otpinfo['low'] .
  ', high=' . $otpinfo['high'] .
  ' WHERE id=' . $ad['id'];
query($conn, $stmt);

//// Check the time stamp
//
if ($sessionCounter == $seenSessionCounter && $sessionUse > $seenSessionUse) {
  $ts = ($otpinfo['high'] << 16) + $otpinfo['low'];
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

function sendResp($status) {
	global $apiKey;

	if ($status == null) {
		$status = S_BACKEND_ERROR;
	}

	$a['status'] = $status;
	$a['t'] = getUTCTimeStamp();
	$h = sign($a, $apiKey);

	echo "h=" . $h . "\r\n";
	echo "t=" . ($a['t']) . "\r\n";
	echo "status=" . ($a['status']) . "\r\n";
	echo "\r\n";

} // End sendResp
?>
