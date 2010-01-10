#!/usr/bin/php -q
<?php

require_once 'ykval-synclib.php';
require_once 'ykval-config.php';
require_once "System/Daemon.php";                 

$appname="ykval-queue";

System_Daemon::setOption("appName", $appname);  
System_Daemon::setOption("appDescription", "Yubico val-server sync daemon"); 
System_Daemon::setOption("authorName", "olov@yubico.com");  
System_Daemon::setOption("authorEmail", "olov@yubico.com"); 

if ($argc==2 && strcmp($argv[1], "install")==0) {
  $autostart_path = System_Daemon::writeAutoRun();
  if ($autostart_path!=1){ 
    echo "Successfully created start script at " . $autostart_path . "\n";
    echo "To start daemon use: /etc/init.d/".$appname." start\n";
    exit();
  } else {
    echo "Start script already created\n";
    echo "To start daemon use: /etc/init.d/".$appname." start\n";
    exit();
  }
 }

System_Daemon::start();                           // Spawn Deamon!
/* Application start */

$sl = new SyncLib();

# Loop forever and resync

$res==0;
while ($res==0) {
  $sl->reSync($baseParams['__YKVAL_SYNC_OLD_LIMIT__'], 
	      $baseParams['__YKVAL_SYNC_RESYNC_TIMEOUT__']);
  $res=sleep($baseParams['__YKVAL_SYNC_INTERVAL__']);
 }

error_log("Stopping " . $appname);
System_Daemon::stop();

?>