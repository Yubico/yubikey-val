<?php

require_once(dirname(__FILE__) . '/../ykval-config.php');
require_once(dirname(__FILE__) . '/../ykval-db.php');
require_once 'PHPUnit/Framework.php';
 

class DbTest extends PHPUnit_Framework_TestCase
{

  public function setup()
  {
    global $baseParams;
    $this->db=new Db($baseParams['__YKVAL_DB_HOST__'],
		     'root',
		     'lab',
		     $baseParams['__YKVAL_DB_NAME__']);
    $this->db->connect();
    $this->db->customQuery("drop table unittest");
    $this->db->customQuery("create table unittest (value1 int, value2 int)");
  }
  public function test_template()
  {
  }

  public function testConnect()
  {
    $this->assertTrue($this->db->isConnected());
    $this->db->disconnect();
    $this->assertFalse($this->db->isConnected());
  }
  public function testSave()
  {
    $this->assertTrue($this->db->save('unittest', array('value1'=>100,
							 'value2'=>200)));
    $res=$this->db->findByMultiple('unittest', array('value1'=>100,
						      'value2'=>200));
    $this->assertEquals(1, count($res));
  }
		      
}
?>