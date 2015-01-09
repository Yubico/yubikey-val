#!/usr/bin/php
<?php

# Copyright (c) 2010-2014 Yubico AB
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

require_once 'ykval-synclib.php';
require_once 'ykval-config.php';
require_once 'ykval-log.php';

if ($argc != 3) {
  print "warning and critical levels have to be given on commandline\n";
  exit (3);
}

$warning = $argv[1];
$critical = $argv[2];

$sync = new SyncLib('ykval-verify:synclib');

$len = $sync->getQueueLength ();

$message = "Queue length is $len";

if($len > $critical) {
  print("CRITICAL: $message\n");
  exit (2);
} elseif($len > $warning) {
  print("WARNING: $message\n");
  exit (1);
} else {
  print("OK: $message\n");
  exit (0);
}

?>
