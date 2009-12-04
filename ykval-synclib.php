<?php

require_once 'ykval-config.php';
require_once 'ykval-common.php';
require_once 'lib/Db.php';

class SyncLib
{
  public $syncServers = null;
  public $dbConn = null;

  function __construct()
  {
    global $baseParams;
    $this->syncServers = explode(";", $baseParams['__YKVAL_SYNC_POOL__']);
    $this->db=new Db($baseParams['__YKVAL_DB_HOST__'],
		     $baseParams['__YKVAL_DB_USER__'],
		     $baseParams['__YKVAL_DB_PW__'],
		     $baseParams['__YKVAL_DB_NAME__']);
    $this->db->connect();
    $this->random_key=rand(0,1<<16);
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
  function getLast() 
  {
    $res=$this->db->last('queue', 1);
    parse_str($res['info'], $info);
    return array('modified'=>$this->DbTimeToUnix($res['modified_time']), 
		 'otp'=>$res['otp'], 
		 'server'=>$res['server'], 
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
  public function queue($otpParams, $localParams)
  {
    
    
    $info='yk_identity=' . $otpParams['yk_identity'] .
      '&yk_counter=' . $otpParams['yk_counter'] .
      '&yk_use=' . $otpParams['yk_use'] .
      '&yk_high=' . $otpParams['yk_high'] .
      '&yk_low=' . $otpParams['yk_low'];
    
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

  private function log($level, $msg, $params=NULL)
  {
    $logMsg="ykval-synclib:" . $level . ":" . $msg;
    if ($params) $logMsg .= " modified=" . $params['modified'] .
		   " yk_identity=" . $params['yk_identity'] .
		   " yk_counter=" . $params['yk_counter'] .   
		   " yk_use=" . $params['yk_use'] .   
		   " yk_high=" . $params['yk_high'] .   
		   " yk_low=" . $params['yk_low'];
    error_log($logMsg);
  }
  private function getLocalParams($yk_identity)
  {
    $this->log("notice", "searching for " . $yk_identity . " (" . modhex2b64($yk_identity) . ") in local db");
    $res = $this->db->lastBy('yubikeys', 'publicName', modhex2b64($yk_identity));
    $localParams=array('modified'=>$this->DbTimeToUnix($res['accessed']),
		       'otp'=>$res['otp'],
		       'yk_identity'=>$yk_identity,
		       'yk_counter'=>$res['counter'], 
		       'yk_use'=>$res['sessionUse'],
		       'yk_high'=>$res['high'],
		       'yk_low'=>$res['low']);

    $this->log("notice", "counter found in db ", $localParams);

    return $localParams;

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

      return $resParams;
  }

  public function updateDbCounters($params)
  {


    $res=$this->db->lastBy('yubikeys', 'publicName', modhex2b64($params['yk_identity']));
    if (isset($res['id'])) {
      if(! $this->db->update('yubikeys', 
			     $res['id'], 
			     array('accessed'=>$this->UnixToDbTime($params['modified']), 
				   'counter'=>$params['yk_counter'], 
				   'sessionUse'=>$params['yk_use'],
				   'low'=>$params['yk_low'],
				   'high'=>$params['yk_high'])))
	{
	  error_log("ykval-synclib:critical: failed to update internal DB with new counters");
	  return false;
	} else {
	$this->log("notice", "updated database ", $params);
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
  
  public function sync($ans_req) 
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
	"&" . $row['info'];
    }

    /*
     Send out requests
    */
    if (count($urls)>=$ans_req) $ans_arr=$this->retrieveURLasync($urls, $ans_req);
    else return false;

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
      
      /* Check if internal DB should be updated */
      if ($this->countersHigherThan($resParams, $lastLocalParams)) {
	$this->updateDbCounters($resParams);
      }
      
      /* Check for warnings 
       
       If received sync response have lower counters than locally saved 
       last counters (indicating that remote server wasn't synced) 
      */
      if ($this->countersHigherThan($localParams, $resParams)) {
	$this->log("warning", "Remote server out of sync, local counters ", $localParams);
	$this->log("warning", "Remote server out of sync, remote counters ", $resParams);
      }
      
      /* If received sync response have higher counters than locally saved 
       last counters (indicating that local server wasn't synced) 
      */
      if ($this->countersHigherThan($resParams, $localParams)) {
	$this->log("warning", "Local server out of sync, local counters ", $localParams);
	$this->log("warning", "Local server out of sync, remote counters ", $resParams);
      }
      
      /* If received sync response have higher counters than OTP counters
       (indicating REPLAYED_OTP) 
      */
      if ($this->countersHigherThanOrEqual($resParams, $this->otpParams)) {
	$this->log("warning", "replayed OTP, remote counters " , $resParams);
	$this->log("warning", "replayed OTP, otp counters", $this->otpParams);
      }

      
      /* Check if answer marks OTP as valid */
      if (!$this->countersHigherThanOrEqual($resParams, $this->otpParams)) $this->valid_answers++;
      
      /*  Delete entry from table */
      preg_match('/url=(.*)\?/', $answer, $out);
      $server=$out[1];
      debug("server=" . $server);
      $this->db->deleteByMultiple('queue', array("modified_time"=>$this->UnixToDbTime($this->otpParams['modified']), "random_key"=>$this->random_key, 'server'=>$server));
      
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
  function retrieveURLasync ($urls, $ans_req=1) {
    $mh = curl_multi_init();

    $ch = array();
    foreach ($urls as $id => $url) {
      $handle = curl_init();
      
      curl_setopt($handle, CURLOPT_URL, $url);
      curl_setopt($handle, CURLOPT_USERAGENT, "YK-VAL");
      curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($handle, CURLOPT_FAILONERROR, true);
      curl_setopt($handle, CURLOPT_TIMEOUT, 10);
      
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