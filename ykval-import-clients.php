#!/usr/bin/php
<?php

set_include_path(get_include_path() . PATH_SEPARATOR . "/usr/share/ykval:/etc/ykval");

require_once 'ykval-config.php';
require_once 'ykval-db.php';


$logname="ykval-import";
$myLog = new Log($logname);

$db = Db::GetDatabaseHandle($baseParams, $logname);

if (!$db->connect()) {
  $myLog->log(LOG_WARNING, "Could not connect to database");
  error_log("Could not connect to database");
  exit(1);
 }


while ($res=fgetcsv(STDIN, 0, "\t")) {
  $params=array("id"=>$res[0],
		"active"=>$res[1],
		"created"=>$res[2],
		"secret"=>$res[3],
		"email"=>$res[4],
		"notes"=>$res[5],
		"otp"=>$res[6]);


  $query="SELECT * FROM clients WHERE id='" . $params['id'] . "'";
  $result=$db->customQuery($query);
  if($db->rowCount($result) == 0) {
    // We didn't have the id in database so we need to do insert instead
    $query="INSERT INTO clients " .
      "(id,active,created,secret,email,notes,otp) VALUES " .
      "('" . $params["id"] . "', " .
      "'" . $params["active"] . "', " .
      "'" . $params['created'] . "'," .
      "'" . $params['secret'] . "'," .
      "'" . $params['email'] . "'," .
      "'" . $params['notes'] . "'," .
      "'" . $params['otp'] . "')";

    if(!$db->customQuery($query)){
      $myLog->log(LOG_ERR, "Failed to insert new client with query " . $query);
      error_log("Failed to insert new client with query " . $query);
      exit(1);
    }
  }
  $db->closeCursor($result);
 }


$myLog->log(LOG_NOTICE, "Successfully imported clients to database");
echo "Successfully imported clients to database\n";
