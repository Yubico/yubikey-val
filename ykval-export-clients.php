#!/usr/bin/php
<?php

set_include_path(get_include_path() . PATH_SEPARATOR . "/usr/share/ykval:/etc/ykval");

require_once 'ykval-config.php';
require_once 'ykval-db.php';


$logname="ykval-export";
$myLog = new Log($logname);

$db = Db::GetDatabaseHandle($baseParams, $logname);

if (!$db->connect()) {
  $myLog->log(LOG_WARNING, "Could not connect to database");
  exit(1);
 }

$result = $db->customQuery("select id, active, created, secret, email, notes, otp from clients order by id");
while($row = $db->fetchArray($result)) {
  echo $db->getRowValue($row, 'id') .
    "\t" . $db->getRowValue($row, 'active') .
    "\t" . $db->getRowValue($row, 'created') .
    "\t" . $db->getRowValue($row, 'secret') .
    "\t" . $db->getRowValue($row, 'email') .
    "\t" . $db->getRowValue($row, 'notes') .
    "\t" . $db->getRowValue($row, 'otp') .
    "\n";
}

$db->closeCursor($result);
$db->disconnect();

$result=null;
$db=null;


?>
