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
		 "/usr/share/ykval:/etc/ykval");

require_once 'ykval-config.php';
require_once 'ykval-db.php';

$logname="ykval-checksum-clients";
$myLog = new Log($logname);

$db = Db::GetDatabaseHandle($baseParams, $logname);

if (!$db->connect()) {
  $myLog->log(LOG_WARNING, "Could not connect to database");
  exit(1);
}

$everything = "";
$result=$db->customQuery("SELECT id, active, secret ".
			 "FROM clients ".
			 "ORDER BY id");
while($row = $db->fetchArray($result)) {
  $active = $row['active'];
  if ($active == "") {
    # For some reason PostgreSQL returns empty strings for false values?!
    $active = "0";
  }
  $everything = $everything .
    $row['id'] . "\t" . $active . "\t" .
    $row['secret'] . "\n";
}

$db->closeCursor($result);
$hash = sha1 ($everything);

if ($verbose) {
  print $everything;
}
print substr ($hash, 0, 10) . "\n";

$result=null;
$db=null;

?>
