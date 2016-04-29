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

define('S_OK', 'OK');
define('S_BAD_OTP', 'BAD_OTP');
define('S_REPLAYED_OTP', 'REPLAYED_OTP');
define('S_DELAYED_OTP', 'DELAYED_OTP');
define('S_BAD_SIGNATURE', 'BAD_SIGNATURE');
define('S_MISSING_PARAMETER', 'MISSING_PARAMETER');
define('S_NO_SUCH_CLIENT', 'NO_SUCH_CLIENT');
define('S_OPERATION_NOT_ALLOWED', 'OPERATION_NOT_ALLOWED');
define('S_BACKEND_ERROR', 'BACKEND_ERROR');
define('S_NOT_ENOUGH_ANSWERS', 'NOT_ENOUGH_ANSWERS');
define('S_REPLAYED_REQUEST', 'REPLAYED_REQUEST');


define('TS_SEC', 1/8);
define('TS_REL_TOLERANCE', 0.3);
define('TS_ABS_TOLERANCE', 20);

define('TOKEN_LEN', 32);
define('OTP_MAX_LEN', 48); // TOKEN_LEN plus public identity of 0..16

function logdie ($logger, $str)
{
	$logger->log(LOG_INFO, $str);
	die($str . "\n");
}

function getHttpVal ($key, $default, $a)
{
	if (array_key_exists($key, $a))
	{
		$val = $a[$key];
	}
	else
	{
		$val = $default;
	}

	$val = trim($val);
	$val = str_replace('\\', '', $val);

	return $val;
}

// Sign a http query string in the array of key-value pairs
// return b64 encoded hmac hash
function sign($a, $apiKey, $logger)
{
	ksort($a);

	$qs = http_build_query($a);
	$qs = urldecode($qs);
	$qs = utf8_encode($qs);

	// base64 encoded binary digest
	$hmac = hash_hmac('sha1', $qs, $apiKey, TRUE);
	$hmac = base64_encode($hmac);

	$logger->log(LOG_DEBUG, "SIGN: $qs H=$hmac");

	return $hmac;
}

function curl_settings($logger, $ident, $ch, $url, $timeout, $opts)
{
	$logger->log(LOG_DEBUG, "$ident adding URL : $url");

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	curl_setopt($ch, CURLOPT_USERAGENT, 'YK-VAL');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_FAILONERROR, TRUE);

	if (is_array($opts) === FALSE)
	{
		$logger->log(LOG_WARN, $ident . 'curl options must be an array');
		return;
	}

	foreach ($opts as $key => $val)
		if (curl_setopt($ch, $key, $val) === FALSE)
			$logger->log(LOG_WARN, "$ident failed to set " . curl_opt_name($key));
}

// returns the string name of a curl constant,
//	or "curl option" if constant not found.
// e.g.
//  curl_opt_name(CURLOPT_URL) returns "CURLOPT_URL"
//  curl_opt_name(CURLOPT_BLABLA) returns "curl option"
function curl_opt_name($opt)
{
	$consts = get_defined_constants(true);
	$consts = $consts['curl'];

	$name = array_search($opt, $consts, TRUE);

	// array_search may return either on failure...
	if ($name === FALSE || $name === NULL)
		return 'curl option';

	return $name;
}

// This function takes a list of URLs.  It will return the content of
// the first successfully retrieved URL, whose content matches ^OK.
// The request are sent asynchronously.  Some of the URLs can fail
// with unknown host, connection errors, or network timeout, but as
// long as one of the URLs given work, data will be returned.  If all
// URLs fail, data from some URL that did not match parameter $match
// (defaults to ^OK) is returned, or if all URLs failed, false.
function retrieveURLasync($ident, $urls, $logger, $ans_req=1, $match="^OK", $returl=False, $timeout=10, $curlopts)
{
	$mh = curl_multi_init();
	$ch = array();

	foreach ($urls as $url)
	{
		$handle = curl_init();
		curl_settings($logger, $ident, $handle, $url, $timeout, $curlopts);
		curl_multi_add_handle($mh, $handle);
		$ch[$handle] = $handle;
	}

	$ans_arr = array();

	do
	{
		while (curl_multi_exec($mh, $active) == CURLM_CALL_MULTI_PERFORM);

		while ($info = curl_multi_info_read($mh))
		{
			$logger->log(LOG_DEBUG, "$ident curl multi info : ", $info);

			if ($info['result'] == CURLE_OK)
			{
				$str = curl_multi_getcontent($info['handle']);

				$logger->log(LOG_DEBUG, "$ident curl multi content : $str");

				if (preg_match("/$match/", $str))
				{
					$logger->log(LOG_DEBUG, "$ident response matches $match");
					$error = curl_error($info['handle']);
					$errno = curl_errno($info['handle']);
					$cinfo = curl_getinfo($info['handle']);
					$logger->log(LOG_INFO, "$ident errno/error: $errno/$error", $cinfo);

					if ($returl)
						$ans_arr[] = "url=" . $cinfo['url'] . "\n" . $str;
					else
						$ans_arr[] = $str;
				}

				if (count($ans_arr) >= $ans_req)
				{
					foreach ($ch as $h)
					{
						curl_multi_remove_handle($mh, $h);
						curl_close($h);
					}
					curl_multi_close($mh);

					return $ans_arr;
				}

				curl_multi_remove_handle($mh, $info['handle']);
				curl_close($info['handle']);
				unset($ch[$info['handle']]);
			}

			curl_multi_select($mh);
		}
	}
	while($active);

	foreach ($ch as $h)
	{
		curl_multi_remove_handle($mh, $h);
		curl_close($h);
	}
	curl_multi_close($mh);

	if (count($ans_arr) > 0)
		return $ans_arr;

	return false;
}

function KSMdecryptOTP($urls, $logger, $curlopts)
{
	$response = retrieveURLasync('YK-KSM', $urls, $logger, $ans_req=1, $match='^OK', $returl=False, $timeout=10, $curlopts);

	if ($response === FALSE)
		return false;

	$response = array_shift($response);

	$logger->log(LOG_DEBUG, "YK-KSM response: $response");

	$ret = array();

	if (sscanf($response,
		'OK counter=%04x low=%04x high=%02x use=%02x',
		$ret['session_counter'],
		$ret['low'],
		$ret['high'],
		$ret['session_use']) !== 4)
	{
		return false;
	}

	return $ret;
}

function sendResp($status, $logger, $apiKey = '', $extra = null)
{
	if ($logger->request !== NULL)
		$logger->request->set('status', $status);

	$a['status'] = $status;

	// 2008-11-21T06:11:55Z0711
	$t = substr(microtime(false), 2, 3);
	$t = gmdate('Y-m-d\TH:i:s\Z0') . $t;

	$a['t'] = $t;

	if ($extra)
		foreach ($extra as $param => $value)
			$a[$param] = $value;

	$h = sign($a, $apiKey, $logger);

	$str = "";
	$str .= "h=" . $h . "\r\n";
	$str .= "t=" . $a['t'] . "\r\n";

	if ($extra)
		foreach ($extra as $param => $value)
			$str .= $param . "=" . $value . "\r\n";

	$str .= "status=" . $a['status'] . "\r\n";
	$str .= "\r\n";

	$logger->log(LOG_INFO, "Response: " . $str . " (at " . gmdate("c") . " " . microtime() . ")");

	if ($logger->request !== NULL)
		$logger->request->write();

	echo $str;
	exit;
}

// backport from PHP 5.6
if (function_exists('hash_equals') === FALSE)
{
	function hash_equals($a, $b)
	{
		// hashes are a (known) fixed length,
		//	so this doesn't leak anything.
		if (strlen($a) != strlen($b))
			return false;

		$result = 0;

		for ($i = 0; $i < strlen($a); $i++)
			$result |= ord($a[$i]) ^ ord($b[$i]);

		return (0 === $result);
	}
}

/**
 * Return the total time taken to receive a response from a URL.
 *
 * @argument $url string
 * @return float|bool seconds or false on failure
 */
function total_time ($url)
{
	$opts = array(
		CURLOPT_URL => $url,
		CURLOPT_TIMEOUT => 3,
		CURLOPT_FORBID_REUSE => TRUE,
		CURLOPT_FRESH_CONNECT => TRUE,
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_USERAGENT => 'ykval-munin-vallatency/1.0',
	);

	if (($ch = curl_init()) === FALSE)
		return false;

	if (curl_setopt_array($ch, $opts) === FALSE)
		return false;

	// we don't care about the actual response
	if (curl_exec($ch) === FALSE)
		return false;

	$total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);

	curl_close($ch);

	if (is_float($total_time) === FALSE)
		return false;

	return $total_time;
}

/**
 * Given a list of urls, create internal and label names for munin.
 *
 * @argument $urls array
 * @return array|bool array or false on failure.
 */
function endpoints ($urls)
{
	$endpoints = array();

	foreach ($urls as $url)
	{
		// internal munin name must be a-zA-Z0-9_,
		//	so sha1 hex should be fine.
		//
		// munin also truncates at some length,
		//	so we just take the first few characters of the hashsum.
		$internal = substr(sha1($url), 0, 20);

		// actual label name shown for graph values
		if (($label = hostport($url)) === FALSE)
		{
			return false;
		}

		$endpoints[] = array($internal, $label, $url);
	}

	// check for truncated sha1 collisions (or actual duplicate URLs!)
	$internal = array();

	foreach($endpoints as $endpoint)
	{
		$internal[] = $endpoint[0];
	}

	if (count(array_unique($internal)) !== count($endpoints))
		return false;

	return $endpoints;
}

/**
 * Given a URL, if the port is defined or can be determined from the scheme,
 *	return the hostname and port.
 * Otherwise just return the hostname.
 *
 * @argument $url string
 * @return string|bool string or false on failure
 */
function hostport ($url)
{
	if (($url = parse_url($url)) === FALSE)
		return false;

	if (array_key_exists('host', $url) === FALSE || $url['host'] === NULL)
		return false;

	if (array_key_exists('port', $url) === TRUE && $url['port'] !== NULL)
		return $url['host'].':'.$url['port'];

	if (array_key_exists('scheme', $url) === TRUE
			&& strtolower($url['scheme']) === 'http')
		return $url['host'].':80';

	if (array_key_exists('scheme', $url) === TRUE
			&& strtolower($url['scheme']) === 'https')
		return $url['host'].':443';

	return $url['host'];
}
