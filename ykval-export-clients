#!/usr/bin/php
<?php

set_include_path(get_include_path() . PATH_SEPARATOR . "/usr/share/yubikey-val:/etc/yubico/val");

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
  echo $row['id'] .
    "," . (int)$row['active'] .
    "," . $row['created'] .
    "," . $row['secret'] .
    "," . $row['email'] .
    "," . $row['notes'] .
    "," . $row['otp'] .
    "\n";
}

$db->closeCursor($result);
$db->disconnect();

$result=null;
$db=null;


?>
