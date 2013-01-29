#!/usr/bin/php
<?php

$verbose = 0;
if (isset($argv[1])) {
  if ($argv[1] == "-h" || $argv[1] == "--help") {
    print "Usage: " . $argv[0] . " [-h|--help] [-v]\n";
    exit(1);
  }

  if ($argv[1] && $argv[1] != "-v") {
    print $argv[0] . ": invalid option -- '" . $argv[0] . "'\n";
    print "Try `" . $argv[0] . " --help' for more information.\n";
    exit(1);
  }

  $verbose = $argv[1] == "-v";
}

set_include_path(get_include_path() . PATH_SEPARATOR .
		 "/usr/share/yubikey-val:/etc/yubico/val");

require_once 'ykval-config.php';
require_once 'ykval-db.php';

$logname="ykval-checksum-deactivated";
$myLog = new Log($logname);

$db = Db::GetDatabaseHandle($baseParams, $logname);

if (!$db->connect()) {
  $myLog->log(LOG_WARNING, "Could not connect to database");
  exit(1);
}

$everything = "";
$result=$db->customQuery("SELECT yk_publicname, yk_counter, yk_use ".
			 "FROM yubikeys WHERE active = false ".
			 "ORDER BY yk_publicname");
while($row = $result->fetch(PDO::FETCH_ASSOC)) {
  $everything = $everything .
    $row['yk_publicname'] . "\t" . $row['yk_counter'] . "\t" . $row['yk_use'] .
    "\n";
}

$hash = sha1 ($everything);

if ($verbose) {
  print $everything;
}
print substr ($hash, 0, 10) . "\n";

$result=null;
$db=null;

?>
