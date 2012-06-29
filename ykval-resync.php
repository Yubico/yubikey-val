<?php
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
if (!preg_match("/^([cbdefghijklnrtuv]{0,16})$/", $yk)) {
  logdie($myLog, "ERROR Unknown yk value: $yk");
}
$myLog->addField('yk', $yk);

# Connect to db
$db = new Db($baseParams['__YKVAL_DB_DSN__'],
	     $baseParams['__YKVAL_DB_USER__'],
	     $baseParams['__YKVAL_DB_PW__'],
	     $baseParams['__YKVAL_DB_OPTIONS__'],
	     'ykval-resync:db');
if (!$db->connect()) {
  logdie($myLog, 'ERROR Database connect error (1)');
}

# Check if key exists
$r = $db->findBy('yubikeys', 'yk_publicname', $yk, 1);
if (!$r) {
  logdie($myLog, "ERROR Unknown yubikey: $yk");
}

/* Initialize the sync library. */
$sync = new SyncLib('ykval-resync:synclib');
$sync->addField('ip', $_SERVER['REMOTE_ADDR']);
$sync->addField('yk', $yk);

if (! $sync->isConnected()) {
  logdie($myLog, 'ERROR Database connect error (2)');
}

$localParams = $sync->getLocalParams($yk);
if (!$localParams) {
  logdie($myLog, 'ERROR Invalid Yubikey ' . $yk);
}

$localParams['otp'] = $yk . str_repeat('c', 32); // Fake an OTP, only used for logging.
$myLog->log(LOG_DEBUG, "Auth data:", $localParams);

/* Queue sync requests */
if (!$sync->queue($localParams, $localParams)) {
  logdie($myLog, 'ERROR Failed resync');
}

# We are done
logdie($myLog, "OK Initiated resync of $yk");
?>
