<?php
define('S_OK', 'OK');
define('S_BAD_OTP', 'BAD_OTP');
define('S_BAD_CLIENT', 'BAD_CLIENT'); // New, added by paul 20080920
define('S_REPLAYED_OTP', 'REPLAYED_OTP');
define('S_BAD_SIGNATURE', 'BAD_SIGNATURE');
define('S_MISSING_PARAMETER', 'MISSING_PARAMETER');
//define('S_NO_SUCH_CLIENT', 'NO_SUCH_CLIENT'); // Deprecated by paul 20080920
define('S_OPERATION_NOT_ALLOWED', 'OPERATION_NOT_ALLOWED');
define('S_BACKEND_ERROR', 'BACKEND_ERROR');
define('S_SECURITY_ERROR', 'SECURITY_ERROR');
define('TS_SEC', 0.1118);
define('TS_TOLERANCE', 0.3);

function debug($msg, $exit = false) {
	global $trace;
	if ($trace) {
		if (is_array($msg)) {
			//print_r($msg);
		} else {
			echo '<p>Debug> ' . $msg;
		}
		echo "\n";
	}
	if ($exit) {
		die('<font color=red><h4>Exit</h4></font>');
	}
}

function genRandRaw($len) {
	$h = hash_hmac('sha1', rand(9999,9999999), 'dj*ccbcuiiurubrvnubcdluul', true);
	$a = str_split($h);
	//print_r($a);
	$a = array_slice($a, 0, $len);
	//print_r($a);
	$s = implode($a);
	//outputToFile('out', $s);
	return $s;
}

// Return eg. 2008-11-21T06:11:55
//            
function getUTCTimeStamp() {
	date_default_timezone_set('UTC');
	return date('Y-m-d\TH:i:s', time());
}

// Sign a http query string in the array of key-value pairs
// return b64 encoded hmac hash
function sign($a, $apiKey, $debug=false) {
	ksort($a);
	$qs = '';
	$n = count($a);
	$i = 0;
	foreach (array_keys($a) as $key) {
		$qs .= trim($key).'='.trim($a[$key]);
		if (++$i < $n) {
			$qs .= '&';
		}
	}
	
	// Generate the signature
	//debug('API key: '.$apiKey); // API key of the client
	debug('SIGN: '.$qs);
	
	// the TRUE at the end states we want the raw value, not hexadecimal form
	$hmac = hash_hmac('sha1', utf8_encode($qs), $apiKey, true);
	$hmac = base64_encode($hmac);
	if ($debug) {
		debug('h='.$hmac);
	}
	return $hmac;
		
} // sign an array of query string

function outputToFile($outFname, $content, $mode, $append = false) {
	$out = fopen($outFname, ($append ? "a" : "w"));
	fwrite($out, $content);
	fclose($out);
}
?>
