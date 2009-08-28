<?php                                                             # -*- php -*-

# For the validation interface.
$baseParams = array ();
$baseParams['__YKVAL_DB_HOST__'] = 'localhost';
$baseParams['__YKVAL_DB_NAME__'] = 'ykval';
$baseParams['__YKVAL_DB_USER__'] = 'ykval_verifier';
$baseParams['__YKVAL_DB_PW__'] = 'password';

# For the get-api-key service.
$baseParams['__YKGAK_DB_HOST__'] = $baseParams['__YKVAL_DB_HOST__'];
$baseParams['__YKGAK_DB_NAME__'] = $baseParams['__YKVAL_DB_NAME__'];
$baseParams['__YKGAK_DB_USER__'] = 'ykval_getapikey';
$baseParams['__YKGAK_DB_PW__'] = 'password';
$baseParams['__YKGAK_ID__'] = '';
$baseParams['__YKGAK_KEY__'] = '';

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
