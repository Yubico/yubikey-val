#!/usr/bin/php
<?php

set_include_path(get_include_path() . PATH_SEPARATOR .
		 "/etc/ykval:/usr/share/ykval");

require_once 'ykval-synclib.php';
require_once 'ykval-config.php';
require_once 'ykval-log.php';

function ksmurl2shortname ($ksmurl) {
  if (preg_match("/^[^\/]+\/\/([a-z0-9-]+)/", $ksmurl, $ksmname)==0){
    echo "Cannot match ksm name: " . $ksmurl . "\n";
    exit (1);
  }

  return $ksmname[1];
}
$ksms = otp2ksmurls ("ccccccccfnkjtvvijktfrvvginedlbvudjhjnggndtck", 16);
$shortksms = array_map("ksmurl2shortname", $ksms);

if ($argc==2 && strcmp($argv[1], "autoconf") == 0) {
  print "yes\n";
  exit (0);
}

if ($argc==2 && strcmp($argv[1], "config") == 0) {

  echo "multigraph yk_latency\n";
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

echo "multigraph diskstats_latency\n";
foreach ($ksms as $ksm) {
  $shortksm = ksmurl2shortname ($ksm);
  $time = `curl --silent --write-out '%{time_total}' --max-time 42 '$ksm' -o /dev/null`;
  echo "${shortksm}_avgwait.value $time\n";
}

?>
