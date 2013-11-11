#!/bin/bash
set -e
set -x

if [ "x$DB" = "xmysql" ]; then
  dbuser=travis

  mysql -u $dbuser -e 'create database ykval;'
  mysql -u $dbuser ykval < ykval-db.sql
elif [ "x$DB" = "xpgsql" ]; then
  dbuser=postgres

  psql -U $dbuser -c 'create database ykval;'
  psql -U $dbuser ykval < ykval-db.sql
elif [ "x$DB" = "xsqlite" ]; then
  dbuser=""
  dblocation=`mktemp`
  sqlite3 $dblocation < ykval-db.sql
  sed -i "s,^.*YKVAL_DB_DSN.*$,\$baseParams['__YKVAL_DB_DSN__'] = \"sqlite:$dblocation\";," ykval-config.php
else
  echo "unknown DB $DB"
  exit 1
fi

cat > config-db.php << EOF
<?php
\$dbuser = '$dbuser';
\$dbpass = '';
\$dbname = 'ykval';
\$dbtype = '$DB';
?>
EOF
sudo mkdir -p /etc/yubico/val/
sudo chmod 0755 /etc/yubico/val/
sudo mv config-db.php /etc/yubico/val/

sudo perl travis/server.pl &

echo '1,1,1383728711,EHmo8FMxuhumBlTinC4uYL0Mgwg=,,,' | php ykval-import-clients

set +e

function run_command () {
	id=$1
	otp=$2
	nonce=$3
	echo '' | php -B "\$_SERVER = array('REMOTE_ADDR' => '127.0.0.1', 'QUERY_STRING' => 'id=$id&otp=$otp&nonce=$nonce', 'REQUEST_URI' => '/wsapi/2.0/verify');\$_GET = array('otp' => '$otp', 'id' => '$id', 'nonce' => '$nonce');" -F ykval-verify.php
}

id="1"
otp="idkfefrdhtrutjduvtcjbfeuvhehdvjjlbchtlenfgku"
nonce="kakakakakakakakakakakaka"
run_command $id $otp $nonce | grep -q 'status=OK'
if [ $? != 0 ]; then
  sudo tail /var/log/syslog
  exit 1
else
  echo "Success 1"
fi

id="2"
run_command $id $otp $nonce | grep -q 'status=NO_SUCH_CLIENT'
if [ $? != 0 ]; then
  sudo tail /var/log/syslog
  exit 1
else
  echo "Success 1"
fi

id="1"
run_command $id $otp $nonce | grep -q 'status=REPLAYED_REQUEST'
if [ $? != 0 ]; then
  sudo tail /var/log/syslog
  exit 1
else
  echo "Success 2"
fi

nonce="bakabakabakabaka"
run_command $id $otp $nonce | grep -q 'status=REPLAYED_OTP'
if [ $? != 0 ]; then
  sudo tail /var/log/syslog
  exit 1
else
  echo "Success 3"
fi

num=`php ykval-export | wc -l`
if [ $num != 1 ]; then
  echo "failed exporting"
  php ykval-export
  exit 1
else
  echo "Success export"
fi

num=`php ykval-export-clients | wc -l`
if [ $num != 1 ]; then
  echo "failed exporting clients"
  php ykval-export-clients
  exit 1
else
  echo "Success export-clients"
fi
