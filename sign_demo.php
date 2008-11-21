<?php
require_once '../yubiphpbase/appinclude.php';
require_once '../yubiphpbase/yubi_lib.php';
require_once 'common.php';

$trace = true;

$act = getHttpVal('act', '');

if ($act == 'sign_req') {
	$id = getHttpVal('id', '');
	$otp = getHttpVal('otp', '');
	$t = getHttpVal('t', '');
	$apiKey = base64_decode(getHttpVal('apikey', ''));

	$a['id'] = $id;
	$a['otp'] = $otp;

	if ($t != '') {
		$a['t'] = $t;
	}

	$hmac = sign($a, $apiKey, true);

//	echo '<a href=verify_debug.php?'.$qs.'&h='.urlencode($hmac).
//		'>Test submit the request >> </a>';

} else if ($act == 'sign_resp') {
	$status = getHttpVal('status', '');
	$t = getHttpVal('t', '');
	$info = getHttpVal('info', '');
	$apiKey = base64_decode(getHttpVal('apikey', ''));

	$a['status'] = $status;
	$a['t'] = $t;

	if ($info != '') {
		$a['info'] = $info;
	}

	$hmac = sign($a, $apiKey, true);
}

echo '<hr><table><tr><td valign=top><h3>Generate a request signature</h3>'.
	'<form action=sign_demo.php method=post>' .
	'<input name=act value=sign_req type=hidden>' .
	'api key: (use your api key issued to you by Yubico in b64 format): ' .
	'<input name=apikey size=45 maxlength=100 value="kNapft02c1a81N4MEMDcC/mgcGc="><p>' .
	'id (your client id): <input name=id size=5 maxlength=10><p>' .
	'otp: <input name=otp size=45 maxlength=100><p>' .
	'<input type=submit value="Test sign the request">' .
	'</form>'.
	'</td>';

echo '<td valign=top><h3>Generate a response signature</h3>'.
	'<form action=sign_demo.php method=post>' .
	'<input name=act value=sign_resp type=hidden>' .
	'api key: (put your api key here in b64 format): ' .
	'<input name=apikey size=45 maxlength=100 value="kNapft02c1a81N4MEMDcC/mgcGc="><p>' .
	'Status: <select name=status>
<option value=S_OK>OK
<option value=S_BAD_OTP>BAD_OTP
<option value=S_BAD_CLIENT>BAD_CLIENT
<option value=S_REPLAYED_OTP>REPLAYED_OTP
<option value=S_BAD_SIGNATURE>BAD_SIGNATURE
<option value=S_MISSING_PARAMETER>MISSING_PARAMETER
<option value=S_OPERATION_NOT_ALLOWED>OPERATION_NOT_ALLOWED
<option value=S_BACKEND_ERROR>BACKEND_ERROR
</select><p>'.
	'Time stamp: <input name=t size=45 maxlength=100 value='.getUTCTimeStamp().'><p>' .
	'info: <input name=info size=45 maxlength=100><p>' .
	'<input type=submit value="Test sign the response">' .
	'</form>'.
	'</td></tr></table>';

?>
