#!/usr/bin/php
<?php

set_include_path(get_include_path() . PATH_SEPARATOR .
		 "/etc/yubico/val:/usr/share/yubikey-val");

require_once 'ykval-config.php';

function url2shortname ($url) {
  if (preg_match("/^[^\/]+\/\/([a-z0-9-]+)/", $url, $name)==0){
    echo "Cannot match URL hostname: " . $url . "\n";
    exit (1);
  }

  return $name[1];
}

$ksms = otp2ksmurls ("ccccccccfnkjtvvijktfrvvginedlbvudjhjnggndtck", 16);
$shortksms = array_map("url2shortname", $ksms);

if ($argc==2 && strcmp($argv[1], "autoconf") == 0) {
  print "yes\n";
  exit (0);
}

if ($argc==2 && strcmp($argv[1], "config") == 0) {

  echo "multigraph ykval_ksmlatency\n";
  echo "graph_title KSM latency\n";
  echo "graph_vlabel Average KSM Decrypt Latency (seconds)\n";
  echo "graph_category ykval\n";
  echo "graph_width 400\n";

  foreach ($shortksms as $shortksm) {
    echo "${shortksm}_avgwait.label ${shortksm}\n";
    echo "${shortksm}_avgwait.type GAUGE\n";
    echo "${shortksm}_avgwait.info Average wait time for KSM decrypt\n";
    echo "${shortksm}_avgwait.min 0\n";
    echo "${shortksm}_avgwait.draw LINE1\n";
  }

  exit (0);
}

echo "multigraph ykval_ksmlatency\n";
foreach ($ksms as $ksm) {
  $shortksm = url2shortname ($ksm);
  $time = `curl --silent --write-out '%{time_total}' --max-time 3 '$ksm' -o /dev/null`;
  if (preg_match("/^3\./", $time)) {
    $time = "timeout";
  }
  if (preg_match("/^0\.000/", $time)) {
    $time = "error";
  }
  echo "${shortksm}_avgwait.value $time\n";
}

#%# family=auto
#%# capabilities=autoconf
?>
