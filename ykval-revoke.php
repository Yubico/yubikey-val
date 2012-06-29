<?php
require_once 'ykval-common.php';
require_once 'ykval-config.php';
require_once 'ykval-db.php';
require_once 'ykval-log.php';

header("content-type: text/plain");

$myLog = new Log('ykval-revoke');
$myLog->addField('ip', $_SERVER['REMOTE_ADDR']);

if (!in_array ($_SERVER["REMOTE_ADDR"], $baseParams['__YKREV_IPS__'])) {
  logdie($myLog, "ERROR Authorization failed (logged ". $_SERVER["REMOTE_ADDR"] .")");
}

# Parse input
$yk = $_REQUEST["yk"];
$do = $_REQUEST["do"];
if (!$yk || !$do) {
  logdie($myLog, "ERROR Missing parameter");
}
if (!preg_match("/^([cbdefghijklnrtuv]{0,16})$/", $yk)) {
  logdie($myLog, "ERROR Unknown yk value: $yk");
}
if ($do != "enable" && $do != "disable") {
  logdie($myLog, "ERROR Unknown do value: $do");
}

# Connect to db
$db = Db::GetDatabaseHandle($baseParams, 'ykval-revoke');
if (!$db->connect()) {
  logdie($myLog, "ERROR Database connect error");
}

# Check if key exists
$r = $db->findBy('yubikeys', 'yk_publicname', $yk, 1);
if (!$r) {
  logdie($myLog, "ERROR Unknown yubikey: $yk");
}

# Enable/Disable the yubikey
if (!$db->updateBy('yubikeys', 'yk_publicname', $yk,
		   array('active'=>($do == "enable" ? "1" : "0")))) {
  logdie($myLog, "ERROR Could not $do for $yk (rows $rows)");
}

# We are done
logdie($myLog, "OK Processed $yk with $do");
?>
