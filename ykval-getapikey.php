<?php
require_once 'ykval-common.php';
require_once 'ykval-config.php';
require_once 'Auth/Yubico.php';

header("content-type: text/plain");

debug("Request: " . $_SERVER['QUERY_STRING']);

$conn = mysql_connect($baseParams['__YKGAK_DB_HOST__'],
		      $baseParams['__YKGAK_DB_USER__'],
		      $baseParams['__YKGAK_DB_PW__']);
if (!$conn) {
  logdie("code=connecterror");
}
if (!mysql_select_db($baseParams['__YKGAK_DB_NAME__'], $conn)) {
  logdie("code=selecterror");
}

$email = $_REQUEST["email"];
$otp = $_REQUEST["otp"];
if (!$email || !$otp || !(strpos($email . $otp, " ") === FALSE)) {
  logdie("code=noparam");
}

$yubi = &new Auth_Yubico($baseParams['__YKGAK_ID__'],
			 $baseParams['__YKGAK_KEY__']);
$auth = $yubi->verify($otp);
if (PEAR::isError($auth)) {
  logdie("code=badotp\nstatus=" . $auth->getMessage());
}

$sqlid = mysql_real_escape_string($email . " " . $yubikey);

$fh = fopen("/dev/urandom", "r")
  or logdie ("code=openerror");
$rnd = fread ($fh, 20)
  or logdie ("code=readerror");
fclose ($fh);
$b64rnd = base64_encode ($rnd);

$query = "SELECT MAX(id) FROM clients";
if (!mysql_query($query, $conn)) {
  debug("SQL query error: " . mysql_error());
  logdie("code=maxiderror");
}
$max = mysql_fetch_row ($result);
mysql_free_result($result);
$max = $max[0] + 1;

$query = "INSERT INTO clients (id, created, email, otp, secret) " .
  "VALUES (\"$max\", NOW(), " . mysql_quote($email) . ", " .
  mysql_quote($otp) . ", " . "\"$b64rnd\")";
if (!mysql_query($query, $conn)) {
  debug("SQL query error: " . mysql_error());
  logdie("code=inserterror");
}

mysql_close($conn);

debug("Successfully added client ID $max");
echo "code=ok\nmax=$max\nkey=$b64rnd\n";
?>
