#!/usr/bin/php
<?php

# Copyright (c) 2010-2013 Yubico AB
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are
# met:
#
#   * Redistributions of source code must retain the above copyright
#     notice, this list of conditions and the following disclaimer.
#
#   * Redistributions in binary form must reproduce the above
#     copyright notice, this list of conditions and the following
#     disclaimer in the documentation and/or other materials provided
#     with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
# "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
# LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
# A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
# OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
# SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
# LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
# DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
# THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
# (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
# OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

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

$urls = $baseParams['__YKVAL_SYNC_POOL__'];
$shortnames = array_map("url2shortname", $urls);

if ($argc==2 && strcmp($argv[1], "autoconf") == 0) {
  print "yes\n";
  exit (0);
}

if ($argc==2 && strcmp($argv[1], "config") == 0) {

  echo "multigraph ykval_vallatency\n";
  echo "graph_title VAL latency\n";
  echo "graph_vlabel Average VAL Latency (seconds)\n";
  echo "graph_category ykval\n";
  echo "graph_width 400\n";

  foreach ($shortnames as $shortname) {
    echo "ipv4${shortname}_avgwait.label IPv4-${shortname}\n";
    echo "ipv4${shortname}_avgwait.type GAUGE\n";
    echo "ipv4${shortname}_avgwait.info Average VAL round-trip latency\n";
    echo "ipv4${shortname}_avgwait.min 0\n";
    echo "ipv4${shortname}_avgwait.draw LINE1\n";

    echo "ipv6${shortname}_avgwait.label IPv6-${shortname}\n";
    echo "ipv6${shortname}_avgwait.type GAUGE\n";
    echo "ipv6${shortname}_avgwait.info Average VAL round-trip latency\n";
    echo "ipv6${shortname}_avgwait.min 0\n";
    echo "ipv6${shortname}_avgwait.draw LINE1\n";
  }

  exit (0);
}

echo "multigraph ykval_vallatency\n";
foreach ($urls as $url) {
  $shortname = url2shortname ($url);
  $cmd = "--user-agent ykval-munin-vallatency/1.0 --silent --write-out '%{time_total}' --max-time 3 '$url' -o /dev/null";
  foreach (array('ipv4', 'ipv6') as $ipv) {
    $time = `curl --$ipv $cmd`;
    if (preg_match("/^3\./", $time)) {
      $time = "timeout";
    }
    if (preg_match("/^0\.000/", $time)) {
      $time = "error";
    }
    echo "$ipv${shortname}_avgwait.value $time\n";
  }
}

#%# family=auto
#%# capabilities=autoconf
?>
