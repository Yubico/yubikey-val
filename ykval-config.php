<?php                                                             # -*- php -*-

# For the validation interface.
$baseParams = array ();
$baseParams['__YKVAL_DB_DSN__'] = "mysql:dbname=ykval;host=127.0.0.1";
$baseParams['__YKVAL_DB_USER__'] = 'ykval_verifier';
$baseParams['__YKVAL_DB_PW__'] = 'lab';
$baseParams['__YKVAL_DB_OPTIONS__'] = array();

# For the validation server sync
$baseParams['__YKVAL_SYNC_POOL__'] = array("http://1.2.3.4/wsapi/2.0/sync", 
					   "http://2.3.4.5/wsapi/2.0/sync", 
					   "http://3.4.5.6/wsapi/2.0/sync");
# An array of IP addresses allowed to issue sync requests
$baseParams['__YKVAL_ALLOWED_SYNC_POOL__'] = array("1.2.3.4", 
						   "2.3.4.5", 
						   "3.4.5.6");

# Specify how often the sync daemon awakens
$baseParams['__YKVAL_SYNC_INTERVAL__'] = 10;
# Specify how long the sync daemon will wait for response
$baseParams['__YKVAL_SYNC_RESYNC_TIMEOUT__'] = 30;
# Specify how old entries in the database should be considered aborted attempts
$baseParams['__YKVAL_SYNC_OLD_LIMIT__'] = 10;

# These are settings for the validation server.
$baseParams['__YKVAL_SYNC_FAST_LEVEL__'] = 1;
$baseParams['__YKVAL_SYNC_SECURE_LEVEL__'] = 50;
$baseParams['__YKVAL_SYNC_DEFAULT_LEVEL__'] = 50;
$baseParams['__YKVAL_SYNC_DEFAULT_TIMEOUT__'] = 1;

$baseParams['__YKVAL_SYNC_MAX_SIMUL__'] = 50;

// otp2ksmurls: Return array of YK-KSM URLs for decrypting OTP for
// CLIENT.  The URLs must be fully qualified, i.e., contain the OTP
// itself.
function otp2ksmurls ($otp, $client) {
  if ($client == 42) {
    return array("http://another-ykkms.example.com/wsapi/decrypt?otp=$otp");
  }

  if (preg_match ("/^dteffujehknh/", $otp)) {
    return array("http://different-ykkms.example.com/wsapi/decrypt?otp=$otp");
  }

  return array(
	       "http://ykkms1.example.com/wsapi/decrypt?otp=$otp",
	       "http://ykkms2.example.com/wsapi/decrypt?otp=$otp",
	       );
}

?>
