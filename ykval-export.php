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
while($row = $db->fetchArray($result)){
  echo     $db->getRowValue($row, 'active') .
    "\t" . $db->getRowValue($row, 'created') .
    "\t" . $db->getRowValue($row, 'modified') .
    "\t" . $db->getRowValue($row, 'yk_publicname') .
    "\t" . $db->getRowValue($row, 'yk_counter') .
    "\t" . $db->getRowValue($row, 'yk_use') .
    "\t" . $db->getRowValue($row, 'yk_low') .
    "\t" . $db->getRowValue($row, 'yk_high') .
    "\t" . $db->getRowValue($row, 'nonce') .
    "\t" . $db->getRowValue($row, 'notes') .
    "\n";
 }

$db->closeCursor($result);
$db->disconnect();
$result=null;
$db=null;


?>
