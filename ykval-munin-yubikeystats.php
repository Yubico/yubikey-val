#!/usr/bin/php
<?php

set_include_path(get_include_path() . PATH_SEPARATOR .
		 "/etc/yubico/val:/usr/share/yubikey-val");

require_once 'ykval-config.php';
require_once 'ykval-db.php';

if ($argc==2 && strcmp($argv[1], "autoconf") == 0) {
  print "yes\n";
  exit (0);
}

if ($argc==2 && strcmp($argv[1], "config") == 0) {

  echo "graph_title YK-VAL YubiKey stats\n";
  echo "graph_vlabel Known YubiKeys\n";
  echo "graph_category ykval\n";

  echo "yubikeys_enabled.label Enabled YubiKeys\n";
  echo "yubikeys_enabled.draw AREA\n";

  echo "yubikeys_disabled.label Disabled YubiKeys\n";
  echo "yubikeys_disabled.draw STACK\n";

  echo "yubikeys_1month.label YubiKeys seen last month\n";
  echo "yubikeys_1month.draw LINE2\n";

  echo "clients_enabled.label Enabled validation clients\n";
  echo "clients_enabled.draw LINE2\n";

  echo "clients_disabled.label Disabled validation clients\n";
  echo "clients_disabled.draw LINE2\n";

  exit (0);
}

# Connect to db
$db = Db::GetDatabaseHandle($baseParams, 'ykval-munin-yubikeystats');
if (!$db->connect()) {
  logdie($myLog, 'ERROR Database connect error (1)');
}

function get_count($db, $table, $conditions) {
  $res = $db->customQuery('SELECT count(1) as count FROM ' . $table . ' WHERE ' . $conditions);
  if ($res) {
    $r = $res->fetch(PDO::FETCH_ASSOC);
    return $r['count'];
  }

  return Null;
}

if ($count = get_count($db, 'yubikeys', 'active=true')) {
  echo "yubikeys_enabled.value " . $count . "\n";
}

if ($count = get_count($db, 'yubikeys', 'active=false')) {
  echo "yubikeys_disabled.value " . $count . "\n";
}

if ($count = get_count($db, 'yubikeys', 'modified >= ' . (time() - (31 * 86400)))) {
  echo "yubikeys_1month.value " . $count . "\n";
}

if ($count = get_count($db, 'clients', 'active=true')) {
  echo "clients_enabled.value " . $count . "\n";
}

if ($count = get_count($db, 'clients', 'active=false')) {
  echo "clients_disabled.value " . $count . "\n";
}


#%# family=auto
#%# capabilities=autoconf
?>
