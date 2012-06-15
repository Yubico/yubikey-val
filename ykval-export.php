#!/usr/bin/php
<?php

set_include_path(get_include_path() . PATH_SEPARATOR . "/usr/share/ykval:/etc/ykval");

require_once 'ykval-config.php';
require_once 'ykval-db.php';


$logname="ykval-export";
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

$result=$db->customQuery("SELECT active, created, modified, yk_publicname, yk_counter, yk_use, yk_low, yk_high, nonce, notes FROM yubikeys ORDER BY yk_publicname");
while($row = $result->fetch(PDO::FETCH_ASSOC)){
  echo (int)$row['active'] .
    "\t" . $row['created'] .
    "\t" . $row['modified'] .
    "\t" . $row['yk_publicname'] .
    "\t" . $row['yk_counter'] .
    "\t" . $row['yk_use'] .
    "\t" . $row['yk_low'] .
    "\t" . $row['yk_high'] .
    "\t" . $row['nonce'] .
    "\t" . $row['notes'] .
    "\n";
 }

$result=null;
$db=null;


?>