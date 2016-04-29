<?php

# Copyright (c) 2009-2015 Yubico AB
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

$time_start = microtime();

require_once 'ykval-common.php';
require_once 'ykval-config.php';
require_once 'ykval-synclib.php';
require_once 'ykval-log-verify.php';

header('content-type: text/plain');

$ipaddr = $_SERVER['REMOTE_ADDR'];

$https = (array_key_exists('HTTPS', $_SERVER) === TRUE
			&& strtolower($_SERVER['HTTPS']) !== 'off' ? TRUE : FALSE);

$myLog = new Log('ykval-verify');
$myLog->addField('ip', $ipaddr);

$myLog->request = new LogVerify();

if (array_key_exists('__YKVAL_VERIFY_LOGFORMAT__', $baseParams)
	&& is_string($baseParams['__YKVAL_VERIFY_LOGFORMAT__']))
{
	$myLog->request->format = $baseParams['__YKVAL_VERIFY_LOGFORMAT__'];
}

$myLog->request->set('ip', $ipaddr);
$myLog->request->set('tls', ($https ? 'tls' : '-'));
$myLog->request->set('time_start', $time_start);
unset($time_start);


$message = '';
if ($_GET) {
	$request = $_GET;
	$message = 'Request: ' . $_SERVER['QUERY_STRING'];
}
else if ($_POST)
{
	$request = $_POST;
	$kv = array();
	foreach ($request as $key => $value)
	{
		$kv[] = "$key=$value";
	}
	$message = 'POST: ' . join('&', $kv);
}
$message .= ' (at ' . date('c') . ' ' . microtime() . ') HTTP' . ($https ? 'S' : '');
$myLog->log(LOG_INFO, $message);
unset($message);


/* Detect protocol version */
if (preg_match('/\/wsapi\/([0-9]+)\.([0-9]+)\//', $_SERVER['REQUEST_URI'], $out))
{
	$protocol_version = $out[1] + $out[2] * 0.1;
}
else
{
	$protocol_version = 1.0;
}

$myLog->request->set('protocol', $protocol_version);
$myLog->log(LOG_DEBUG, "found protocol version $protocol_version");

/**
 * Extract values from HTTP request
 */
$h = getHttpVal('h', '', $request);
$client = getHttpVal('id', '0', $request);
$timestamp = getHttpVal('timestamp', '0', $request);
$otp = getHttpVal('otp', '', $request);

$otp = strtolower($otp);
if (preg_match('/^[jxe.uidchtnbpygk]+$/', $otp))
{
	$new_otp = strtr($otp, 'jxe.uidchtnbpygk', 'cbdefghijklnrtuv');
	$myLog->log(LOG_INFO, "Dvorak OTP converting $otp to $new_otp");
	$otp = $new_otp;
	unset($new_otp);
}

$myLog->request->set('signed', ($h === '' ? '-' : 'signed'));
$myLog->request->set('client', ($client === '0' ? '-' : $client));
$myLog->request->set('otp', $otp);


/**
 * Construct response parameters
 */
$extra = array();

if ($protocol_version >= 2.0)
{
	$extra['otp'] = $otp;
}

/**
 * We have the OTP now, so let's add it to the logging
 */
$myLog->addField('otp', $otp);


if ($protocol_version >= 2.0)
{
	$sl = getHttpVal('sl', '', $request);
	$timeout = getHttpVal('timeout', '', $request);
	$nonce = getHttpVal('nonce', '', $request);

	$myLog->request->set('sl', $sl);
	$myLog->request->set('timeout', $timeout);
	$myLog->request->set('nonce', $nonce);

	/* Nonce is required from protocol 2.0 */
	if (!$nonce)
	{
		$myLog->log(LOG_NOTICE, 'Nonce is missing and protocol version >= 2.0');
		sendResp(S_MISSING_PARAMETER, $myLog);
	}

	/* Add nonce to response parameters */
	$extra['nonce'] = $nonce;
}


/**
 * Sanity check HTTP parameters
 *
 * otp: one-time password
 * id: client id
 * timeout: timeout in seconds to wait for external answers, optional: if absent the server decides
 * nonce: random alphanumeric string, 16 to 40 characters long. Must be non-predictable and changing for each request, but need not be cryptographically strong
 * sl: "sync level", percentage of external servers that needs to answer (integer 0 to 100), or "fast" or "secure" to use server-configured values
 * h: signature (optional)
 * timestamp: requests timestamp/counters in response
 */

/* Change default protocol "strings" to numeric values */
if (isset($sl) && strcasecmp($sl, 'fast') == 0)
{
	$sl = $baseParams['__YKVAL_SYNC_FAST_LEVEL__'];
}
if (isset($sl) && strcasecmp($sl, 'secure') == 0)
{
	$sl = $baseParams['__YKVAL_SYNC_SECURE_LEVEL__'];
}
if (!isset($sl) || $sl == '')
{
	$sl = $baseParams['__YKVAL_SYNC_DEFAULT_LEVEL__'];
}
if ($sl && (preg_match("/^[0-9]+$/", $sl)==0 || ($sl<0 || $sl>100)))
{
	$myLog->log(LOG_NOTICE, 'SL is provided but not correct');
	sendResp(S_MISSING_PARAMETER, $myLog);
}

if (!isset($timeout) || $timeout == '')
{
	$timeout = $baseParams['__YKVAL_SYNC_DEFAULT_TIMEOUT__'];
}
if ($timeout && preg_match("/^[0-9]+$/", $timeout) == 0)
{
	$myLog->log(LOG_NOTICE, 'timeout is provided but not correct');
	sendResp(S_MISSING_PARAMETER, $myLog);
}

if ($otp == '')
{
	$myLog->log(LOG_NOTICE, 'OTP is missing');
	sendResp(S_MISSING_PARAMETER, $myLog);
}
if (strlen($otp) < TOKEN_LEN || strlen($otp) > OTP_MAX_LEN)
{
	$myLog->log(LOG_NOTICE, "Incorrect OTP length: $otp");
	sendResp(S_BAD_OTP, $myLog);
}
if (preg_match('/^[cbdefghijklnrtuv]+$/', $otp) == 0)
{
	$myLog->log(LOG_NOTICE, "Invalid OTP: $otp");
	sendResp(S_BAD_OTP, $myLog);
}

if (preg_match("/^[0-9]+$/", $client) == 0)
{
	$myLog->log(LOG_NOTICE, 'id provided in request must be an integer');
	sendResp(S_MISSING_PARAMETER, $myLog);
}
if ($client === '0')
{
	$myLog->log(LOG_NOTICE, 'Client ID is missing');
	sendResp(S_MISSING_PARAMETER, $myLog);
}

if (isset($nonce) && preg_match("/^[A-Za-z0-9]+$/", $nonce) == 0)
{
	$myLog->log(LOG_NOTICE, 'NONCE is provided but not correct');
	sendResp(S_MISSING_PARAMETER, $myLog);
}
if (isset($nonce) && (strlen($nonce) < 16 || strlen($nonce) > 40))
{
	$myLog->log(LOG_NOTICE, 'Nonce too short or too long');
	sendResp(S_MISSING_PARAMETER, $myLog);
}

/**
 * Timestamp parameter is not checked since current protocol
 *	says that 1 means request timestamp and anything else is discarded.
 */


/**
 * Initialize the sync library. Strive to use this instead of custom
 *	DB requests, custom comparisons etc.
 */
$sync = new SyncLib('ykval-verify:synclib');
$sync->addField('ip', $ipaddr);
$sync->addField('otp', $otp);

if (! $sync->isConnected())
{
	sendResp(S_BACKEND_ERROR, $myLog);
}

if (($cd = $sync->getClientData($client)) === FALSE)
{
	$myLog->log(LOG_NOTICE, "Invalid client id $client");
	sendResp(S_NO_SUCH_CLIENT, $myLog);
}
$myLog->log(LOG_DEBUG, 'Client data:', $cd);


/**
 * Check client signature
 */
$apiKey = $cd['secret'];
$apiKey = base64_decode($apiKey);
unset($cd);

if ($h != '')
{
	// Create the signature using the API key
	unset($request['h']);

	$hmac = sign($request, $apiKey, $myLog);

	if (hash_equals($hmac, $h) === FALSE)
	{
		$myLog->log(LOG_DEBUG, "client hmac=$h, server hmac=$hmac");
		sendResp(S_BAD_SIGNATURE, $myLog, $apiKey);
	}
}

/**
 * We need to add necessary parameters not available at
 *	earlier protocols after signature is computed.
 */
if ($protocol_version < 2.0)
{
	// we need to create a nonce manually here
	$nonce = md5(uniqid(rand()));
	$myLog->log(LOG_INFO, "protocol version below 2.0. Created nonce $nonce");
}

// which YK-KSM should we talk to?
$urls = otp2ksmurls($otp, $client);
if (!is_array($urls))
{
	sendResp(S_BACKEND_ERROR, $myLog, $apiKey);
}

// decode OTP from input
$curlopts = array();
if (array_key_exists('__YKVAL_KSM_CURL_OPTS__', $baseParams))
{
	$curlopts = $baseParams['__YKVAL_KSM_CURL_OPTS__'];
}
if (($otpinfo = KSMdecryptOTP($urls, $myLog, $curlopts)) === FALSE)
{
	/**
	 * FIXME
	 *
	 * Return S_BACKEND_ERROR if there are connection issues,
	 *	e.g. misconfigured otp2ksmurls.
	 */
	sendResp(S_BAD_OTP, $myLog, $apiKey);
}
$myLog->request->set('counter', $otpinfo['session_counter']);
$myLog->request->set('use', $otpinfo['session_use']);
$myLog->request->set('high', $otpinfo['high']);
$myLog->request->set('low', $otpinfo['low']);
$myLog->log(LOG_DEBUG, 'Decrypted OTP:', $otpinfo);

// get Yubikey from DB
$public_id = substr($otp, 0, strlen ($otp) - TOKEN_LEN);
$myLog->request->set('public_id', $public_id);
if (($localParams = $sync->getLocalParams($public_id)) === FALSE)
{
	$myLog->log(LOG_NOTICE, "Invalid Yubikey $public_id");
	sendResp(S_BACKEND_ERROR, $myLog, $apiKey);
}

$myLog->log(LOG_DEBUG, 'Auth data:', $localParams);
if ($localParams['active'] != 1)
{
	$myLog->log(LOG_NOTICE, "De-activated Yubikey $public_id");
	sendResp(S_BAD_OTP, $myLog, $apiKey);
}

/* Build OTP params */

$otpParams = array(
	'modified' => time(),
	'otp' => $otp,
	'nonce' => $nonce,
	'yk_publicname' => $public_id,
	'yk_counter' => $otpinfo['session_counter'],
	'yk_use' => $otpinfo['session_use'],
	'yk_high' => $otpinfo['high'],
	'yk_low' => $otpinfo['low']
);
unset($otpinfo);


/* First check if OTP is seen with the same nonce, in such case we have an replayed request */
if ($sync->countersEqual($localParams, $otpParams) && $localParams['nonce'] == $otpParams['nonce'])
{
	$myLog->log(LOG_WARNING, 'Replayed request');
	sendResp(S_REPLAYED_REQUEST, $myLog, $apiKey, $extra);
}

/* Check the OTP counters against local db */
if ($sync->countersHigherThanOrEqual($localParams, $otpParams))
{
	$sync->log(LOG_WARNING, 'replayed OTP: Local counters higher');
	$sync->log(LOG_WARNING, 'replayed OTP: Local counters ', $localParams);
	$sync->log(LOG_WARNING, 'replayed OTP: Otp counters ', $otpParams);
	sendResp(S_REPLAYED_OTP, $myLog, $apiKey, $extra);
}

/* Valid OTP, update database. */

if (!$sync->updateDbCounters($otpParams))
{
	$myLog->log(LOG_CRIT, 'Failed to update yubikey counters in database');
	sendResp(S_BACKEND_ERROR, $myLog, $apiKey);
}

/* Queue sync requests */

if (!$sync->queue($otpParams, $localParams))
{
	$myLog->log(LOG_CRIT, 'failed to queue sync requests');
	sendResp(S_BACKEND_ERROR, $myLog, $apiKey);
}

$nr_servers = $sync->getNumberOfServers();
$req_answers = ceil($nr_servers * $sl / 100.0);
if ($req_answers > 0)
{
	$syncres = $sync->sync($req_answers, $timeout);
	$nr_answers = $sync->getNumberOfAnswers();
	$nr_valid_answers = $sync->getNumberOfValidAnswers();
	$sl_success_rate = floor(100.0 * $nr_valid_answers / $nr_servers);
}
else
{
	$syncres = true;
	$nr_answers = 0;
	$nr_valid_answers = 0;
	$sl_success_rate = 0;
}

$myLog->log(LOG_INFO, '', array(
	'synclevel' => $sl,
	'nr servers' => $nr_servers,
	'req answers' => $req_answers,
	'answers' => $nr_answers,
	'valid answers' => $nr_valid_answers,
	'sl success rate' => $sl_success_rate,
	'timeout' => $timeout,
));

if ($syncres == False)
{
	/* sync returned false, indicating that
		either at least 1 answer marked OTP as invalid or
		there were not enough answers */
	$myLog->log(LOG_WARNING, 'Sync failed');

	if ($nr_valid_answers != $nr_answers)
		sendResp(S_REPLAYED_OTP, $myLog, $apiKey, $extra);

	$extra['sl'] = $sl_success_rate;
	sendResp(S_NOT_ENOUGH_ANSWERS, $myLog, $apiKey, $extra);
}

if ($otpParams['yk_counter'] == $localParams['yk_counter'] && $otpParams['yk_use'] > $localParams['yk_use'])
{
	$ts = ($otpParams['yk_high'] << 16) + $otpParams['yk_low'];
	$seenTs = ($localParams['yk_high'] << 16) + $localParams['yk_low'];
	$tsDiff = $ts - $seenTs;
	$tsDelta = $tsDiff * TS_SEC;

	$now = time();
	$elapsed = $now - $localParams['modified'];
	$deviation = abs($elapsed - $tsDelta);

	// Time delta server might verify multiple OTPS in a row. In such case validation server doesn't
	// have time to tick a whole second and we need to avoid division by zero.
	if ($elapsed != 0)
	{
		$percent = $deviation/$elapsed;
	}
	else
	{
		$percent = 1;
	}

	$myLog->log(LOG_INFO, 'Timestamp', array(
		'seen' => $seenTs,
		'this' => $ts,
		'delta' => $tsDiff,
		'secs' => $tsDelta,
		'accessed' => sprintf('%s (%s)', $localParams['modified'], date('Y-m-d H:i:s', $localParams['modified'])),
		'now' => sprintf('%s (%s)', $now, date('Y-m-d H:i:s', $now)),
		'elapsed' => $elapsed,
		'deviation' => sprintf('%s secs or %s%%', $deviation, round(100 * $percent)),
	));

	if ($deviation > TS_ABS_TOLERANCE && $percent > TS_REL_TOLERANCE)
	{
		$myLog->log(LOG_NOTICE, 'OTP failed phishing test');

		// FIXME
		// This was wrapped around if (0). should we nuke or enable?
		// sendResp(S_DELAYED_OTP, $myLog, $apiKey, $extra);
	}
}

/**
 * Fill up with more response parameters
 */

if ($protocol_version >= 2.0)
{
	$extra['sl'] = $sl_success_rate;
}

if ($timestamp == 1)
{
	$extra['timestamp'] = ($otpParams['yk_high'] << 16) + $otpParams['yk_low'];
	$extra['sessioncounter'] = $otpParams['yk_counter'];
	$extra['sessionuse'] = $otpParams['yk_use'];
}

sendResp(S_OK, $myLog, $apiKey, $extra);
