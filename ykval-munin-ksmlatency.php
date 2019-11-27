#!/usr/bin/php
<?php

# Copyright (c) 2010-2015 Yubico AB
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

set_include_path(implode(PATH_SEPARATOR, array(
	get_include_path(),
	'/usr/share/yubikey-val',
	'/etc/yubico/val',
)));

require_once 'ykval-config.php';
require_once 'ykval-common.php';

# FIXME
# otp and client ID should be moved to a munin environment variable
$urls = otp2ksmurls('ccccccccfnkjtvvijktfrvvginedlbvudjhjnggndtck', 16);

if (($endpoints = endpoints($urls)) === false)
{
	echo "Cannot parse URLs from ksm url list\n";
	exit(1);
}

if ($argc == 2 && strcmp($argv[1], 'autoconf') == 0)
{
	echo "yes\n";
	exit(0);
}

if ($argc == 2 && strcmp($argv[1], 'config') == 0)
{
	echo "multigraph ykval_ksmlatency\n";
	echo "graph_title KSM latency\n";
	echo "graph_vlabel Average KSM Decrypt Latency (seconds)\n";
	echo "graph_category ykval\n";
	echo "graph_width 400\n";

	foreach ($endpoints as $endpoint)
	{
		list ($internal, $label, $url) = $endpoint;

		echo "${internal}_avgwait.label ${label}\n";
		echo "${internal}_avgwait.type GAUGE\n";
		echo "${internal}_avgwait.info Average wait time for KSM decrypt\n";
		echo "${internal}_avgwait.min 0\n";
		echo "${internal}_avgwait.draw LINE1\n";
	}

	exit(0);
}

echo "multigraph ykval_ksmlatency\n";

foreach ($endpoints as $endpoint)
{
	list ($internal, $label, $url) = $endpoint;

	if (($total_time = total_time($url)) === false)
		$total_time = 'error';

	echo "${internal}_avgwait.value ${total_time}\n";
}

exit(0);
