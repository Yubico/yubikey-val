<?php
require_once 'ykval-common.php';
require_once 'ykval-config.php';

header("content-type: text/plain");

debug("Request: " . $_SERVER['QUERY_STRING']);

if ($baseParams['__YKR_IP__'] != $_SERVER["REMOTE_ADDR"]) {
  logdie("ERROR Authorization failed");
}

# Connect to db
$conn = mysql_connect($baseParams['__YKR_DB_HOST__'],
                      $baseParams['__YKR_DB_USER__'],
                      $baseParams['__YKR_DB_PW__'])
  or die('Could not connect to database: ' . mysql_error());
mysql_select_db($baseParams['__YKR_DB_NAME__'], $conn)
or die('Could not select database');

# Parse input
$yk = $_REQUEST["yk"];
$do = $_REQUEST["do"];
if (!$yk) {
  logdie("ERROR Missing parameter");
}
if ($do != "enable" && $do != "disable") {
  logdie("ERROR Unknown do value: $do");
}

# Check if key exists
$stmt = 'SELECT publicName FROM yubikeys WHERE publicName=' .
  mysql_quote (modhex2b64($yk));
$r = query($conn, $stmt);
if (mysql_num_rows($r) != 1) {
  logdie("ERROR Unknown yubikey $yk");
}

# Enable/Disable the yubikey
$stmt = 'UPDATE yubikeys SET active = ' .
  ($do == "enable" ? "TRUE" : "FALSE") .
  ' WHERE publicName=' . mysql_quote (modhex2b64($yk));
query($conn, $stmt);
$rows = mysql_affected_rows();
if ($rows != 1) {
  logdie("ERROR Could not $do for $yk (rows $rows)");
}

# We are done
logdie("OK Processed $yk with $do");
?>
