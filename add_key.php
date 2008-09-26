<?php require_once '../yubiphpbase/appinclude.php';
	  require_once '../yubiphpbase/yubi_lib.php';
	  require_once 'common.php';
	  
header("content-type: text/plain");

$trace = 1;

$client = getHttpVal('id', 0);
if ($client <= 0) {
	debug('Client ID is missing, default to 1 (root)');
	$client = 1;
}

$nonce = getHttpVal('nonce', '');
if ($nonce == '') {
	reply(S_MISSING_PARAMETER, '', $client, '', 'nonce');
	exit;
}

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

$ci = getClientInfo($client);

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

$tokenId = genRandB64(6);
$secret = genRandB64(16);
$keyid = addNewKey($tokenId, 1, $secret, '', $client);

if ($keyid > 0) {
	debug('Key '.$keyid.' added');
	reply(S_OK, $ci['secret'], $client, $nonce);
} else {
	reply(S_BACKEND_ERROR, $ci['secret'], $client, $nonce);
	exit;
}

function reply($status, $apiKey, $client_id, $nonce, $info=null) {
	global $tokenId;
	
	if ($status == null) {
		$status = S_BACKEND_ERROR;
	}

	date_default_timezone_set('UTC');
	$timestamp = date('Y-m-d\TH:i:s\ZZ', time());

	//// Prepare the response to the user
	//
	$respParams = 'status='.$status.'&t='.$timestamp;

	// Generate the signature
	debug('API key: '.$apiKey); // API key of the client
	debug('Signing: '.$respParams);
	// the TRUE at the end states we want the raw value, not hexadecimal form
	$hmac = hash_hmac('sha1', utf8_encode($respParams), $apiKey, true);
	//outputToFile('hmac', $hmac, "b");
	// now take that byte value and base64 encode it
	$hmac = base64_encode($hmac);

	echo 'h='.$hmac.PHP_EOL;
	if ($info != null) {
		echo 'info='.$info.PHP_EOL;
	}
	echo 'nonce='.$nonce.PHP_EOL;
	echo 'status='.$status.PHP_EOL;
	echo 't='.$timestamp.PHP_EOL;
	echo PHP_EOL;
	
} // End reply

?>
