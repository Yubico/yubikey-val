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

$result = $db->customQuery("select id, active, created, secret, email, notes, otp from clients order by id");
while($row = $result->fetch(PDO::FETCH_ASSOC)){
  echo $row['id'] .
    "\t" . $row['active'] .
    "\t" . $row['created'] .
    "\t" . $row['secret'] .
    "\t" . $row['email'] .
    "\t" . $row['notes'] .
    "\t" . $row['otp'] .
    "\n";
 }

$result=null;
$db=null;


?>
