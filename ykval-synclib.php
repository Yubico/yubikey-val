<?php

require_once 'ykval-config.php';
require_once 'ykval-common.php';
require_once 'lib/Db.php';

class SyncLib
{
  public $syncServers = null;
  public $dbConn = null;

  function __construct($logname='ykval-synclib')
  {
    $this->logname=$logname;
    global $baseParams;
    $this->syncServers = explode(";", $baseParams['__YKVAL_SYNC_POOL__']);
    $this->db=new Db($baseParams['__YKVAL_DB_HOST__'],
		     $baseParams['__YKVAL_DB_USER__'],
		     $baseParams['__YKVAL_DB_PW__'],
		     $baseParams['__YKVAL_DB_NAME__']);
    $this->isConnected=$this->db->connect();
    $this->random_key=rand(0,1<<16);
    $this->max_url_chunk=$baseParams['__YKVAL_SYNC_MAX_SIMUL__'];
    $this->resync_timeout=$baseParams['__YKVAL_SYNC_RESYNC_TIMEOUT__'];

  }
  

  function isConnected() 
  {
    return $this->isConnected;
  }
  function DbTimeToUnix($db_time)
  {
    $unix=strptime($db_time, '%F %H:%M:%S');
    return mktime($unix[tm_hour], $unix[tm_min], $unix[tm_sec], $unix[tm_mon]+1, $unix[tm_mday], $unix[tm_year]+1900);
  }
  
  function UnixToDbTime($unix)
  {
    return date('Y-m-d H:i:s', $unix);
  }  
  function getServer($index)
  {
    if (isset($this->syncServers[$index])) return $this->syncServers[$index];
    else return "";
  }

  function getClientData($client)
  {
    $res=$this->db->customQuery('SELECT id, secret FROM clients WHERE active AND id='.mysql_quote($client));
    if(mysql_num_rows($res)>0) {
      $row = mysql_fetch_assoc($res);
      mysql_free_result($res);
      return $row;
    } else return false;
  }
  function getLast() 
  {
    $res=$this->db->last('queue', 1);
    $info=$this->otpParamsFromInfoString($res['info']);
    return array('modified'=>$this->DbTimeToUnix($res['modified_time']), 
		 'otp'=>$res['otp'], 
		 'server'=>$res['server'],
		 'nonce'=>$info['nonce'],
		 'yk_identity'=>$info['yk_identity'], 
		 'yk_counter'=>$info['yk_counter'], 
		 'yk_use'=>$info['yk_use'], 
		 'yk_high'=>$info['yk_high'], 
		 'yk_low'=>$info['yk_low']);
  }
  public function getQueueLength()
  {
    return count($this->db->last('queue', NULL));
  }

  public function createInfoString($otpParams, $localParams)
  {
    return 'yk_identity=' . $otpParams['yk_identity'] .
      '&yk_counter=' . $otpParams['yk_counter'] .
      '&yk_use=' . $otpParams['yk_use'] .
      '&yk_high=' . $otpParams['yk_high'] .
      '&yk_low=' . $otpParams['yk_low'] .
      '&nonce=' . $otpParams['nonce'] .
      ',local_counter=' . $localParams['yk_counter'] .
      '&local_use=' . $localParams['yk_use'];
  }
  public function otpParamsFromInfoString($info) {
    $out=explode(",", $info);
    parse_str($out[0], $params);
    return $params;
  }
  public function otpPartFromInfoString($info) {
    $out=explode(",", $info);
    return $out[0];
  }
  public function localParamsFromInfoString($info) 
  {
    $out=explode(",", $info);
    parse_str($out[1], $params);
    return array('yk_counter'=>$params['local_counter'], 
		 'yk_use'=>$params['local_use']);
  }
  public function queue($otpParams, $localParams)
  {

    $info=$this->createInfoString($otpParams, $localParams);
    $this->otpParams = $otpParams;
    $this->localParams = $localParams;
    
    
    $res=True;
    foreach ($this->syncServers as $server) {
      
      if(! $this->db->save('queue', array('modified_time'=>$this->UnixToDbTime($otpParams['modified']), 
					  'otp'=>$otpParams['otp'], 
					  'server'=>$server,
					  'random_key'=>$this->random_key,
					  'info'=>$info))) $res=False;
    }
    return $res;
  }
  public function getNumberOfServers()
  {
    if (is_array($this->syncServers)) return count($this->syncServers);
    else return 0;
  }

  public function log($level, $msg, $params=NULL)
  {
    $logMsg=$this->logname . ':' . $level . ':' . $msg;
    if ($params) $logMsg .= ' modified=' . $params['modified'] .
		   ' nonce=' . $params['nonce'] .
		   ' yk_identity=' . $params['yk_identity'] .
		   ' yk_counter=' . $params['yk_counter'] .   
		   ' yk_use=' . $params['yk_use'] .   
		   ' yk_high=' . $params['yk_high'] .   
		   ' yk_low=' . $params['yk_low'];
    error_log($logMsg);
  }
  function updateLocalParams($id,$params)
  {
    return $this->db->update('yubikeys', 
			     $id, 
			     array('accessed'=>UnixToDbTime($params['modified']), 
				   'nonce'=>$params['nonce'], 
				   'counter'=>$params['yk_counter'], 
				   'sessionUse'=>$params['yk_use'], 
				   'high'=>$params['yk_high'], 
				   'low'=>$params['yk_low']));
  }
  function getLocalParams($yk_identity)
  {
    $this->log("notice", "searching for " . $yk_identity . " (" . modhex2b64($yk_identity) . ") in local db");
    $res = $this->db->findBy('yubikeys', 'publicName', modhex2b64($yk_identity),1);

    if (!$res) {
      $this->log('notice', 'Discovered new identity ' . $yk_identity);
      $this->db->save('yubikeys', array('publicName'=>modhex2b64($yk_identity), 
				  'active'=>1, 
				  'counter'=>0, 
				  'sessionUse'=>0, 
				  'nonce'=>0));
      $res=$this->db->findBy('yubikeys', 'publicName', modhex2b64($yk_identity), 1);
    }
    if ($res) {
      $localParams=array('id'=>$res['id'],
			 'modified'=>$this->DbTimeToUnix($res['accessed']),
			 'otp'=>$res['otp'],
			 'nonce'=>$res['nonce'],
			 'active'=>$res['active'],
			 'yk_identity'=>$yk_identity,
			 'yk_counter'=>$res['counter'], 
			 'yk_use'=>$res['sessionUse'],
			 'yk_high'=>$res['high'],
			 'yk_low'=>$res['low']);
      
      $this->log("notice", "counter found in db ", $localParams);
      return $localParams;
    } else {
      $this->log('notice', 'params for identity ' . $yk_identity . ' not found in database');
      return false;
    }
  }

  private function parseParamsFromMultiLineString($str)
  {
      preg_match("/^modified=([0-9]*)/m", $str, $out);
      $resParams['modified']=$out[1];
      preg_match("/^yk_identity=([[:alpha:]]*)/m", $str, $out);
      $resParams['yk_identity']=$out[1];
      preg_match("/^yk_counter=([0-9]*)/m", $str, $out);
      $resParams['yk_counter']=$out[1];
      preg_match("/^yk_use=([0-9]*)/m", $str, $out);
      $resParams['yk_use']=$out[1];
      preg_match("/^yk_high=([0-9]*)/m", $str, $out);
      $resParams['yk_high']=$out[1];
      preg_match("/^yk_low=([0-9]*)/m", $str, $out);
      $resParams['yk_low']=$out[1];
      preg_match("/^nonce=([[:alpha:]]*)/m", $str, $out);
      $resParams['nonce']=$out[1];

      return $resParams;
  }

  public function updateDbCounters($params)
  {
    $res=$this->db->lastBy('yubikeys', 'publicName', modhex2b64($params['yk_identity']));
    if (isset($res['id'])) {
      $condition='('.$params['yk_counter'].'>counter or ('.$params['yk_counter'].'=counter and ' .
	$params['yk_use'] . '>sessionUse))' ;
      if(! $this->db->conditional_update('yubikeys', 
					 $res['id'], 
					 array('accessed'=>$this->UnixToDbTime($params['modified']), 
					       'counter'=>$params['yk_counter'], 
					       'sessionUse'=>$params['yk_use'],
					       'low'=>$params['yk_low'],
					       'high'=>$params['yk_high'], 
					       'nonce'=>$params['nonce']), 
					 $condition))
	{
	  error_log("ykval-synclib:critical: failed to update internal DB with new counters");
	  return false;
	} else {
	if (mysql_affected_rows()>0) $this->log("notice", "updated database ", $params);
	else $this->log('notice', 'database not updated', $params);
	return true;
      }
    } else return false;
  }
  
  public function countersHigherThan($p1, $p2)
  {
    if ($p1['yk_counter'] > $p2['yk_counter'] ||
	($p1['yk_counter'] == $p2['yk_counter'] &&
	 $p1['yk_use'] > $p2['yk_use'])) return true;
    else return false;
  }
  
  public function countersHigherThanOrEqual($p1, $p2)
  {
    if ($p1['yk_counter'] > $p2['yk_counter'] ||
	($p1['yk_counter'] == $p2['yk_counter'] &&
	 $p1['yk_use'] >= $p2['yk_use'])) return true;
    else return false;
  }
  public function countersEqual($p1, $p2) {
    return ($p1['yk_counter']==$p2['yk_counter']) && ($p1['yk_use']==$p2['yk_use']);
  }

  public function deleteQueueEntry($answer) 
  {

    preg_match('/url=(.*)\?/', $answer, $out);
    $server=$out[1];
    debug("server=" . $server);
    $this->db->deleteByMultiple('queue', array("modified_time"=>$this->UnixToDbTime($this->otpParams['modified']), "random_key"=>$this->random_key, 'server'=>$server));
  }

  public function reSync($older_than=10)
  {
    $urls=array();
    # TODO: move statement to DB class, this looks grotesque
    $res=$this->db->customQuery("select * from queue WHERE queued_time < DATE_SUB(now(), INTERVAL " . $older_than . " MINUTE)");
    $this->log('notice', "found " . mysql_num_rows($res) . " old queue entries");
    $collection=array();
    while($row = mysql_fetch_array($res, MYSQL_ASSOC)) {
      $collection[]=$row;
    }
    foreach ($collection as $row) {
      $this->log('notice', "server=" . $row['server'] . " , info=" . $row['info']);

      $urls[]=$row['server'] .  
	"?otp=" . $row['otp'] .
	"&modified=" . $this->DbTimeToUnix($row['modified_time']) .
	"&" . $this->otpPartFromInfoString($row['info']);
      
    }

    /* We do not want to sent out to many requests at once since this
     tends to be very slow since most system have limits on number
     of outgoing connections */
    $url_chunks=array_chunk($urls, $this->max_url_chunk);
    foreach($url_chunks as $urls) {
      
      $ans_arr=$this->retrieveURLasync($urls, count($urls), $this->resync_timeout);
      
      if (!is_array($ans_arr)) {
	$this->log('notice', 'No responses from validation server pool'); 
	$ans_arr=array();
      }
      
      foreach ($ans_arr as $answer){
	/* Parse out parameters from each response */
	$resParams=$this->parseParamsFromMultiLineString($answer);
	$this->log("notice", "response contains ", $resParams);
	
	/* Update database counters */
	$this->updateDbCounters($resParams);
	
	/* Warnings and deletion */
	preg_match("/url=(.*)\?.*otp=([[:alpha:]]*)/", $answer, $out);
	$server=$out[1];
	$otp=$out[2];
	
	$this->log('notice', 'Searching for entry with' .
		   ' server=' . $server .
		   ' otp=' . $otp);
	
	$entries=$this->db->findByMultiple('queue', 
					   array('server'=>$server, 
						 'otp'=>$otp));
	$this->log('notice', 'found ' . count($entries) . ' entries');
	if (count($entries)>1) $this->log('warning', 'Multiple queue entries with the same OTP. We could have an OTP replay attempt in the system');
	
	foreach($entries as $entry) {
	  /* Warnings */
	  
	  $localParams=$this->localParamsFromInfoString($entry['info']);
	  $otpParams=$this->otpParamsFromInfoString($entry['info']);
	  
	  /* Check for warnings 
	   
	   If received sync response have lower counters than locally saved 
	   last counters (indicating that remote server wasn't synced) 
	  */
	  if ($this->countersHigherThan($localParams, $resParams)) {
	    $this->log("warning", "queued:Remote server out of sync, local counters ", $localParams);
	    $this->log("warning", "queued:Remote server out of sync, remote counters ", $resParams);
	  }
	  
	  /* If received sync response have higher counters than locally saved 
	   last counters (indicating that local server wasn't synced) 
	  */
	  if ($this->countersHigherThan($resParams, $localParams)) {
	    $this->log("warning", "queued:Local server out of sync, local counters ", $localParams);
	    $this->log("warning", "queued:Local server out of sync, remote counters ", $resParams);
	  }

	  if ($this->countersHigherThan($resParams, $otpParams) || 
	      ($this->countersEqual($resParams, $otpParams) &&
	       $resParams['nonce']!=$otpParams['nonce'])) {
	    
	    /* If received sync response have higher counters than OTP or same counters with different nonce
	     (indicating REPLAYED_OTP) 
	    */
	
	    $this->log("warning", "queued:replayed OTP, remote counters " , $resParams);
	    $this->log("warning", "queued:replayed OTP, otp counters", $otpParams);
	  }
	  
	  /* Deletion */
	  $this->log('notice', 'deleting queue entry with id=' . $entry['id']);
	  $this->db->deleteByMultiple('queue', array('id'=>$entry['id']));
	}
      }
    }
  }
  public function sync($ans_req, $timeout=1) 
  {
    /*
     Construct URLs
    */
    
    $urls=array();
    $res=$this->db->findByMultiple('queue', array("modified_time"=>$this->UnixToDbTime($this->otpParams['modified']), "random_key"=>$this->random_key));
    foreach ($res as $row) {
      $urls[]=$row['server'] .  
	"?otp=" . $row['otp'] .
	"&modified=" . $this->DbTimeToUnix($row['modified_time']) .
	"&" . $this->otpPartFromInfoString($row['info']);
    }

    /*
     Send out requests
    */
    $ans_arr=$this->retrieveURLasync($urls, $ans_req, $timeout);

    if (!is_array($ans_arr)) {
      $this->log('warning', 'No responses from validation server pool'); 
      $ans_arr=array();
    }

    /*
     Parse responses
    */
    $lastLocalParams=$this->getLocalParams($this->otpParams['yk_identity']);
    $localParams = $this->localParams;

    $this->answers = count($ans_arr);
    $this->valid_answers = 0;
    foreach ($ans_arr as $answer){
      /* Parse out parameters from each response */
      $resParams=$this->parseParamsFromMultiLineString($answer);
      $this->log("notice", "local db contains ", $localParams);
      $this->log("notice", "response contains ", $resParams);
      
      /* Update internal DB (conditional) */
      
      $this->updateDbCounters($resParams);
      
      
      /* Check for warnings 
       
       If received sync response have lower counters than local db
       (indicating that remote server wasn't synced) 
      */
      if ($this->countersHigherThan($localParams, $resParams)) {
	$this->log("warning", "Remote server out of sync, local counters ", $localParams);
	$this->log("warning", "Remote server out of sync, remote counters ", $resParams);
      }
      
      /* If received sync response have higher counters than local db
       (indicating that local server wasn't synced) 
      */
      if ($this->countersHigherThan($resParams, $localParams)) {
	$this->log("warning", "Local server out of sync, local counters ", $localParams);
	$this->log("warning", "Local server out of sync, remote counters ", $resParams);
      }
      
      if ($this->countersHigherThan($resParams, $this->otpParams) || 
	  ($this->countersEqual($resParams, $this->otpParams) &&
	   $resParams['nonce']!=$this->otpParams['nonce'])) {

	/* If received sync response have higher counters than OTP or same counters with different nonce
	 (indicating REPLAYED_OTP) 
	*/
	
	$this->log("warning", "replayed OTP, remote counters " , $resParams);
	$this->log("warning", "replayed OTP, otp counters", $this->otpParams);
      } else {

	/* The answer is ok since a REPLAY was not indicated */
	
	$this->valid_answers++;
      }

      

      
      /*  Delete entry from table */
      $this->deleteQueueEntry($answer);

      
    }
   
    /* Return true if valid answers equals required answers. 
     Since we only obtain the required amount of answers from 
     retrieveAsync this indicates that all answers were actually valid. 
     Otherwise, return false. */
    if ($this->valid_answers==$ans_req) return True;
    else return False;
  }
  public function getNumberOfValidAnswers()
  {
    if (isset($this->valid_answers)) return $this->valid_answers;
    else return 0;
  }
  public function getNumberOfAnswers()
  {
    if (isset($this->answers)) return $this->answers;
    else return 0;
  }


  /*
   This function takes a list of URLs.  It will return the content of
   the first successfully retrieved URL, whose content matches ^OK.
   The request are sent asynchronously.  Some of the URLs can fail
   with unknown host, connection errors, or network timeout, but as
   long as one of the URLs given work, data will be returned.  If all
   URLs fail, data from some URL that did not match parameter $match 
   (defaults to ^OK) is returned, or if all URLs failed, false.
  */
  function retrieveURLasync ($urls, $ans_req=1, $timeout=1.0) {
    $mh = curl_multi_init();

    $ch = array();
    foreach ($urls as $id => $url) {
      $handle = curl_init();
      
      curl_setopt($handle, CURLOPT_URL, $url);
      curl_setopt($handle, CURLOPT_USERAGENT, "YK-VAL");
      curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($handle, CURLOPT_FAILONERROR, true);
      curl_setopt($handle, CURLOPT_TIMEOUT, $timeout);
      
      curl_multi_add_handle($mh, $handle);
      
      $ch[$handle] = $handle;
    }
    
    $str = false;
    $ans_count = 0;
    $ans_arr = array();
    
    do {
      while (($mrc = curl_multi_exec($mh, $active)) == CURLM_CALL_MULTI_PERFORM)
	;
      
      while ($info = curl_multi_info_read($mh)) {
	debug ("YK-KSM multi", $info);
	if ($info['result'] == CURL_OK) {
	  $str = curl_multi_getcontent($info['handle']);
	  debug($str);
	  if (preg_match("/status=OK/", $str)) {
	    $error = curl_error ($info['handle']);
	    $errno = curl_errno ($info['handle']);
	    $cinfo = curl_getinfo ($info['handle']);
	    debug("YK-KSM errno/error: " . $errno . "/" . $error, $cinfo);
	    $ans_count++;
	    debug("found entry");
	    $ans_arr[]="url=" . $cinfo['url'] . "\n" . $str;
	  }
	  
	  if ($ans_count >= $ans_req) {
	    foreach ($ch as $h) {
	      curl_multi_remove_handle ($mh, $h);
	      curl_close ($h);
	    }
	    curl_multi_close ($mh);
	    
	    return $ans_arr;
	  }
	  
	  curl_multi_remove_handle ($mh, $info['handle']);
	  curl_close ($info['handle']);
	  unset ($ch[$info['handle']]);
	}
	
	curl_multi_select ($mh);
      }
    } while($active);

    
    foreach ($ch as $h) {
      curl_multi_remove_handle ($mh, $h);
      curl_close ($h);
    }
    curl_multi_close ($mh);

    if ($ans_count>0) return $ans_arr;
    else return $str;
  }
  
}

?>