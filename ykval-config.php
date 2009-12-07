<?php                                                             # -*- php -*-

# For the validation interface.
$baseParams = array ();
$baseParams['__YKVAL_DB_HOST__'] = 'localhost';
$baseParams['__YKVAL_DB_NAME__'] = 'ykval';
$baseParams['__YKVAL_DB_USER__'] = 'ykval_verifier';
$baseParams['__YKVAL_DB_PW__'] = 'lab';
# For the validation server sync
$baseParams['__YKVAL_SYNC_POOL__'] = "http://api2.yubico.com/wsapi/sync;http://api3.yubico.com/wsapi/sync;http://api4.yubico.com/wsapi/sync";
$baseParams['__YKVAL_SYNC_INTERVAL__'] = 60;
$baseParams['__YKVAL_SYNC_MAX_SIMUL__'] = 50;
$baseParams['__YKVAL_SYNC_TIMEOUT__'] = 30;
$baseParams['__YKVAL_SYNC_OLD_LIMIT__'] = 1;

# For the get-api-key service.
$baseParams['__YKGAK_DB_HOST__'] = $baseParams['__YKVAL_DB_HOST__'];
$baseParams['__YKGAK_DB_NAME__'] = $baseParams['__YKVAL_DB_NAME__'];
$baseParams['__YKGAK_DB_USER__'] = 'ykval_getapikey';
$baseParams['__YKGAK_DB_PW__'] = 'secondpassword';
$baseParams['__YKGAK_ID__'] = '';
$baseParams['__YKGAK_KEY__'] = '';

# For the revoke service.
$baseParams['__YKR_DB_HOST__'] = $baseParams['__YKVAL_DB_HOST__'];
$baseParams['__YKR_DB_NAME__'] = $baseParams['__YKVAL_DB_NAME__'];
$baseParams['__YKR_DB_USER__'] = 'ykval_revoke';
$baseParams['__YKR_DB_PW__'] = 'thirdpassword';
$baseParams['__YKR_IP__'] = '1.2.3.4';




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
