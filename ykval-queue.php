#!/usr/bin/php
<?php

if ($argc==2 && strcmp($argv[1], "help")==0) {
  echo "\nUsage:\n\n";
  echo $argv[0] . " install  \t- Installs start scripts for daemon\n";
  echo $argv[0] . " file     \t- Starts sync daemon. file is sourced and can include for example path configuration\n";
  echo "\n";
  exit();
 }
if ($argc==2 && strcmp($argv[1], "install")!=0) {
  set_include_path(get_include_path() . PATH_SEPARATOR . $argv[1]);
 }

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
  } else {
    echo "Start script already created\n";
    echo "To start daemon use: /etc/init.d/".$appname." start\n";
  }
  exit();
 }

require_once 'ykval-synclib.php';
require_once 'ykval-config.php';
require_once 'ykval-log.php';

System_Daemon::start();                           // Spawn Deamon!
/* Application start */

$sl = new SyncLib('ykval-queue:synclib');

# Loop forever and resync

$res==0;
while ($res==0) {
  $sl->reSync($baseParams['__YKVAL_SYNC_OLD_LIMIT__'],
	      $baseParams['__YKVAL_SYNC_RESYNC_TIMEOUT__']);
  $res=sleep($baseParams['__YKVAL_SYNC_INTERVAL__']);
 }

System_Daemon::stop();

?>
