#!/usr/bin/php
<?php

set_include_path(get_include_path() . PATH_SEPARATOR .
		 "/etc/ykval:/usr/share/ykval");

require_once 'ykval-config.php';
require_once 'ykval-synclib.php';
require_once 'ykval-log.php';

if ($argc==2 && strcmp($argv[1], "autoconf") == 0) {
  print "yes\n";
  exit (0);
}

if ($argc==2 && strcmp($argv[1], "config") == 0) {

  echo "graph_title YK-VAL queue size\n";
  echo "graph_vlabel sync requests in queue\n";
  echo "graph_category ykval\n";

  echo "queuelength.label sync requests\n";
  echo "queuelength.draw AREA\n";

  exit (0);
}

$sync = new SyncLib('ykval-verify:synclib');
if (isset($_SERVER['REMOTE_ADDR'])) {
  $sync->addField('ip', $_SERVER['REMOTE_ADDR']);
}

$len = $sync->getQueueLength ();
echo "queuelength.value $len\n";

#%# family=auto
#%# capabilities=autoconf
?>
