<?php

# Copyright (c) 2012-2013 Yubico AB
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
require_once 'ykval-db.php';
require_once 'ykval-log.php';
require_once 'ykval-synclib.php';

header("content-type: text/plain");

$myLog = new Log('ykval-resync');
$myLog->addField('ip', $_SERVER['REMOTE_ADDR']);

if (!in_array ($_SERVER["REMOTE_ADDR"], $baseParams['__YKRESYNC_IPS__'])) {
  logdie($myLog, "ERROR Authorization failed (logged ". $_SERVER["REMOTE_ADDR"] .")");
}

# Parse input
$yk = $_REQUEST["yk"];
if (!$yk) {
  logdie($myLog, "ERROR Missing parameter");
}
if (!($yk == "all" || preg_match("/^([cbdefghijklnrtuv]{0,16})$/", $yk))) {
  logdie($myLog, "ERROR Unknown yk value: $yk");
}
$myLog->addField('yk', $yk);

# Connect to db
$db = Db::GetDatabaseHandle($baseParams, 'ykval-resync');
if (!$db->connect()) {
  logdie($myLog, 'ERROR Database connect error (1)');
}

if($yk == "all") {
  # Get all keys
  $res = $db->customQuery("SELECT yk_publicname FROM yubikeys WHERE active = true");
  while($r = $db->fetchArray($res)) {
    $yubikeys[] = $r['yk_publicname'];
  }
  $db->closeCursor($res);
} else {
  # Check if key exists
  $r = $db->findBy('yubikeys', 'yk_publicname', $yk, 1);
  if (!$r) {
    logdie($myLog, "ERROR Unknown yubikey: $yk");
  }
  $yubikeys = array($yk);
}

/* Initialize the sync library. */
$sync = new SyncLib('ykval-resync:synclib');
$sync->addField('ip', $_SERVER['REMOTE_ADDR']);
$sync->addField('yk', $yk);

if (! $sync->isConnected()) {
  logdie($myLog, 'ERROR Database connect error (2)');
}

foreach($yubikeys as $key) {
  $localParams = $sync->getLocalParams($key);
  if (!$localParams) {
    logdie($myLog, 'ERROR Invalid Yubikey ' . $key);
  }

  $localParams['otp'] = $key . str_repeat('c', 32); // Fake an OTP, only used for logging.
  $myLog->log(LOG_DEBUG, "Auth data:", $localParams);

  /* Queue sync request */
  if (!$sync->queue($localParams, $localParams)) {
    logdie($myLog, 'ERROR Failed resync');
  }
}

# We are done
logdie($myLog, "OK Initiated resync of $yk");
?>
