<?php

require_once 'ykval-synclib.php';
require_once 'ykval-config.php';

$sl = new SyncLib();

$resync = $baseParams['__YKVAL_SYNC_INTERVAL__'];
# Loop forever and resync

while (True) {
  $sl->reSync($baseParams['__YKVAL_SYNC_OLD_LIMIT__']);
  
  sleep($baseParams['__YKVAL_SYNC_INTERVAL__']);
 }

?>