<?php

# Copyright (c) 2009-2013 Yubico AB
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are
# met:
#
#   * Redistributions of source code must retain the above copyright
#     notice, this list of conditions and the following disclaimer.
#
#   * Redistributions in binary form must reproduce the above
#     copyright notice, this list of conditions and the following
#     disclaimer in the documentation and/or other materials provided
#     with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
# "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
# LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
# A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
# OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
# SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
# LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
# DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
# THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
# (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
# OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

require_once 'ykval-common.php';
require_once 'ykval-config.php';
require_once 'ykval-synclib.php';

$apiKey = '';

header("content-type: text/plain");

$myLog = new Log('ykval-verify');
$myLog->addField('ip', $_SERVER['REMOTE_ADDR']);
$query_string = '';
if ($_POST) {
  $kv = array();
  foreach ($_POST as $key => $value) {
    $kv[] = "$key=$value";
  }
  $query_string = "POST: " . join("&", $kv);
} else {
  $query_string = "Request: " . $_SERVER['QUERY_STRING'];
}

$myLog->log(LOG_INFO, $query_string .
	    " (at " . date("c") . " " . microtime() . ") " .
	    (isset($_SERVER["HTTPS"])  && $_SERVER["HTTPS"] == "on" ? "HTTPS" : "HTTP"));

/* Detect protocol version */
if (preg_match("/\/wsapi\/([0-9]+)\.([0-9]+)\//", $_SERVER['REQUEST_URI'], $out)) {
  $protocol_version=$out[1]+$out[2]*0.1;
 } else {
  $protocol_version=1.0;
 }

$myLog->log(LOG_DEBUG, "found protocol version " . $protocol_version);

/* Extract values from HTTP request
 */
$h = getHttpVal('h', '');
$client = getHttpVal('id', 0);
$otp = getHttpVal('otp', '');
$otp = strtolower($otp);
if (preg_match("/^[jxe.uidchtnbpygk]+$/", $otp)) {
  $new_otp = strtr($otp, "jxe.uidchtnbpygk", "cbdefghijklnrtuv");
  $myLog->log(LOG_INFO, 'Dvorak OTP converting ' . $otp . ' to ' . $new_otp);
  $otp = $new_otp;
}
$timestamp = getHttpVal('timestamp', 0);

/* Construct response parameters */
$extra=array();
if ($protocol_version>=2.0) {
  $extra['otp']=$otp;
}


/* We have the OTP now, so let's add it to the logging */
$myLog->addField('otp', $otp);

if ($protocol_version>=2.0) {
  $sl = getHttpVal('sl', '');
  $timeout = getHttpVal('timeout', '');
  $nonce = getHttpVal('nonce', '');

  /* Add nonce to response parameters */
  $extra['nonce']= $nonce;

  /* Nonce is required from protocol 2.0 */
  if(!$nonce) {
    $myLog->log(LOG_NOTICE, 'Nonce is missing and protocol version >= 2.0');
    sendResp(S_MISSING_PARAMETER, $myLog);
    exit;
  }
}


/* Sanity check HTTP parameters

 * otp: one-time password
 * id: client id
 * timeout: timeout in seconds to wait for external answers, optional: if absent the server decides
 * nonce: random alphanumeric string, 16 to 40 characters long. Must be non-predictable and changing for each request, but need not be cryptographically strong
 * sl: "sync level", percentage of external servers that needs to answer (integer 0 to 100), or "fast" or "secure" to use server-configured values
 * h: signature (optional)
 * timestamp: requests timestamp/counters in response

 */

/* Change default protocol "strings" to numeric values */
if (isset($sl) && strcasecmp($sl, 'fast')==0) {
  $sl=$baseParams['__YKVAL_SYNC_FAST_LEVEL__'];
}
if (isset($sl) && strcasecmp($sl, 'secure')==0) {
  $sl=$baseParams['__YKVAL_SYNC_SECURE_LEVEL__'];
}
if (!isset($sl) || $sl == '') {
  $sl=$baseParams['__YKVAL_SYNC_DEFAULT_LEVEL__'];
}
if (!isset($timeout) || $timeout == '') {
  $timeout=$baseParams['__YKVAL_SYNC_DEFAULT_TIMEOUT__'];
}

if ($otp == '') {
  $myLog->log(LOG_NOTICE, 'OTP is missing');
  sendResp(S_MISSING_PARAMETER, $myLog);
  exit;
}

if (strlen($otp) < TOKEN_LEN || strlen ($otp) > OTP_MAX_LEN) {
  $myLog->log(LOG_NOTICE, 'Incorrect OTP length: ' . $otp);
  sendResp(S_BAD_OTP, $myLog);
  exit;
}

if (preg_match("/^[cbdefghijklnrtuv]+$/", $otp)==0) {
  $myLog->log(LOG_NOTICE, 'Invalid OTP: ' . $otp);
  sendResp(S_BAD_OTP, $myLog);
  exit;
}

if (preg_match("/^[0-9]+$/", $client)==0){
  $myLog->log(LOG_NOTICE, 'id provided in request must be an integer');
  sendResp(S_MISSING_PARAMETER, $myLog);
  exit;
}

if ($timeout && preg_match("/^[0-9]+$/", $timeout)==0) {
  $myLog->log(LOG_NOTICE, 'timeout is provided but not correct');
  sendResp(S_MISSING_PARAMETER, $myLog);
  exit;
}

if (isset($nonce) && preg_match("/^[A-Za-z0-9]+$/", $nonce)==0) {
  $myLog->log(LOG_NOTICE, 'NONCE is provided but not correct');
  sendResp(S_MISSING_PARAMETER, $myLog);
  exit;
}

if (isset($nonce) && (strlen($nonce) < 16 || strlen($nonce) > 40)) {
  $myLog->log(LOG_NOTICE, 'Nonce too short or too long');
  sendResp(S_MISSING_PARAMETER, $myLog);
  exit;
}

if ($sl && (preg_match("/^[0-9]+$/", $sl)==0 || ($sl<0 || $sl>100))) {
  $myLog->log(LOG_NOTICE, 'SL is provided but not correct');
  sendResp(S_MISSING_PARAMETER, $myLog);
  exit;
}

// NOTE: Timestamp parameter is not checked since current protocol says that 1 means request timestamp
// and anything else is discarded.

//// Get Client info from DB
//
if ($client <= 0) {
  $myLog->log(LOG_NOTICE, 'Client ID is missing');
  sendResp(S_MISSING_PARAMETER, $myLog);
  exit;
}



/* Initialize the sync library. Strive to use this instead of custom
   DB requests, custom comparisons etc */
$sync = new SyncLib('ykval-verify:synclib');
$sync->addField('ip', $_SERVER['REMOTE_ADDR']);
$sync->addField('otp', $otp);

if (! $sync->isConnected()) {
  sendResp(S_BACKEND_ERROR, $myLog);
  exit;
 }

$cd=$sync->getClientData($client);
if(!$cd) {
  $myLog->log(LOG_NOTICE, 'Invalid client id ' . $client);
  sendResp(S_NO_SUCH_CLIENT, $myLog);
  exit;
 }
$myLog->log(LOG_DEBUG,"Client data:", $cd);

//// Check client signature
//
$apiKey = base64_decode($cd['secret']);

if ($h != '') {
  // Create the signature using the API key
  $a;
  if($_GET) {
    $a = $_GET;
  } elseif($_POST) {
    $a = $_POST;
  } else {
    sendRest(S_BACKEND_ERROR);
    exit;
  }
  unset($a['h']);

  $hmac = sign($a, $apiKey, $myLog);
  // Compare it
  if ($hmac != $h) {
    $myLog->log(LOG_DEBUG, 'client hmac=' . $h . ', server hmac=' . $hmac);
    sendResp(S_BAD_SIGNATURE, $myLog, $apiKey);
    exit;
  }
}

/* We need to add necessary parameters not available at earlier protocols after signature is computed.
 */
if ($protocol_version<2.0) {
  /* We need to create a nonce manually here */
  $nonce = md5(uniqid(rand()));
  $myLog->log(LOG_INFO, 'protocol version below 2.0. Created nonce ' . $nonce);
 }

//// Which YK-KSM should we talk to?
//
$urls = otp2ksmurls ($otp, $client);
if (!is_array($urls)) {
  sendResp(S_BACKEND_ERROR, $myLog, $apiKey);
  exit;
}

//// Decode OTP from input
//
$otpinfo = KSMdecryptOTP($urls, $myLog);
if (!is_array($otpinfo)) {
  sendResp(S_BAD_OTP, $myLog, $apiKey);
  exit;
}
$myLog->log(LOG_DEBUG, "Decrypted OTP:", $otpinfo);

//// Get Yubikey from DB
//
$devId = substr($otp, 0, strlen ($otp) - TOKEN_LEN);
$yk_publicname=$devId;
$localParams = $sync->getLocalParams($yk_publicname);
if (!$localParams) {
  $myLog->log(LOG_NOTICE, 'Invalid Yubikey ' . $yk_publicname);
  sendResp(S_BACKEND_ERROR, $myLog, $apiKey);
  exit;
 }

$myLog->log(LOG_DEBUG, "Auth data:", $localParams);
if ($localParams['active'] != 1) {
  $myLog->log(LOG_NOTICE, 'De-activated Yubikey ' . $devId);
  sendResp(S_BAD_OTP, $myLog, $apiKey);
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
  $myLog->log(LOG_WARNING, 'Replayed request');
  sendResp(S_REPLAYED_REQUEST, $myLog, $apiKey, $extra);
  exit;
 }

/* Check the OTP counters against local db */
if ($sync->countersHigherThanOrEqual($localParams, $otpParams)) {
  $sync->log(LOG_WARNING, 'replayed OTP: Local counters higher');
  $sync->log(LOG_WARNING, 'replayed OTP: Local counters ', $localParams);
  $sync->log(LOG_WARNING, 'replayed OTP: Otp counters ', $otpParams);
  sendResp(S_REPLAYED_OTP, $myLog, $apiKey, $extra);
  exit;
 }

/* Valid OTP, update database. */

if(!$sync->updateDbCounters($otpParams)) {
  $myLog->log(LOG_CRIT, "Failed to update yubikey counters in database");
  sendResp(S_BACKEND_ERROR, $myLog, $apiKey);
  exit;
 }

/* Queue sync requests */

if (!$sync->queue($otpParams, $localParams)) {
  $myLog->log(LOG_CRIT, "ykval-verify:critical:failed to queue sync requests");
  sendResp(S_BACKEND_ERROR, $myLog, $apiKey);
  exit;
 }

$nr_servers=$sync->getNumberOfServers();
$req_answers=ceil($nr_servers*$sl/100.0);
if ($req_answers>0) {
  $syncres=$sync->sync($req_answers, $timeout);
  $nr_answers=$sync->getNumberOfAnswers();
  $nr_valid_answers=$sync->getNumberOfValidAnswers();
  $sl_success_rate=floor(100.0 * $nr_valid_answers / $nr_servers);

 } else {
  $syncres=true;
  $nr_answers=0;
  $nr_valid_answers=0;
  $sl_success_rate=0;
 }
$myLog->log(LOG_INFO, "ykval-verify:notice:synclevel=" . $sl .
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
  $myLog->log(LOG_WARNING, "ykval-verify:notice:Sync failed");
  if ($nr_valid_answers!=$nr_answers) {
    sendResp(S_REPLAYED_OTP, $myLog, $apiKey, $extra);
    exit;
  } else {
    $extra['sl']=$sl_success_rate;
    sendResp(S_NOT_ENOUGH_ANSWERS, $myLog, $apiKey, $extra);
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
  $myLog->log(LOG_INFO, "Timestamp seen=" . $seenTs . " this=" . $ts .
	      " delta=" . $tsDiff . ' secs=' . $tsDelta .
	      ' accessed=' . $lastTime .' (' . $ad['accessed'] . ') now='
	      . $now . ' (' . strftime("%Y-%m-%d %H:%M:%S", $now)
	      . ') elapsed=' . $elapsed .
	      ' deviation=' . $deviation . ' secs or '.
	      round(100*$percent) . '%');
  if ($deviation > TS_ABS_TOLERANCE && $percent > TS_REL_TOLERANCE) {
    $myLog->log(LOG_NOTICE, "OTP failed phishing test");
    if (0) {
      sendResp(S_DELAYED_OTP, $myLog, $apiKey, $extra);
      exit;
    }
  }
}

/* Fill up with more respone parameters */
if ($protocol_version>=2.0) {
  $extra['sl'] = $sl_success_rate;
 }
if ($timestamp==1){
  $extra['timestamp'] = ($otpinfo['high'] << 16) + $otpinfo['low'];
  $extra['sessioncounter'] = $sessionCounter;
  $extra['sessionuse'] = $sessionUse;
 }

sendResp(S_OK, $myLog, $apiKey, $extra);

?>
