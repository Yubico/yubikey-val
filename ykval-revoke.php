<?php
require_once 'ykval-common.php';
require_once 'ykval-config.php';
require_once 'ykval-db.php';

header("content-type: text/plain");

if ($baseParams['__YKR_IP__'] != $_SERVER["REMOTE_ADDR"]) {
  logdie("ERROR Authorization failed");
}

# Parse input
$yk = $_REQUEST["yk"];
$do = $_REQUEST["do"];
if (!$yk || !$do) {
  logdie("ERROR Missing parameter");
}
if (!preg_match("/^([cbdefghijklnrtuv]{0,16})$/", $yk)) {
  logdie("ERROR Unknown yk value: $yk");
}
if ($do != "enable" && $do != "disable") {
  logdie("ERROR Unknown do value: $do");
}

# Connect to db
$db = new Db($baseParams['__YKVAL_DB_DSN__'],
	     $baseParams['__YKVAL_DB_USER__'],
	     $baseParams['__YKVAL_DB_PW__'],
	     $baseParams['__YKVAL_DB_OPTIONS__'], 
	     'ykval-revoke:db');
if (!$db->connect()) {
  logdie("ERROR Database connect error");
}

# Check if key exists
$r = $db->findBy('yubikeys', 'yk_publicname', $yk, 1);
if (!$r) {
  logdie("ERROR Unknown yubikey: $yk");
}

# Enable/Disable the yubikey
if (!$db->updateBy('yubikeys', 'yk_publicname', $yk,
		   array('active'=>($do == "enable" ? "TRUE" : "FALSE")))) {
  logdie("ERROR Could not $do for $yk (rows $rows)");
}

# We are done
logdie("OK Processed $yk with $do");
?>
