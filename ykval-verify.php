<?php
require_once 'ykval-common.php';
require_once 'ykval-config.php';
require_once 'ykval-synclib.php';

$apiKey = '';

header("content-type: text/plain");

debug("Request: " . $_SERVER['QUERY_STRING']);

/* Detect protocol version */
if (preg_match("/\/wsapi\/([0-9]*)\.([0-9]*)\//", $_SERVER['REQUEST_URI'], $out)) {
  $protocol_version=$out[1]+$out[2]*0.1;
 } else {
  $protocol_version=1.0;
 }
debug("found protocol version " . $protocol_version);

/* Initialize the sync library. Strive to use this instead of custom DB requests, 
 custom comparisons etc */ 
$sync = new SyncLib();
if (! $sync->isConnected()) {
  sendResp(S_BACKEND_ERROR, $apiKey);
  exit;
 }

/* Extract values from HTTP request
 */
$h = getHttpVal('h', '');
$client = getHttpVal('id', 0);
$otp = getHttpVal('otp', '');
$otp = strtolower($otp);
$timestamp = getHttpVal('timestamp', 0);
if ($protocol_version>=2.0) {
  
  $sl = getHttpVal('sl', '');
  $timeout = getHttpVal('timeout', '');  
  $nonce = getHttpVal('nonce', '');

  /* Nonce is required from protocol 2.0 */
  if(!$nonce || strlen($nonce)<16) {
    debug('Protocol version >= 2.0. Nonce is missing');
    sendResp(S_MISSING_PARAMETER, $apiKey);
    exit;
  }
 }

if ($protocol_version<2.0) {
  /* We need to create a nonce manually here */
  $nonce = md5(uniqid(rand())); 
  debug('protocol version below 2.0. Created nonce ' . $nonce);
 }
//// Get Client info from DB
//
if ($client <= 0) {
  debug('Client ID is missing');
  sendResp(S_MISSING_PARAMETER, $apiKey);
  exit;
}

$cd=$sync->getClientData($client);
if(!$cd) {
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
  if ($nonce) $a['nonce'] = $nonce;

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
$yk_publicname=$devId;
$localParams = $sync->getLocalParams($yk_publicname);
if (!$localParams) {
  debug('Invalid Yubikey ' . $yk_publicname);
  sendResp(S_BACKEND_ERROR, $apiKey);
  exit;
 }

debug("Auth data:", $localParams);
if ($localParams['active'] != 1) {
  debug('De-activated Yubikey ' . $devId);
  sendResp(S_BAD_OTP, $apiKey);
  exit;
}

/* Build OTP params */

$otpParams=array('modified'=>time(), 
		 'otp'=>$otp, 
		 'nonce'=>$nonce,
		 'yk_publicname'=>$devId, 
		 'yk_counter'=>$otpinfo['session_counter'], 
		 'yk_use'=>$otpinfo['session_use'], 
		 'yk_high'=>$otpinfo['high'], 
		 'yk_low'=>$otpinfo['low']);


/* First check if OTP is seen with the same nonce, in such case we have an replayed request */
if ($sync->countersEqual($localParams, $otpParams) &&
    $localParams['nonce']==$otpParams['nonce']) {
  debug('Replayed request');
  sendResp(S_REPLAYED_REQUEST, $apikey);
  exit;
 }

/* Check the OTP counters against local db */    
if ($sync->countersHigherThanOrEqual($localParams, $otpParams)) {
  $sync->log('warning', 'replayed OTP: Local counters higher');
  $sync->log('warning', 'replayed OTP: Local counters ', $localParams);
  $sync->log('warning', 'replayed OTP: Otp counters ', $otpParams);
  sendResp(S_REPLAYED_OTP, $apiKey);
  exit;
 }

/* Valid OTP, update database. */

if(!$sync->updateDbCounters($otpParams)) {
  sendResp(S_BACKEND_ERROR, $apiKey);
  exit;
 }

/* Queue sync requests */

if (!$sync->queue($otpParams, $localParams)) {
  debug("ykval-verify:critical:failed to queue sync requests");
  sendResp(S_BACKEND_ERROR, $apiKey);
  exit;
 }

/* Change default protocol "strings" to numeric values */
if (strcasecmp($sl, 'fast')==0) $sl=$baseParams['__YKVAL_SYNC_FAST_LEVEL__'];
if (strcasecmp($sl, 'secure')==0) $sl=$baseParams['__YKVAL_SYNC_SECURE_LEVEL__'];
if (!$sl) $sl=$baseParams['__YKVAL_SYNC_DEFAULT_LEVEL__'];
if (!$timeout) $timeout=$baseParams['__YKVAL_SYNC_DEFAULT_TIMEOUT__'];

$nr_servers=$sync->getNumberOfServers();
$req_answers=ceil($nr_servers*$sl/100.0);
if ($req_answers>0) {
  $syncres=$sync->sync($req_answers, $timeout);
  $nr_answers=$sync->getNumberOfAnswers();
  $nr_valid_answers=$sync->getNumberOfValidAnswers();
  $sl_success_rate=floor(100.0 * $nr_valid_answers / $nr_servers);
  
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

/* Recreate parameters to make phising test work out 
 TODO: use timefunctionality in deltatime library instead */
$sessionCounter = $otpParams['yk_counter'];
$sessionUse = $otpParams['yk_use'];
$seenSessionCounter = $localParams['yk_counter'];
$seenSessionUse = $localParams['yk_use'];

$ad['high']=$localParams['yk_high'];
$ad['low']=$localParams['yk_low'];
$ad['accessed']=$sync->unixToDbTime($localParams['modified']);

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
  $extra['nonce']= $nonce;
 }
if ($timestamp==1){
  $extra['timestamp'] = ($otpinfo['high'] << 16) + $otpinfo['low'];
  $extra['sessioncounter'] = $sessionCounter;
  $extra['sessionuse'] = $sessionUse;
 }

sendResp(S_OK, $apiKey, $extra);

?>
