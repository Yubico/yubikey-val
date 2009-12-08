<?php
require_once 'ykval-common.php';
require_once 'ykval-config.php';
require_once 'ykval-synclib.php';

$apiKey = '';

header("content-type: text/plain");

debug("Request: " . $_SERVER['QUERY_STRING']);

$protocol_version=2.0;

$conn = mysql_connect($baseParams['__YKVAL_DB_HOST__'],
		      $baseParams['__YKVAL_DB_USER__'],
		      $baseParams['__YKVAL_DB_PW__']);
if (!$conn) {
  sendResp(S_BACKEND_ERROR, $apiKey);
  exit;
}
if (!mysql_select_db($baseParams['__YKVAL_DB_NAME__'], $conn)) {
  sendResp(S_BACKEND_ERROR, $apiKey);
  exit;
}

//// Extract values from HTTP request
//
$h = getHttpVal('h', '');
$client = getHttpVal('id', 0);
$otp = getHttpVal('otp', '');
$otp = strtolower($otp);
$timestamp = getHttpVal('timestamp', 0);
if ($protocol_version>=2.0) {

  $sl = getHttpVal('sl', '');
  if (strcasecmp($sl, 'fast')==0) $sl=$baseParams['__YKVAL_SYNC_FAST_LEVEL__'];
  if (strcasecmp($sl, 'secure')==0) $sl=$baseParams['__YKVAL_SYNC_SECURE_LEVEL__'];
  if (!$sl) $sl=$baseParams['__YKVAL_SYNC_DEFAULT_LEVEL__'];

  $timeout = getHttpVal('timeout', '');  

  if (!$timeout) $timeout=$baseParams['__YKVAL_SYNC_DEFAULT_TIMEOUT__'];
 }

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
  // include timestamp,sl and timeout in signature if it exists
  if ($timestamp) $a['timestamp'] = $timestamp;
  if ($sl) $a['sl'] = $sl;
  if ($timeout) $a['timeout'] = $timeout;
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
$r=query($conn, $stmt);

$stmt = 'SELECT accessed FROM yubikeys WHERE id=' . $ad['id'];
$r=query($conn, $stmt);
if (mysql_num_rows($r) > 0) {
  $row = mysql_fetch_assoc($r);
  mysql_free_result($r);
  $modified=DbTimeToUnix($row['accessed']);
 }  
 else {
   $modified=0;
 }

//// Queue sync requests
$sync = new SyncLib();
// We need the modifed value from the DB
$stmp = 'SELECT accessed FROM yubikeys WHERE id=' . $ad['id'];
query($conn, $stmt);

$otpParams=array('modified'=>$modified, 
		 'otp'=>$otp, 
		 'yk_identity'=>$devId, 
		 'yk_counter'=>$otpinfo['session_counter'], 
		 'yk_use'=>$otpinfo['session_use'], 
		 'yk_high'=>$otpinfo['high'], 
		 'yk_low'=>$otpinfo['low']);

$localParams=array('modified'=>DbTimeToUnix($ad['accessed']), 
		   'otp'=>'', 
		   'yk_identity'=>$devId, 
		   'yk_counter'=>$ad['counter'], 
		   'yk_use'=>$ad['sessionUse'], 
		   'yk_high'=>$ad['high'], 
		   'yk_low'=>$ad['low']);


if (!$sync->queue($otpParams, $localParams)) {
  debug("ykval-verify:critical:failed to queue sync requests");
  sendResp(S_BACKEND_ERROR, $apiKey);
  exit;
 }

$nr_servers=$sync->getNumberOfServers();
$req_answers=ceil($nr_servers*$sl/100);
if ($req_answers>0) {
  $syncres=$sync->sync($req_answers, $timeout);
  $nr_answers=$sync->getNumberOfAnswers();
  $nr_valid_answers=$sync->getNumberOfValidAnswers();
  $sl_success_rate=floor($nr_valid_answers / $nr_servers * 100);
  
 } else {
  $nr_answers=0;
  $nr_valid_answers=0;
  $sl_success_rate=0;
 }
debug("ykval-verify:notice:synclevel=" . $sl .
      " nr servers=" . $nr_servers .
      " req answers=" . $req_answers .
      " answers=" . $nr_answers .
      " valid answers=" . $nr_valid_answers .
      " sl success rate=" . $sl_success_rate .
      " timeout=" . $timeout);

if($syncres==False) {
  /* sync returned false, indicating that 
   either at least 1 answer marked OTP as invalid or
   there were not enough answers */
  debug("ykval-verify:notice:Sync failed");
  if ($nr_valid_answers!=$nr_answers) {
    sendResp(S_REPLAYED_OTP, $apiKey);
    exit;
  } else {
    $extra=array('sl'=>$sl_success_rate);
    sendResp(S_NOT_ENOUGH_ANSWERS, $apiKey, $extra);
    exit;
  }
 }


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

  // Time delta server might verify multiple OTPS in a row. In such case validation server doesn't 
  // have time to tick a whole second and we need to avoid division by zero. 
  if ($elapsed != 0) {
    $percent = $deviation/$elapsed;
  } else {
    $percent = 1;
  }
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

/* Construct response parameters */
$extra=array();
if ($protocol_version>=2.0) {
  $extra['otp']=$otp;
  $extra['sl'] = $sl_success_rate;
 }
if ($timestamp==1){
  $extra['timestamp'] = ($otpinfo['high'] << 16) + $otpinfo['low'];
  $extra['sessioncounter'] = $sessionCounter;
  $extra['sessionuse'] = $sessionUse;
 }

sendResp(S_OK, $apiKey, $extra);

?>
