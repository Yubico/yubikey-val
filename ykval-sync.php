<?php
require_once 'ykval-common.php';
require_once 'ykval-config.php';
require_once 'ykval-synclib.php';

$apiKey = '';

header("content-type: text/plain");

debug("Request: " . $_SERVER['QUERY_STRING']);

$sync = new SyncLib('ykval-sync');
if (! $sync->isConnected()) {
  sendResp(S_BACKEND_ERROR, $apiKey);
  exit;
 }

#
# Define requirements on protocoll
#

$syncParams=array('modified'=>Null,
		  'otp'=>Null,
		  'nonce'=>Null,
		  'yk_publicname'=>Null,
		  'yk_counter'=>Null,
		  'yk_use'=>Null,
		  'yk_high'=>Null,
		  'yk_low'=>Null);

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

$yk_publicname = $syncParams['yk_publicname'];
$localParams = $sync->getLocalParams($yk_publicname);
if (!$localParams) {
  debug('Invalid Yubikey ' . $yk_publicname);
  sendResp(S_BACKEND_ERROR, $apiKey);
  exit;
 }

if ($localParams['active'] != 1) {
  debug('De-activated Yubikey ' . $yk_publicname);
  sendResp(S_BAD_OTP, $apiKey);
  exit;
 }


#
# Compare sync and local counters and generate warnings according to 
#  
# http://code.google.com/p/yubikey-val-server-php/wiki/ServerReplicationProtocol
#

/* Conditional update local database */
$sync->updateDbCounters($syncParams); 

if ($sync->countersHigherThan($localParams, $syncParams)) {
  /* sync counters are lower than local counters */
  $sync->log('warning', 'Remote server out of sync. Local params ' , $localParams);
  $sync->log('warning', 'Remote server out of sync. Sync params ' , $syncParams);
 }

if ($sync->countersEqual($localParams, $syncParams)) {
  /* sync counters are equal to local counters.  */
  if ($syncParams['modified']==$localParams['modified']) {
    /* sync modified is equal to local modified. 
     Sync request is unnessecarily sent, we log a "light" warning */
    $sync->log('warning', 'Sync request unnessecarily sent');
  } else {
    /* sync modified is not equal to local modified. 
     We have an OTP replay attempt somewhere in the system */
    $sync->log('warning', 'Replayed OTP attempt. Modified differs. Local ',  $localParams);
    $sync->log('warning', 'Replayed OTP attempt. Modified differs. Sync ',  $syncParams);
  }
  if ($syncParams['nonce']!=$localParams['nonce']) {
    $sync->log('warning', 'Replayed OTP attempt. Nonce differs. Local ', $localParams);
    $sync->log('warning', 'Replayed OTP attempt. Nonce differs. Sync ', $syncParams);
  }
 }

  
$extra=array('modified'=>$localParams['modified'],
	     'nonce'=>$localParams['nonce'],
	     'yk_publicname'=>$yk_publicname,
	     'yk_counter'=>$localParams['yk_counter'],
	     'yk_use'=>$localParams['yk_use'],
	     'yk_high'=>$localParams['yk_high'],
	     'yk_low'=>$localParams['yk_low']);

sendResp(S_OK, '', $extra);

?>
