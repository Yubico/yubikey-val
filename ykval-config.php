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


/**
 * Load database configuration settings from config-db.php if available,
 *	otherwise default to values specified below.
 */
$dbfile = '/etc/yubico/val/config-db.php';

if (file_exists($dbfile) && @is_readable($dbfile))
{
    require_once $dbfile;
}
else
{
    // FIXME hostname
    // FIXME port
    // 'oci:oracledb' for Oracle DB (with OCI library)
    $dbtype = 'mysql';
    $dbuser = 'ykval_verifier';
    $dbpass = 'yourpassword';
    $dbname = 'ykval';
}

// for the validation interface.
$baseParams = array ();
$baseParams['__YKVAL_DB_DSN__'] = "$dbtype:dbname=$dbname;host=127.0.0.1";
$baseParams['__YKVAL_DB_USER__'] = $dbuser;
$baseParams['__YKVAL_DB_PW__'] = $dbpass;
$baseParams['__YKVAL_DB_OPTIONS__'] = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);

// for the validation server sync
$baseParams['__YKVAL_SYNC_POOL__'] = array(
    // "https://api2.example.com/wsapi/2.0/sync",
    // "https://api3.example.com/wsapi/2.0/sync",
    // "https://api4.example.com/wsapi/2.0/sync",
);

/**
 * An array of IP addresses which are allowed to issue sync requests to us.
 *
 * NOTE: You must use IP addresses here. Hostnames will be ignored!
 *	Both IPv4 and IPv6 are supported.
 */
$baseParams['__YKVAL_ALLOWED_SYNC_POOL__'] = array(
    // "1.2.3.4",
    // "2.3.4.5",
    // "3.4.5.6",
    // "fc00:aaaa::",
    // "fc00:bbbb::",
    // "fc00:cccc::",
);

// An array of IP addresses allowed to issue YubiKey activation/deactivation
// requests through ykval-revoke.php. NOTE: You must use IP addresses here.
$baseParams['__YKREV_IPS__'] = array(/*"127.0.0.1"*/);
// An array of IP addresses allowed to issue database resync requests through
// ykval-resync.php. NOTE: You must use IP addresses here.
// $baseParams['__YKRESYNC_IPS__'] = array("127.0.0.1");
// Use the same as for issuing sync requests:
$baseParams['__YKRESYNC_IPS__'] = $baseParams['__YKVAL_ALLOWED_SYNC_POOL__'];

// Specify how often the sync daemon awakens
$baseParams['__YKVAL_SYNC_INTERVAL__'] = 10;
// Specify how long the sync daemon will wait for response
$baseParams['__YKVAL_SYNC_RESYNC_TIMEOUT__'] = 30;
// Specify how old entries in the database should be considered aborted attempts
$baseParams['__YKVAL_SYNC_OLD_LIMIT__'] = 10;

// These are settings for the validation server.
$baseParams['__YKVAL_SYNC_FAST_LEVEL__'] = 1;
$baseParams['__YKVAL_SYNC_SECURE_LEVEL__'] = 40;
$baseParams['__YKVAL_SYNC_DEFAULT_LEVEL__'] = 60;
$baseParams['__YKVAL_SYNC_DEFAULT_TIMEOUT__'] = 1;

// A key -> value array with curl options to set
//  when calling URLs defined in __YKVAL_SYNC_POOL__
$baseParams['__YKVAL_SYNC_CURL_OPTS__'] = array(
    //CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
);

// A key -> value array with curl options to set
//  when calling URLs returned by otp2ksmurls()
$baseParams['__YKVAL_KSM_CURL_OPTS__'] = array(
    //CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
);

// Returns an array of YK-KSM URLs for decrypting $otp for $client.
// The URLs must be fully qualified, i.e., containing the OTP itself.
function otp2ksmurls ($otp, $client)
{
    //if ($client == 42) {
    //  return array("https://another-ykksm.example.com/wsapi/decrypt?otp=$otp");
    //}

    //if (preg_match("/^dteffujehknh/", $otp)) {
    //  return array("https://different-ykksm.example.com/wsapi/decrypt?otp=$otp");
    //}

    return array(
        // "https://ykksm1.example.com/wsapi/decrypt?otp=$otp",
        // "https://ykksm2.example.com/wsapi/decrypt?otp=$otp",
        "http://127.0.0.1:80/wsapi/decrypt?otp=$otp",
        "http://127.0.0.1:8002/wsapi/decrypt?otp=$otp",
    );
}

/**
 * Uncomment to log verify requests just before replying to client.
 *
 * Available variables:
 *	%time_start%
 *	%time_end%
 *	%time_taken%
 *	%ip%
 *	%client%
 *	%public_id%
 *	%otp%
 *	%status%
 *	%nonce%
 *	%signed%
 *	%counter%
 *	%low%
 *	%high%
 *	%use%
 *	%tls%
 *	%protocol%
 *	%sl%
 *	%timeout%
 *
 * If a value is malformed or not available,
 *	a dash '-' is written instead.
 */
//$baseParams['__YKVAL_VERIFY_LOGFORMAT__'] = '[%time_start%] [%time_taken%] [%ip%] [%tls%] [%protocol%] [%status%] [%client%] [%public_id%] [%otp%] [%sl%] [%timeout%] [%nonce%] [%signed%] [%counter%] [%low%] [%high%] [%use%]';
