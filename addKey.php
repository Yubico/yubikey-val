<?php require_once '../yubiphpbase/appinclude.php';
	  require_once '../yubiphpbase/yubi_lib.php';
	  require_once 'common.php';
	  
header("content-type: text/plain");

$tokenId = $secret = '';

$nonce = getHttpVal('nonce', '');
if ($nonce == '') {
	reply(S_MISSING_PARAMETER, '', $client, '', 'nonce');
	exit;
}

$client = getHttpVal('id', 0);
if ($client <= 0) {
	reply(S_MISSING_PARAMETER, '', $client, $nonce, 'client');
	exit;
}
$ci = getClientInfo($client);

$h = getHttpVal('h', '');
if ($h == '') {
	reply(S_MISSING_PARAMETER, '', $client, $nonce, 'h');
	exit;
}

$op = getHttpVal('operation', '');

if ($op == '') {
	reply(S_MISSING_PARAMETER, '', $client, $nonce, 'operation');
	exit;
} else if ($op != 'add_key') {
	reply(S_OPERATION_NOT_ALLOWED, $ci['secret'], $client, $nonce, $op);
	exit;
}

if (! isset($ci['id'])) {
	debug('Client '.$client.' not found!');
	$client = 1;
	if (($ci = getClientInfo($client)) == null)  {
		debug('Root client not found, service not installed properly!');
		reply(S_BACKEND_ERROR, '', $client, $nonce, 'root client');
		exit;
	}	
	reply(S_BAD_CLIENT, $ci['secret'], $client, $nonce);
	exit;
}

if ($ci['perm_id'] != 1 && $ci['perm_id'] != 2) {
  	reply(S_OPERATION_NOT_ALLOWED, $ci['secret'], $client, $nonce, $ci['perm_id']);
	exit;
}

//// Verify request signature
//
$reqArr = array();
$reqArr['id'] = $client;
$reqArr['nonce'] = $nonce;
$reqArr['operation'] = 'add_key';
$reqHash = sign($reqArr, $ci['secret']);
if ($reqHash != $h) {
  	reply(S_BAD_SIGNATURE, $ci['secret'], $client, $nonce, $h);
	exit;
}

$tokenId = base64_encode(genRandRaw(6));
$secret = base64_encode(genRandRaw(16));
$keyid = addNewKey($tokenId, 1, $secret, '', $client);

if ($keyid > 0) {
	debug('Key '.$keyid.' added');
	reply(S_OK, $ci['secret'], $client, $nonce);
} else {
	reply(S_BACKEND_ERROR, $ci['secret'], $client, $nonce);
	exit;
}

function reply($status, $apiKey, $client_id, $nonce, $info='') {
	global $tokenId, $secret;
	
	if ($status == null) {
		$status = S_BACKEND_ERROR;
	}

	$a = array();

	echo 'nonce='.($a['nonce'] = $nonce).PHP_EOL;
	echo 'status='.($a['status'] = $status).PHP_EOL;
	if ($info != '') {
		echo 'info='.($a['info'] = $info).PHP_EOL;
	}
	if ($tokenId != '') {
		echo 'token_id='.($a['token_id'] = $tokenId).PHP_EOL;
		echo 'user_id='.($a['user_id'] = $tokenId).PHP_EOL;//TODO
	}
	if ($secret != '') {
		echo 'shared_secret='.($a['shared_secret'] = $secret).PHP_EOL;
	}
	echo 't='.($a['t']=getUTCTimeStamp()).PHP_EOL;
	$h = sign($a, $apiKey);
	echo 'h='.$h.PHP_EOL;
	echo PHP_EOL;

} // End reply

?>
