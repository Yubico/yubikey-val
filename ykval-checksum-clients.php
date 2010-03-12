#!/usr/bin/php
<?php

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

set_include_path(get_include_path() . PATH_SEPARATOR .
		 "/usr/share/ykval:/etc/ykval");

require_once 'ykval-db.php';
require_once 'ykval-config.php';

$logname="ykval-checksum-clients";
$myLog = new Log($logname);

$db=new Db($baseParams['__YKVAL_DB_DSN__'],
	   $baseParams['__YKVAL_DB_USER__'],
	   $baseParams['__YKVAL_DB_PW__'],
	   $baseParams['__YKVAL_DB_OPTIONS__'], 
	   $logname . ':db');

if (!$db->connect()) {
  $myLog->log(LOG_WARNING, "Could not connect to database");
  exit(1);
}

$everything = "";
$result=$db->customQuery("SELECT id, active, secret ".
			 "FROM clients ".
			 "ORDER BY id");
while($row = $result->fetch(PDO::FETCH_ASSOC)) {
  $everything = $everything .
    $row['id'] . "\t" . $row['active'] . "\t" . $row['secret'] .
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
