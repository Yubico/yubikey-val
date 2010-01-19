<?php


/**
 * Class for creating new OTPs for testing purposes
 *
 * LICENSE:
 *
 * Copyright (c) 2009  Yubico.  All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * o Redistributions of source code must retain the above copyright
 *   notice, this list of conditions and the following disclaimer.
 * o Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.
 * o The names of the authors may not be used to endorse or promote
 *   products derived from this software without specific prior written
 *   permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author      Olov Danielson <olov@yubico.com>
 * @copyright   2010 Yubico
 * @license     http://opensource.org/licenses/bsd-license.php New BSD License
 * @link        http://www.yubico.com/
 * @link        http://code.google.com/p/yubikey-val-server-php/
 */

require_once('ykval-db.php');

class OtpGen
{


  public function __construct($db_dsn, $db_username, $db_password, $db_options, $name='ykval-otpgen', $yk_publicname)
  {

    $this->myLog = new Log($name);
    $this->db=new Db($db_dsn, $db_username, $db_password, $db_options, $name . ':db');
    $this->isConnected=$this->db->connect();

    // First obtain private ID and AES-key
    if($yubikey=$this->db->findBy('yubikeys', 'yk_publicname', $yk_publicname, 1)) {
      $this->yk_internalname=$yubikey['yk_internalname'];
      $this->yk_aeskey=$yubikey['yk_aeskey'];
    } else {
      $this->myLog->log(LOG_WARNING, 'Failed to obtain data for yubikey ' . $yk_publicname);
    }
    

    $this->yk_publicname = $yk_publicname;
    $this->yk_counter = $this->stepYkCounter();
    $this->yk_use = 0;
    $this->yk_low = rand(0,65535);
    $this->yk_high = rand(0,255);
    // Store start time as well so we can step yk_low, yk_high correctly
    $this->start_time=time();
    
  }


  public function getOtp()
  {
    # TODO. Add the rest of the values to string and execute. !
    $execstring=sprintf("ykgenerate %s %s %04x %04x %02x %02x" , 
			$this->yk_aeskey,
			$this->yk_internalname,
			$this->yk_counter,
			$this->yk_low,
			$this->yk_high,
			$this->yk_use++);
    if ($this->yk_use>=256) {
      $this->yk_use=0;
      $this->yk_counter=$this->stepYkCounter();
    }
    echo $execstring . "\n";
    $otp=system($execstring);
    return $this->yk_publicname . $otp;
  }

  
  private function stepYkCounter()
  {
    if ($this->yk_publicname) {
      if($yubikey=$this->db->findBy('yubikeys', 'yk_publicname', $this->yk_publicname, 1)) {
	$new_counter = $yubikey['yk_counter'] + 1;
	if ($this->db->updateBy('yubikeys', 
				'yk_publicname', 
				$this->yk_publicname,
				array('yk_counter'=>$new_counter))) {
	  $this->myLog->log(LOG_NOTICE, "Yubikey " . $this->yk_publicname . " stepped counter value to " . $new_counter);
	  return $new_counter;
	} else {
	  $this->myLog->log(LOG_WARNING, "Failed to update counter value for yubikey " . $this->yk_publicname);
	} 
      } else {
	$this->myLog->log(LOG_WARNING, "Failed to get data for yubikey " . $this->yk_publicname);
      }
    } else {
      $this->myLog->log(LOG_WARNING, "yk_publicname not set up correctly for class ykval-otpgen.php. We shouldn't be here.");
    }
    return false;
  }
}
  
 
