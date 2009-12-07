<?php

require_once 'ykval-synclib.php';
require_once 'ykval-config.php';

$sl = new SyncLib();


# Loop forever and resync

while (True) {
  $sl->reSync(10);
  
  sleep(60);
 }

?>