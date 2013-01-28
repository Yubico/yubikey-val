<?php                                                             # -*- php -*-

//ykval will use the configuration stored in /etc/yubico/val/config-db.php, if that file exists. If it does not exist, the below values will be used.

if(!include '/etc/yubico/val/config-db.php') {
	$dbuser='ykval_verifier';
	$dbpass='yourpassword';
	$basepath='';
	$dbname='ykval';
	$dbserver='';
	$dbport='';
	$dbtype='mysql';
}


# For the validation interface.
$baseParams = array ();
$baseParams['__YKVAL_DB_DSN__'] = "$dbtype:dbname=$dbname;host=127.0.0.1"; # "oci:oracledb" for Oracle DB (with OCI library)
$baseParams['__YKVAL_DB_USER__'] = $dbuser;
$baseParams['__YKVAL_DB_PW__'] = $dbpass;
$baseParams['__YKVAL_DB_OPTIONS__'] = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);

# For the validation server sync
$baseParams['__YKVAL_SYNC_POOL__'] = array(/*"http://api2.example.com/wsapi/2.0/sync",*/
					   /*"http://api3.example.com/wsapi/2.0/sync",*/
					  /* "http://api4.example.com/wsapi/2.0/sync"*/);
# An array of IP addresses allowed to issue sync requests
# NOTE: You must use IP addresses here.
$baseParams['__YKVAL_ALLOWED_SYNC_POOL__'] = array(/*"1.2.3.4",*/
						   /*"2.3.4.5",*/
						   /*"3.4.5.6"*/);

# An array of IP addresses allowed to issue YubiKey activation/deactivation
# requests through ykval-revoke.php. NOTE: You must use IP addresses here.
$baseParams['__YKREV_IPS__'] = array(/*"127.0.0.1"*/);
# An array of IP addresses allowed to issue database resync requests through
# ykval-resync.php. NOTE: You must use IP addresses here.
$baseParams['__YKRESYNC_IPS__'] = array(/*"127.0.0.1"*/);

# Specify how often the sync daemon awakens
$baseParams['__YKVAL_SYNC_INTERVAL__'] = 10;
# Specify how long the sync daemon will wait for response
$baseParams['__YKVAL_SYNC_RESYNC_TIMEOUT__'] = 30;
# Specify how old entries in the database should be considered aborted attempts
$baseParams['__YKVAL_SYNC_OLD_LIMIT__'] = 10;

# These are settings for the validation server.
$baseParams['__YKVAL_SYNC_FAST_LEVEL__'] = 1;
$baseParams['__YKVAL_SYNC_SECURE_LEVEL__'] = 40;
$baseParams['__YKVAL_SYNC_DEFAULT_LEVEL__'] = 60;
$baseParams['__YKVAL_SYNC_DEFAULT_TIMEOUT__'] = 1;

// otp2ksmurls: Return array of YK-KSM URLs for decrypting OTP for
// CLIENT.  The URLs must be fully qualified, i.e., contain the OTP
// itself.
function otp2ksmurls ($otp, $client) {
  //if ($client == 42) {
  //  return array("http://another-ykkms.example.com/wsapi/decrypt?otp=$otp");
  //}

  //if (preg_match ("/^dteffujehknh/", $otp)) {
  //  return array("http://different-ykkms.example.com/wsapi/decrypt?otp=$otp");
  //}

  return array(
	       //"http://ykkms1.example.com/wsapi/decrypt?otp=$otp",
	       //"http://ykkms2.example.com/wsapi/decrypt?otp=$otp",
		"http://127.0.0.1/wsapi/decrypt?otp=$otp"
	       );
}

?>
