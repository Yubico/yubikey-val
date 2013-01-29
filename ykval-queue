#!/usr/bin/php
<?php

set_include_path(get_include_path() . PATH_SEPARATOR . "/usr/share/yubikey-val:/etc/yubico/val");

require_once 'ykval-synclib.php';
require_once 'ykval-config.php';
require_once 'ykval-log.php';

$sl = new SyncLib('ykval-queue:synclib');

# Loop forever and resync
do {
  $sl->reSync($baseParams['__YKVAL_SYNC_OLD_LIMIT__'],
	      $baseParams['__YKVAL_SYNC_RESYNC_TIMEOUT__']);
} while(sleep($baseParams['__YKVAL_SYNC_INTERVAL__'])==0);

?>
