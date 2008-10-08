<h3>Generate a signature</h3>

<?php require_once '../yubiphpbase/appinclude.php';
      require_once '../yubiphpbase/yubi_lib.php';
      require_once 'common.php';

$trace = true;

$act = getHttpVal('act', '');

if ($act == '') {
	echo '<form action=sign_demo.php method=post>'.
		'<input name=act value=sign type=hidden>'.
		'api key: (use your api key issued to you by Yubico in b64 format): ' .
		'<input name=apikey size=45 maxlength=100 value="kNapft02c1a81N4MEMDcC/mgcGc="><p>'.
		'id (your client id): <input name=id size=5 maxlength=10><p>'.	
		'otp: <input name=otp size=45 maxlength=100><p>'.
		'<input type=submit value=Sign>'.
		'</form>';
	exit;
}

$id = getHttpVal('id', '');
$otp = getHttpVal('otp', '');
$t = getHttpVal('t', '');
$apiKey = base64_decode(getHttpVal('apikey', ''));

$a['id']=$id;
$a['otp']=$otp;

if ($t != '') {
  $a['t']=$t;
}

$hmac = sign($a, $apiKey, true);

?>
