<?php

require_once 'PHPUnit/Framework.php';
require_once (dirname(__FILE__) . '/../ykval-synclib.php');
require_once(dirname(__FILE__) . '/../ykval-config.php');
require_once(dirname(__FILE__) . '/../lib/Db.php'); 

class SyncLibTest extends PHPUnit_Framework_TestCase
{

  public function setup()
  {
    global $baseParams;
    $db = new Db($baseParams['__YKVAL_DB_HOST__'],
		 'root',
		 'lab',
		 $baseParams['__YKVAL_DB_NAME__']);
    $db->connect();
   # $db->truncateTable('queue');
    $db->disconnect();
  }
  public function testTemplate()
  {
  }

  public function testConstructor()
  {
    $sl = new SyncLib();
    $this->assertGreaterThan(1, $sl->getNumberOfServers());
    $this->assertEquals($sl->getServer(0), "http://api2.yubico.com/wsapi/sync");
  }


  public function testQueue()
  {
    $sl = new SyncLib();
    $nr_servers = $sl->getNumberOfServers();
    $queue_length = $sl->getQueueLength();


    $sl->queue(array('modified'=>1259585588,
		     'otp'=>"ccccccccccccfrhiutjgfnvgdurgliidceuilikvfhui",
		     'yk_identity'=>"cccccccccccc",
		     'yk_counter'=>10,
		     'yk_use'=>20,
		     'yk_high'=>100,
		     'yk_low'=>1000),
	       array('modified'=>1259585588,
		     'otp'=>"ccccccccccccfrhiutjgfnvgdurgliidceuilikvfhui",
		     'yk_identity'=>"cccccccccccc",
		     'yk_counter'=>10,
		     'yk_use'=>18,
		     'yk_high'=>100,
		     'yk_low'=>1000)
	       );

    
    $this->assertEquals($nr_servers + $queue_length, $sl->getQueueLength());
    $lastSync=$sl->getLast();
    $this->assertEquals($lastSync['modified'], 1259585588);
    $this->assertEquals($lastSync['otp'], "ccccccccccccfrhiutjgfnvgdurgliidceuilikvfhui");
    $this->assertEquals($lastSync['yk_identity'], "cccccccccccc");
    $this->assertEquals($lastSync['yk_counter'], 10);
    $this->assertEquals($lastSync['yk_use'], 20);
    $this->assertEquals($lastSync['yk_high'], 100);
    $this->assertEquals($lastSync['yk_low'], 1000);
  }

  public function testCountersHigherThan()
  {
    $sl = new SyncLib();
    $localParams=array('yk_counter'=>100, 
		       'yk_use'=>10);
    $otpParams=array('yk_counter'=>100,
		     'yk_use'=>11);

    $this->assertTrue($sl->countersHigherThan($otpParams, $localParams));
    $this->assertFalse($sl->countersHigherThan($localParams, $otpParams));
    $otpParams['yk_use']=10;
    $this->assertFalse($sl->countersHigherThan($otpParams, $localParams));
    $otpParams['yk_counter']=99;
    $this->assertFalse($sl->countersHigherThan($otpParams, $localParams));
    $otpParams['yk_counter']=101;
    $this->assertTrue($sl->countersHigherThan($otpParams, $localParams));
  }

  public function testCountersHigherThanOrEqual()
  {
    $sl = new SyncLib();
    $localParams=array('yk_counter'=>100, 
		       'yk_use'=>10);
    $otpParams=array('yk_counter'=>100,
		     'yk_use'=>11);

    $this->assertTrue($sl->countersHigherThanOrEqual($otpParams, $localParams));
    $this->assertFalse($sl->countersHigherThanOrEqual($localParams, $otpParams));
    $otpParams['yk_use']=10;
    $this->assertTrue($sl->countersHigherThanOrEqual($otpParams, $localParams));
    $otpParams['yk_counter']=99;
    $this->assertFalse($sl->countersHigherThanOrEqual($otpParams, $localParams));
    $otpParams['yk_counter']=101;
    $this->assertTrue($sl->countersHigherThanOrEqual($otpParams, $localParams));
  }


  public function testSync1()
  {
    $sl = new SyncLib();
    $sl->syncServers = array("http://localhost/wsapi/syncvalid1", 
			     "http://localhost/wsapi/syncvalid2",
			     "http://localhost/wsapi/syncvalid3");
    
    $start_length=$sl->getQueueLength();
    $this->assertTrue(
		      $sl->queue(array('modified'=>1259585588+1000,
				       'otp'=>"ccccccccccccfrhiutjgfnvgdurgliidceuilikvfhui",
				       'yk_identity'=>"cccccccccccc",
				       'yk_counter'=>9,
				       'yk_use'=>3,
				       'yk_high'=>100,
				       'yk_low'=>1000),
				 array('modified'=>1259585588,
				       'otp'=>"ccccccccccccfrhiutjgfnvgdurgliidceuilikvfhui",
				       'yk_identity'=>"cccccccccccc",
				       'yk_counter'=>10,
				       'yk_use'=>18,
				       'yk_high'=>100,
				       'yk_low'=>1000)
				 ));


    
    $res=$sl->sync(3);
    $this->assertEquals(3, $sl->getNumberOfValidAnswers());
    $this->assertTrue($res, "all sync servers should be configured to return ok values");
    $this->assertEquals($start_length, $sl->getQueueLength());

    $this->assertTrue(
		      $sl->queue(array('modified'=>1259585588+1000,
				       'otp'=>"ccccccccccccfrhiutjgfnvgdurgliidceuilikvfhui",
				       'yk_identity'=>"cccccccccccc",
				       'yk_counter'=>9,
				       'yk_use'=>3,
				       'yk_high'=>100,
				       'yk_low'=>1000),
				 array('modified'=>1259585588,
				       'otp'=>"ccccccccccccfrhiutjgfnvgdurgliidceuilikvfhui",
				       'yk_identity'=>"cccccccccccc",
				       'yk_counter'=>10,
				       'yk_use'=>18,
				       'yk_high'=>100,
				       'yk_low'=>1000)
				 ));

    
    $res=$sl->sync(2);
    $this->assertEquals(2, $sl->getNumberOfValidAnswers());
    $this->assertTrue($res, "all sync servers should be configured to return ok values");
    $this->assertEquals($start_length+1, $sl->getQueueLength());

    
  }

  public function testSync2()
  {
    $sl = new SyncLib();
    $sl->syncServers = array("http://localhost/wsapi/syncinvalid1", 
			     "http://localhost/wsapi/syncinvalid2",
			     "http://localhost/wsapi/syncinvalid3");
    
    $start_length=$sl->getQueueLength();
    $this->assertTrue(
		      $sl->queue(array('modified'=>1259585588+1000,
				       'otp'=>"ccccccccccccfrhiutjgfnvgdurgliidceuilikvfhui",
				       'yk_identity'=>"cccccccccccc",
				       'yk_counter'=>9,
				       'yk_use'=>3,
				       'yk_high'=>100,
				       'yk_low'=>1000),
				 array('modified'=>1259585588,
				       'otp'=>"ccccccccccccfrhiutjgfnvgdurgliidceuilikvfhui",
				       'yk_identity'=>"cccccccccccc",
				       'yk_counter'=>10,
				       'yk_use'=>18,
				       'yk_high'=>100,
				       'yk_low'=>1000)
				 ));

    
    $res=$sl->sync(3);
    $this->assertEquals(0, $sl->getNumberOfValidAnswers());
    $this->assertFalse($res, "only 1 sync server should have returned ok values");
    $this->assertEquals($start_length, $sl->getQueueLength());

    
  }

  public function testSync3()
  {
    $sl = new SyncLib();
    $sl->syncServers = array("http://localhost/wsapi/syncvalid1", 
			     "http://localhost/wsapi/syncvalid2",
			     "http://localhost/wsapi/syncvalid3");
    
    $start_length=$sl->getQueueLength();
    $this->assertTrue(
		      $sl->queue(array('modified'=>1259585588+1000,
				       'otp'=>"ccccccccccccfrhiutjgfnvgdurgliidceuilikvfhui",
				       'yk_identity'=>"cccccccccccc",
				       'yk_counter'=>9,
				       'yk_use'=>3,
				       'yk_high'=>100,
				       'yk_low'=>1000),
				 array('modified'=>1259585588,
				       'otp'=>"ccccccccccccfrhiutjgfnvgdurgliidceuilikvfhui",
				       'yk_identity'=>"cccccccccccc",
				       'yk_counter'=>10,
				       'yk_use'=>18,
				       'yk_high'=>100,
				       'yk_low'=>1000)
				 ));

    
    $res=$sl->sync(1);
    $this->assertEquals(1, $sl->getNumberOfValidAnswers());
    $this->assertTrue($res, "only 1 sync server should have returned ok values");
    $this->assertEquals($start_length+2, $sl->getQueueLength());

    
  }
  
  public function testActivateQueue()
  {
  }
}
?>