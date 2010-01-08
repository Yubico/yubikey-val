<?php

require_once 'ykval-synclib.php';
require_once 'ykval-config.php';

if ($argc==2) $server=$argv[1];
 else {
   echo "Usage: " . $argv[0] . " server\n";
   exit;
 }

$sl = new SyncLib();

$resync = $baseParams['__YKVAL_SYNC_INTERVAL__'];
# Loop forever and resync

while (True) {
  $sl->reSync($baseParams['__YKVAL_SYNC_OLD_LIMIT__'], 10);
  
  sleep($baseParams['__YKVAL_SYNC_INTERVAL__']);
 }

?>