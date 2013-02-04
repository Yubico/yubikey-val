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
