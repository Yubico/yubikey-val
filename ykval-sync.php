<?php
require_once 'ykval-common.php';
require_once 'ykval-config.php';

$apiKey = '';

header("content-type: text/plain");

debug("Request: " . $_SERVER['QUERY_STRING']);

$conn = mysql_connect($baseParams['__YKVAL_DB_HOST__'],
		      $baseParams['__YKVAL_DB_USER__'],
		      $baseParams['__YKVAL_DB_PW__']);
if (!$conn) {
  sendResp(S_BACKEND_ERROR, $apiKey);
  exit;
}
if (!mysql_select_db($baseParams['__YKVAL_DB_NAME__'], $conn)) {
  sendResp(S_BACKEND_ERROR, $apiKey);
  exit;
}

#
# Define requirements on protocoll
#

$syncParams=array("modified"=>Null,
		       "otp"=>Null, 
		       "yk_identity"=>Null,
		       "yk_counter"=>Null,
		       "yk_use"=>Null,
		       "yk_high"=>Null,
		       "yk_low"=>Null);

#
# Extract values from HTTP request
#

$tmp_log = "ykval-sync received ";
foreach ($syncParams as $param=>$value) {
  $value = getHttpVal($param, Null);
  if ($value==Null) {
    debug("ykval-sync recevied request with parameter[s] missing");
    sendResp(S_MISSING_PARAMETER, '');
    exit;
  }
  $syncParams[$param]=$value;
  $local_log .= "$param=$value ";
}
debug($tmp_log);

#
# Get local counter data
#

$devId = $syncParams['yk_identity'];
$ad = getAuthData($conn, $devId);
if (!is_array($ad)) {
  debug('Discovered Yubikey ' . $devId);
  addNewKey($conn, $devId);
  $ad = getAuthData($conn, $devId);
  if (!is_array($ad)) {
    debug('Invalid Yubikey ' . $devId);
    sendResp(S_BACKEND_ERROR, $apiKey);
    exit;
  }
 }
debug("Auth data:", $ad);
if ($ad['active'] != 1) {
  debug('De-activated Yubikey ' . $devId);
  sendResp(S_BAD_OTP, $apiKey);
  exit;
 }

# Note: AD comes directly from the DB response. Since we want to separate
# DB-dependencies longterm, we parse out the values we want from the response
# in order to keep naming consistent in the remaining code. This could be 
# considered inefficent in terms of computing power.
$localParams=array('modified'=>DbTimeToUnix($ad['accessed']),
		   'yk_counter'=>$ad['counter'],
		   'yk_use'=>$ad['sessionUse'],
		   'yk_low'=>$ad['low'],
		   'yk_high'=>$ad['high']);

#
# Compare sync and local counters and generate warnings according to 
#  
# http://code.google.com/p/yubikey-val-server-php/wiki/ServerReplicationProtocol
#

if ($syncParams['yk_counter'] > $localParams['yk_counter'] || 
    ($syncParams['yk_counter'] == $localParams['yk_counter'] &&
     $syncParams['yk_use'] > $localParams['yk_use'])) {
# sync counters are higher than local counters. We should update database
  
#TODO: Take care of accessed field. What format should be used. seconds since epoch?
  $stmt = 'UPDATE yubikeys SET ' .
    'accessed=\'' . UnixToDbTime($syncParams['modified']) . '\'' .
    ', counter=' . $syncParams['yk_counter'] .
    ', sessionUse=' . $syncParams['yk_use'] .
    ', low=' . $syncParams['yk_low'] .
    ', high=' . $syncParams['yk_high'] .
    ' WHERE id=' . $ad['id'];
  query($conn, $stmt);
  
 } else {
  if ($syncParams['yk_counter']==$localParams['yk_counter'] &&
      $syncParams['yk_use']==$localParams['yk_use']) {
# sync counters are equal to local counters. 
    if ($syncParams['modified']==$localParams['modified']) {
# sync modified is equal to local modified. Sync request is unnessecarily sent, we log a "light" warning
      error_log("ykval-sync:notice:Sync request unnessecarily sent");
    } else {
# sync modified is not equal to local modified. We have an OTP replay attempt somewhere in the system
      error_log("ykval-sync:warning:Replayed OTP attempt. " .
		" identity=" . $syncParams['yk_identity'] .
		" otp=" . $syncParams['otp'] .
		" syncCounter=" . $syncParams['yk_counter'] .
		" syncUse=" . $syncParams['yk_use'] .
		" syncModified=" . $syncParams['modified'] .
		" localModified=" . $localParams['modified']);
    }
  } else {
# sync counters are lower than local counters
    error_log("ykval-sync:warning:Remote server is out of sync." .
	      " identity=" . $syncParams['yk_identity'] .
	      " syncCounter=" . $syncParams['yk_counter'] .
	      " syncUse=" . $syncParams['yk_use'].
	      " localCounter=" . $localParams['yk_counter'] .
	      " localUse=" . $localParams['yk_use']);
  }
 }

  
  $extra=array('modified'=>$localParams['modified'],
	       'yk_identity'=>$syncParams['yk_identity'], #NOTE: Identity is never picked out from local db
	       'yk_counter'=>$localParams['yk_counter'],
	       'yk_use'=>$localParams['yk_use'],
	       'yk_high'=>$localParams['yk_high'],
	       'yk_low'=>$localParams['yk_low']);

  sendResp(S_OK, '', $extra);

?>
