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

function unescape($s) {
	return str_replace('\\', "", $s);
}

function getHttpVal($key, $defaultVal) {
	$val = $defaultVal;
	if (array_key_exists($key, $_GET)) {
		$val = $_GET[$key];
  	} else if (array_key_exists($key, $_POST)) {
  		$val = $_POST[$key];
  	}
  	$v = unescape(trim($val));
  	return $v;
}

function log_format() {
  $str = "";
  foreach (func_get_args() as $msg)
    {
      if (is_array($msg)) {
	foreach($msg as $key => $value){
	  $str .= "$key=$value ";
	}
      } else {
	$str .= $msg . " ";
      }
    }
  return $str;
}

// Return eg. 2008-11-21T06:11:55Z0711
//
function getUTCTimeStamp() {
	date_default_timezone_set('UTC');
	$tiny = substr(microtime(false), 2, 3);
	return date('Y-m-d\TH:i:s\Z0', time()) . $tiny;
}

# NOTE: When we evolve to using general DB-interface, this functinality
# should be moved there.
function DbTimeToUnix($db_time)
{
  $unix=strptime($db_time, '%F %H:%M:%S');
  return mktime($unix[tm_hour], $unix[tm_min], $unix[tm_sec], $unix[tm_mon]+1, $unix[tm_mday], $unix[tm_year]+1900);
}

function UnixToDbTime($unix)
{
  return date('Y-m-d H:i:s', $unix);
}

// Sign a http query string in the array of key-value pairs
// return b64 encoded hmac hash
function sign($a, $apiKey, $logger) {
	ksort($a);
	$qs = urldecode(http_build_query($a));

	// the TRUE at the end states we want the raw value, not hexadecimal form
	$hmac = hash_hmac('sha1', utf8_encode($qs), $apiKey, true);
	$hmac = base64_encode($hmac);

	$logger->log(LOG_DEBUG, 'SIGN: ' . $qs . ' H=' . $hmac);

	return $hmac;

} // sign an array of query string

function hex2b64 ($hex_str) {
  $bin = pack("H*", $hex_str);
  return base64_encode($bin);
}

function modhex2b64 ($modhex_str) {
  $hex_str = strtr ($modhex_str, "cbdefghijklnrtuv", "0123456789abcdef");
  return hex2b64($hex_str);
}

// This function takes a list of URLs.  It will return the content of
// the first successfully retrieved URL, whose content matches ^OK.
// The request are sent asynchronously.  Some of the URLs can fail
// with unknown host, connection errors, or network timeout, but as
// long as one of the URLs given work, data will be returned.  If all
// URLs fail, data from some URL that did not match parameter $match
// (defaults to ^OK) is returned, or if all URLs failed, false.
function retrieveURLasync ($ident, $urls, $logger, $ans_req=1, $match="^OK", $returl=False, $timeout=10) {
  $mh = curl_multi_init();

  $ch = array();
  foreach ($urls as $id => $url) {
    $handle = curl_init();
    $logger->log(LOG_DEBUG, $ident . " adding URL : " . $url);
    curl_setopt($handle, CURLOPT_URL, $url);
    curl_setopt($handle, CURLOPT_USERAGENT, "YK-VAL");
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($handle, CURLOPT_FAILONERROR, true);
    curl_setopt($handle, CURLOPT_TIMEOUT, $timeout);

    curl_multi_add_handle($mh, $handle);

    $ch[$handle] = $handle;
  }

  $str = false;
  $ans_count = 0;
  $ans_arr = array();

  do {
    while (($mrc = curl_multi_exec($mh, $active)) == CURLM_CALL_MULTI_PERFORM)
      ;

    while ($info = curl_multi_info_read($mh)) {
      $logger->log(LOG_DEBUG, $ident . " curl multi info : ", $info);
      if ($info['result'] == CURLE_OK) {
	$str = curl_multi_getcontent($info['handle']);
	$logger->log(LOG_DEBUG, $ident . " curl multi content : " . $str);
	if (preg_match("/".$match."/", $str)) {
	  $logger->log(LOG_DEBUG, $ident . " response matches " . $match);
	  $error = curl_error ($info['handle']);
	  $errno = curl_errno ($info['handle']);
	  $cinfo = curl_getinfo ($info['handle']);
	  $logger->log(LOG_DEBUG, $ident . " errno/error: " . $errno . "/" . $error, $cinfo);
	  $ans_count++;
	  if ($returl) $ans_arr[]="url=" . $cinfo['url'] . "\n" . $str;
	  else $ans_arr[]=$str;
	}

	if ($ans_count >= $ans_req) {
	  foreach ($ch as $h) {
	    curl_multi_remove_handle ($mh, $h);
	    curl_close ($h);
	  }
	  curl_multi_close ($mh);

	  return $ans_arr;
	}

	curl_multi_remove_handle ($mh, $info['handle']);
	curl_close ($info['handle']);
	unset ($ch[$info['handle']]);
      }

      curl_multi_select ($mh);
    }
  } while($active);

  foreach ($ch as $h) {
    curl_multi_remove_handle ($mh, $h);
    curl_close ($h);
  }
  curl_multi_close ($mh);

  if ($ans_count>0) return $ans_arr;
  return $str;
}

// $otp: A yubikey OTP
function KSMdecryptOTP($urls, $logger) {
  $ret = array();
  if (!is_array($urls)) {
    $urls = array($urls);
  }

  $response = retrieveURLasync ("YK-KSM", $urls, $logger, $ans_req=1, $match="^OK", $returl=False, $timeout=10);
  if (is_array($response)) {
    $response = $response[0];
  }
  if ($response) {
    $logger->log(LOG_DEBUG, log_format("YK-KSM response: ", $response));
  }
  if (sscanf ($response,
	      "OK counter=%04x low=%04x high=%02x use=%02x",
	      $ret["session_counter"], $ret["low"], $ret["high"],
	      $ret["session_use"]) != 4) {
    return false;
  }
  return $ret;
} // End decryptOTP

function sendResp($status, $logger, $apiKey = '', $extra = null) {
  if ($status == null) {
    $status = S_BACKEND_ERROR;
  }

  $a['status'] = $status;
  $a['t'] = getUTCTimeStamp();
  if ($extra){
    foreach ($extra as $param => $value) $a[$param] = $value;
  }
  $h = sign($a, $apiKey, $logger);

  $str = "h=" . $h . "\r\n";
  $str .= "t=" . ($a['t']) . "\r\n";
  if ($extra){
    foreach ($extra as $param => $value) {
      $str .= $param . "=" . $value . "\r\n";
    }
  }
  $str .= "status=" . ($a['status']) . "\r\n";
  $str .= "\r\n";

  $logger->log(LOG_INFO, "Response: " . $str .
    " (at " . date("c") . " " . microtime() . ")");

  echo $str;
}
?>
