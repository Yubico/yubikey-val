<?php require_once '../yms/appinclude.php';
	  require_once '../yms/yubi_lib.php';

$id = getHttpVal('id', 0);
if ($id <= 0) {
	echo 'Client ID is missing';
	exit;
}

$otp = getHttpVal('otp', '');
if ($otp == '') {
	echo 'OTP is missing';
	exit;
}

//// Get Yubikey from DB
//
$devId = substr($otp, 0, 12);
$ad = getAuthData($devId);
echo '<h3>--- From DB ---</h3>';
print_r($ad); echo '<p>';
if ($ad == null) {
	echo 'Invalid Yubikey '.$devId;
	exit;
}

//// Decode OTP from input
//
echo '<h3>--- From Input ---</h3>';
//$key16 = b64ToHex($ad['secret']);
//$key16 = 'e42b35465e48b6bdbe6676f23bf28259';
$k = b64ToModhex($ad['secret']);
echo '<li>K='.$k.'<p>';
$key16 = ModHex::Decode($k);
echo '<li>Key16 = ['.$key16.'] len='.strlen($key16).'<p>';
//$key= pack('H*', $key16);
$decoded_token = Yubikey::Decode($otp, $key16);
print_r($decoded_token);
echo '<p>';
if ( ! is_array($decoded_token) ) {
	die ('DECODING FAILED: '.$decoded_token."\n");
}

//// Sanity check key status
//
if ($ad['active'] < 1) {
	die ('The Yubikey is not activated!');
}

// Sanity check client status
//
if ($ad['c_active'] < 1) {
	die ('The Client is not activated!');
}

// Sanity check token ID
//
if ( strlen($decoded_token["public_id"]) == 12 ) {
	print "\t-> Public ID OK (".$decoded_token["public_id"].")\n";
} else { print "TOKEN ID FAILED, ".$decoded_token["public_id"]."\n"; }

// Sanity check the OTP
//
if ( strlen($decoded_token["token"]) == 32) {
	print "\t-> OTP len OK (".$decoded_token["token"].")\n";
} else { print " OTP len FAILED,".strlen($decoded_token["token"])."\n"; }

// Check the session counter
//
$sessionCounter = $decoded_token["counter"]; // From the req
$seenSessionCounter = $ad['counter']; // From DB
$scDiff = $seenSessionCounter - $sessionCounter;
if ($scDiff > 0) {
	print "Replayed session counter, counter=".$sessionCounter.', seen='.$seenSessionCounter."\n";
} else {
	print "\t-> Counter OK (".$sessionCounter.")\n";
}

$hi = $decoded_token["high"] & 0xff; // From the req
$seenHi = $ad['high']; // From DB
$hiDiff = $seenHi - $hi;
if ($scDiff == 0 && $hiDiff > 0) {
	print "Replayed high counter, counter=".$hi.', seen='.$seenHi."\n";
} else {
	print "\t-> High counter OK (".$hi.")\n";
}

$lo = $decoded_token["low"] & 0xff; // From the req
$seenLo = $ad['low']; // From DB
$loDiff = $seenLo - $lo;
if ($scDiff == 0 && $loDiff > 0) {
	print "Replayed low counter, counter=".$lo.', seen='.$seenLo."\n";
} else {
	print "\t-> Low counter OK (".$lo.")\n";
}

echo '<p>Validation OK for Yubikey: '.$decoded_token["public_id"];

?>
