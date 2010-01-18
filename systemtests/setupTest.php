<?php

require_once 'PHPUnit/Framework.php';
require_once 'Auth/Yubico.php';
require_once(dirname(__FILE__) . '/../ykval-otpgen.php');
require_once(dirname(__FILE__) . '/../ykval-log.php');
require_once(dirname(__FILE__) . '/../ykval-db.php');


class setupTest extends PHPUnit_Framework_TestCase
{

  public function setup()
  {
    $this->yubi = &new Auth_Yubico('1', null);
    $this->yubi->setURLPart("api2.yubico.com/wsapi/verify");
  }

  function microtime_float()
  {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
  }
  
  function testStandardValidation() 
  {
    $myKey=new otpgen("mysql:dbname=ykval_systemtest;host=127.0.0.1", 
		      "ykval-systester", 
		      "lab", 
		      array(), 
		      "ykval-systemtest", 
		      "ccccccccgchv");
    $otp=$myKey->getOtp();
    $this->assertTrue(is_string($otp), "getOtp should return a string");
    $this->assertEquals(44, strlen($otp), "OTP should have length 32");

    $auth=$this->yubi->verify($otp);


    if (PEAR::isError($auth)) {
      echo "\nERROR MESSAGE IS " . $auth->getMessage() . "\n";
    }
    $this->assertFalse(PEAR::isError($auth), "An error should not have been raised by this OTP.");

    $validation_pool=array("api3.yubico.com/wsapi/verify", 
			   "api4.yubico.com/wsapi/verify",
			   "api5.yubico.com/wsapi/verify");

    // We except the calls to these to fail with replayed_otp. 

    foreach ($validation_pool as $server){
      $this->yubi->setURLPart($server);
      $auth=$this->yubi->verify($otp);
      $this->assertTrue(PEAR::isError($auth), "An error should have been raised by this OTP.");
      $this->assertEquals("REPLAYED_OTP", $auth->getMessage(), "OTP should be reported as replayed.");
    }
  }
  
}
?>