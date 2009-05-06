<?php
require_once 'ykval-common.php';
require_once 'ykval-config.php';

$apiKey = '';

header("content-type: text/plain");

debug("Request: " . $_SERVER['QUERY_STRING']);

$conn = mysql_connect($baseParams['__DB_HOST__'],
		      $baseParams['__DB_USER__'],
		      $baseParams['__DB_PW__']);
if (!$conn) {
  sendResp(S_BACKEND_ERROR, $apiKey);
  exit;
}
if (!mysql_select_db($baseParams['__DB_NAME__'], $conn)) {
  sendResp(S_BACKEND_ERROR, $apiKey);
  exit;
}

//// Extract values from HTTP request
//
$h = getHttpVal('h', '');
$client = getHttpVal('id', 0);
$otp = getHttpVal('otp', '');
$otp = strtolower($otp);

//// Get Client info from DB
//
if ($client <= 0) {
  debug('Client ID is missing');
  sendResp(S_MISSING_PARAMETER, $apiKey);
  exit;
}

$cd = getClientData($conn, $client);
if ($cd == null) {
  debug('Invalid client id ' . $client);
  sendResp(S_NO_SUCH_CLIENT);
  exit;
}
debug("Client data:", $cd);

//// Check client signature
//
$apiKey = base64_decode($cd['secret']);

if ($h != '') {
  // Create the signature using the API key
  $a = array ();
  $a['id'] = $client;
  $a['otp'] = $otp;
  $hmac = sign($a, $apiKey);

  // Compare it
  if ($hmac != $h) {
    debug('client hmac=' . $h . ', server hmac=' . $hmac);
    sendResp(S_BAD_SIGNATURE, $apiKey);
    exit;
  }
}

//// Sanity check OTP
//
if ($otp == '') {
  debug('OTP is missing');
  sendResp(S_MISSING_PARAMETER, $apiKey);
  exit;
}
if (strlen($otp) <= TOKEN_LEN) {
  debug('Too short OTP: ' . $otp);
  sendResp(S_BAD_OTP, $apiKey);
  exit;
}

//// Which YK-KSM should we talk to?
//
$urls = otp2ksmurls ($otp, $client);
if (!is_array($urls)) {
  sendResp(S_BACKEND_ERROR, $apiKey);
  exit;
}

//// Decode OTP from input
//
$otpinfo = KSMdecryptOTP($urls);
if (!is_array($otpinfo)) {
  sendResp(S_BAD_OTP, $apiKey);
  exit;
}
debug("Decrypted OTP:", $otpinfo);

//// Get Yubikey from DB
//
$devId = substr($otp, 0, strlen ($otp) - TOKEN_LEN);
$ad = getAuthData($conn, $devId);
if (!is_array($ad)) {
  debug('Discovered Yubikey ' . $devId);
  addNewKey($conn, $devId);
  $ad = getAuthData($conn, $devId);
  if (!is_array($ad)) {
    debug('Invalid Yubikey ' . $devId);
    sendResp(S_BACKEND_ERROR, $apiKey);
    exit;
  }
}
debug("Auth data:", $ad);
if ($ad['active'] != 1) {
  debug('De-activated Yubikey ' . $devId);
  sendResp(S_BAD_OTP, $apiKey);
  exit;
}

//// Check the session counter
//
$sessionCounter = $otpinfo["session_counter"]; // From the req
$seenSessionCounter = $ad['counter']; // From DB
if ($sessionCounter < $seenSessionCounter) {
  debug("Replay, session counter, seen=" . $seenSessionCounter .
	" this=" . $sessionCounter);
  sendResp(S_REPLAYED_OTP, $apiKey);
  exit;
}

//// Check the session use
//
$sessionUse = $otpinfo["session_use"]; // From the req
$seenSessionUse = $ad['sessionUse']; // From DB
if ($sessionCounter == $seenSessionCounter && $sessionUse <= $seenSessionUse) {
  debug("Replay, session use, seen=" . $seenSessionUse .
	' this=' . $sessionUse);
  sendResp(S_REPLAYED_OTP, $apiKey);
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
    if (0) {
      sendResp(S_DELAYED_OTP, $apiKey);
      exit;
    }
  }
}

sendResp(S_OK, $apiKey);
?>
