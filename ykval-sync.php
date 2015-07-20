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

require_once 'ykval-common.php';
require_once 'ykval-config.php';
require_once 'ykval-synclib.php';

header('content-type: text/plain');

if (empty($_SERVER['QUERY_STRING']))
{
	sendResp(S_MISSING_PARAMETER, $myLog);
}

$ipaddr = $_SERVER['REMOTE_ADDR'];
$allowed = $baseParams['__YKVAL_ALLOWED_SYNC_POOL__'];

$myLog = new Log('ykval-sync');
$myLog->addField('ip', $ipaddr);
$myLog->log(LOG_INFO, 'Request: ' . $_SERVER['QUERY_STRING']);
$myLog->log(LOG_DEBUG, "Received request from $ipaddr");


// verify request sent by whitelisted address
if (in_array($ipaddr, $allowed, TRUE) === FALSE)
{
	$myLog->log(LOG_NOTICE, "Operation not allowed from IP $ipaddr");
	$myLog->log(LOG_DEBUG, "Remote IP $ipaddr not listed in allowed sync pool : " . implode(', ', $allowed));
	sendResp(S_OPERATION_NOT_ALLOWED, $myLog);
}


// define requirements on protocol
$syncParams = array(
	'modified' => NULL,
	'otp' => NULL,
	'nonce' => NULL,
	'yk_publicname' => NULL,
	'yk_counter' => NULL,
	'yk_use' => NULL,
	'yk_high' => NULL,
	'yk_low' => NULL
);

// extract values from HTTP request
$tmp_log = 'Received ';
foreach ($syncParams as $param => $value)
{
	$value = getHttpVal($param, NULL);

	if ($value == NULL)
	{
		$myLog->log(LOG_NOTICE, "Received request with parameter[s] ($param) missing value");
		sendResp(S_MISSING_PARAMETER, $myLog);
	}

	$syncParams[$param] = $value;
	$tmp_log .= "$param=$value ";
}
$myLog->log(LOG_INFO, $tmp_log);


$sync = new SyncLib('ykval-sync:synclib');
$sync->addField('ip', $ipaddr);

if (! $sync->isConnected())
{
	sendResp(S_BACKEND_ERROR, $myLog);
}

// at this point we should have the otp so let's add it to the logging module
$myLog->addField('otp', $syncParams['otp']);
$sync->addField('otp', $syncParams['otp']);


// verify correctness of input parameters
foreach (array('modified','yk_counter', 'yk_use', 'yk_high', 'yk_low') as $param)
{
	// -1 is valid except for modified
	if ($param !== 'modified' && $syncParams[$param] === '-1')
		continue;

	// [0-9]+
	if ($syncParams[$param] !== '' && ctype_digit($syncParams[$param]))
		continue;

	$myLog->log(LOG_NOTICE, "Input parameters $param  not correct");
	sendResp(S_MISSING_PARAMETER, $myLog);
}

// get local counter data
$yk_publicname = $syncParams['yk_publicname'];
if (($localParams = $sync->getLocalParams($yk_publicname)) === FALSE)
{
	$myLog->log(LOG_NOTICE, 'Invalid Yubikey ' . $yk_publicname);
	sendResp(S_BACKEND_ERROR, $myLog);
}

// conditional update local database
$sync->updateDbCounters($syncParams);

$myLog->log(LOG_DEBUG, 'Local params ', $localParams);
$myLog->log(LOG_DEBUG, 'Sync request params ', $syncParams);

if ($sync->countersHigherThan($localParams, $syncParams))
{
	$myLog->log(LOG_WARNING, 'Remote server out of sync.');
}

if ($sync->countersEqual($localParams, $syncParams))
{
	if ($syncParams['modified'] == $localParams['modified']
		&& $syncParams['nonce'] == $localParams['nonce'])
	{
		/**
		 * This is not an error. When the remote server received an OTP to verify, it would
		 * have sent out sync requests immediately. When the required number of responses had
		 * been received, the current implementation discards all additional responses (to
		 * return the result to the client as soon as possible). If our response sent last
		 * time was discarded, we will end up here when the background ykval-queue processes
		 * the sync request again.
		 */
		$myLog->log(LOG_INFO, 'Sync request unnecessarily sent');
	}

	if ($syncParams['modified'] != $localParams['modified']
		&& $syncParams['nonce'] == $localParams['nonce'])
	{
		$deltaModified = $syncParams['modified'] - $localParams['modified'];

		if ($deltaModified < -1 || $deltaModified > 1)
		{
			$myLog->log(LOG_WARNING, "We might have a replay. 2 events at different times have generated the same counters. The time difference is $deltaModified seconds");
		}
	}

	if ($syncParams['nonce'] != $localParams['nonce'])
	{
		$myLog->log(LOG_WARNING, 'Remote server has received a request to validate an already validated OTP ');
	}
}

if ($localParams['active'] != 1)
{
	/**
	 * The remote server has accepted an OTP from a YubiKey which we would not.
	 * We still needed to update our counters with the counters from the OTP though.
	 */
	$myLog->log(LOG_WARNING, "Received sync-request for de-activated Yubikey $yk_publicname - check database synchronization!!!");
	sendResp(S_BAD_OTP, $myLog);
}

$extra = array(
	'modified' => $localParams['modified'],
	'nonce' => $localParams['nonce'],
	'yk_publicname' => $yk_publicname,
	'yk_counter' => $localParams['yk_counter'],
	'yk_use' => $localParams['yk_use'],
	'yk_high' => $localParams['yk_high'],
	'yk_low' => $localParams['yk_low']
);

sendResp(S_OK, $myLog, '', $extra);
