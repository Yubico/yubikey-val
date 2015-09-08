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
require_once 'ykval-db.php';

$urls = $baseParams['__YKVAL_SYNC_POOL__'];
$shortnames = array_map("short_name", $urls);

if ($argc == 2 && strcmp($argv[1], "autoconf") == 0)
{
	if (is_array($urls) && count($urls) > 0)
	{
		echo "yes\n";
		exit(0);
	}

	echo "no (sync pool not configured)\n";
	exit(0);
}

if ($argc==2 && strcmp($argv[1], "config") == 0)
{
	echo "graph_title YK-VAL queue size\n";
	echo "graph_vlabel sync requests in queue\n";
	echo "graph_category ykval\n";

	foreach ($shortnames as $shortname)
	{
		echo "queuelength_${shortname}.label sync ${shortname}\n";
		echo "queuelength_${shortname}.draw AREASTACK\n";
		echo "queuelength_${shortname}.type GAUGE\n";
	}

	exit(0);
}

$db = Db::GetDatabaseHandle($baseParams, 'ykval-munin-queuelength');
if (!$db->connect())
	logdie($myLog, 'ERROR Database connect error (1)');

$res = $db->customQuery('select server,count(server) as count from queue group by server');
if ($res)
	$r = $res->fetchAll(PDO::FETCH_ASSOC);
else
	logdie($myLog, 'ERROR getting data from db');

foreach ($shortnames as $shortname)
{
	$count = 0;

	foreach ($r as $result)
	{
		if (short_name($result['server']) === $shortname)
		{
			$count = $result['count'];
			break;
		}
	}

	echo "queuelength_${shortname}.value $count\n";
}
