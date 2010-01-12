<?php

require_once(dirname(__FILE__) . '/../ykval-config.php');
require_once(dirname(__FILE__) . '/../ykval-db.php');
require_once 'PHPUnit/Framework.php';
 

class DbTest extends PHPUnit_Framework_TestCase
{

  public function setup()
  {
    global $baseParams;
    $this->db=new Db($baseParams['__YKVAL_DB_DSN__'],
		     'root',
		     'lab',
		     $baseParams['__YKVAL_DB_OPTIONS__']);
    $this->db->connect();
    $this->db->customQuery("drop table unittest");
    $this->db->customQuery("create table unittest (id int,value1 int, value2 int)");

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

  public function testUpdateBy()
  {
    $this->assertTrue($this->db->save('unittest', array('value1'=>100,
							'value2'=>200)));
    $this->db->updateBy('unittest', 'value1', 100, array('value2'=>NULL));
    $res=$this->db->findByMultiple('unittest', array('value1'=>100,
						     'value2'=>NULL));
    $this->assertEquals(1, count($res));
  }
  public function testFindBy()
  {
    $this->assertTrue($this->db->save('unittest', array('value1'=>100,
							'value2'=>200)));
    $res=$this->db->findBy('unittest', 'value1', 100);
    $this->assertEquals(1, count($res));
  }
  public function testUpdate()
  {
    $this->assertTrue($this->db->save('unittest', array('value1'=>100,
							'value2'=>200, 
							'id'=>1)));
    $res=$this->db->findBy('unittest', 'value1', 100);
    $this->assertTrue($this->db->update('unittest', 1, 
					array('value2'=>1000)));
    
    $res=$this->db->findBy('unittest', 'id', 1, 1);
    $this->assertEquals(1000, $res['value2']);
  }
  public function testDeleteByMultiple()
  {
    $this->assertTrue($this->db->save('unittest', array('value1'=>100,
							'value2'=>200, 
							'id'=>1)));
    $this->assertTrue($this->db->deleteByMultiple('unittest', array('value1'=>100, 
								    'value2'=>200)));

  }

  public function testRowCount()
  {
    $this->assertTrue($this->db->save('unittest', array('value1'=>100,
							'value2'=>200, 
							'id'=>1)));
    $this->assertEquals(1, $this->db->rowCount(), "1 row should have been affected by previous statement");
  }
  public function testConditionalUpdate()
  {
    $this->assertTrue($this->db->save('unittest', array('value1'=>100,
							'value2'=>200, 
							'id'=>1)));
    $condition="(100 > value1 or (100=value1 and 200>value2))";
    $this->assertTrue($this->db->conditionalUpdate('unittest', 1, array('value2'=>201), $condition));
    $this->assertEquals(0, $this->db->rowCount(), "One row should have been affected");
    $condition="(100 > value1 or (100=value1 and 201>value2))";
    $this->assertTrue($this->db->conditionalUpdate('unittest', 1, array('value2'=>201), $condition));
    $this->assertEquals(1, $this->db->rowCount(), "One row should have been affected");
  }
  public function testConditionalUpdateBy()
  {
    $this->assertTrue($this->db->save('unittest', array('value1'=>100,
							'value2'=>200, 
							'id'=>1)));
    $condition="(100 > value1 or (100=value1 and 201>value2))";
    $this->assertTrue($this->db->conditionalUpdateBy('unittest', 'value1', 100, array('value2'=>201), $condition));
    $this->assertEquals(1, $this->db->rowCount(), "One row should have been affected");


    $this->db->customQuery("drop table myunittest");
    $this->db->customQuery("create table myunittest (id int, string1 varchar(10), value1 int)");
    

    $this->assertTrue($this->db->save('myunittest', array('value1'=>100,
							  'string1'=>'hej', 
							  'id'=>1)));
    $condition="(101 > value1)";
    $this->assertTrue($this->db->conditionalUpdateBy('myunittest', 'string1', 'hej', array('value1'=>101), $condition));
    $this->assertEquals(1, $this->db->rowCount(), "One row should have been affected");


  }
}
?>