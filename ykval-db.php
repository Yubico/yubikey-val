<?php


/**
 * Class for managing database connection
 */

require_once('ykval-log.php');

class Db
{


  /**
   * Constructor
   *
   * @param string $host Database host
   * @param string $user Database user
   * @param string $pwd  Database password
   * @param string $name Database table name
   * @return void 
   *
   */
  public function __construct($db_dsn, $db_username, $db_password, $db_options, $name='ykval-db')
  {
    $this->db_dsn=$db_dsn;
    $this->db_username=$db_username;
    $this->db_password=$db_password;
    $this->db_options=$db_options;

    $this->myLog=new Log($name);
  }

  function addField($name, $value)
  {
    $this->myLog->addField($name, $value);
  }

  /**
   * function to convert Db timestamps to unixtime(s)
   *
   * @param string $updated Database timestamp 
   * @return int Timestamp in unixtime format
   *
   */
  public function timestampToTime($updated)
  {
    $stamp=strptime($updated, '%F %H:%M:%S');
    return mktime($stamp[tm_hour], $stamp[tm_min], $stamp[tm_sec], $stamp[tm_mon]+1, $stamp[tm_mday], $stamp[tm_year]);

  }

  /**
   * function to compute delta (s) between 2 Db timestamps
   *
   * @param string $first Database timestamp 1 
   * @param string $second Database timestamp 2
   * @return int Deltatime (s)
   *
   */
  public function timestampDeltaTime($first, $second)
  {
    return Db::timestampToTime($second) - Db::timestampToTime($first);
  }

  /**
   * function to disconnect from database
   *
   * @return boolean True on success, otherwise false.
   *
   */
  public function disconnect()
  {
    $this->dbh=NULL;
  }
  
  /**
   * function to check if database is connected
   *
   * @return boolean True if connected, otherwise false.
   *
   */
  public function isConnected()
  {
    if ($this->dbh!=NULL) return True;
    else return False;
  }
  /**
   * function to connect to database defined in config.php
   *
   * @return boolean True on success, otherwise false.
   *
   */
  public function connect(){

    try {
      $this->dbh = new PDO($this->db_dsn, $this->db_username, $this->db_password, $this->db_options);
    } catch (PDOException $e) {
      $this->myLog->log(LOG_CRIT, "Database connection error: " . $e->getMessage());
      $this->dbh=Null;
      return false;
    }
    return true;
  }

  private function query($query, $returnresult=false) {
    if($this->dbh) {
      $this->myLog->log(LOG_DEBUG, 'DB query is: ' . $query);
      
      $this->result = $this->dbh->query($query);
      if (! $this->result){
	$this->myLog->log(LOG_INFO, 'Database query error: ' . preg_replace('/\n/',' ',print_r($this->dbh->errorInfo(), true)));
	return false;
      }
      if ($returnresult) return $this->result;
      else return true;
    } else {
      $this->myLog->log(LOG_CRIT, 'No database connection');
      return false;
    }
  }

  public function truncateTable($name)
  {
    $this->query("TRUNCATE TABLE " . $name);
  }

  /**
   * function to update row in database by a where condition
   *
   * @param string $table Database table to update row in
   * @param int $id Id on row to update
   * @param array $values Array with key=>values to update
   * @return boolean True on success, otherwise false.
   *
   */
  public function updateBy($table, $k, $v, $values)
  {
    $query = "";

    foreach ($values as $key=>$value){
      if (!is_null($value)) $query .= ' ' . $key . "='" . $value . "',";
      else $query .= ' ' . $key . '=NULL,';
    }
    if (! $query) {
      $this->myLog->log(LOG_DEBUG, "no values to set in query. Not updating DB");
      return true;
    }

    $query = rtrim($query, ",") . " WHERE " . $k . " = '" . $v . "'";
    // Insert UPDATE statement at beginning
    $query = "UPDATE " . $table . " SET " . $query; 
    
    return $this->query($query, false);
  }


  /**
   * function to update row in database
   *
   * @param string $table Database table to update row in
   * @param int $id Id on row to update
   * @param array $values Array with key=>values to update
   * @return boolean True on success, otherwise false.
   *
   */
  public function update($table, $id, $values)
  {
    return $this->updateBy($table, 'id', $id, $values);
  }

  /**
   * function to update row in database based on a condition
   *
   * @param string $table Database table to update row in
   * @param string $k Column to select row on
   * @param string $v Value to select row on
   * @param array $values Array with key=>values to update
   * @param string $condition conditional statement
   * @return boolean True on success, otherwise false.
   *
   */
  public function conditionalUpdateBy($table, $k, $v, $values, $condition)
  {
    $query = ""; /* quiet the PHP Notice */

    foreach ($values as $key=>$value){
      $query = $query . " " . $key . "='" . $value . "',";
    }
    if (! $query) {
      $this->myLog->log(LOG_DEBUG, "no values to set in query. Not updating DB");
      return true;
    }

    $query = rtrim($query, ",") . " WHERE " . $k . " = '" . $v . "' and " . $condition;
    // Insert UPDATE statement at beginning
    $query = "UPDATE " . $table . " SET " . $query; 

    return $this->query($query, false);
  }
    

  /**
   * Function to update row in database based on a condition.
   * An ID value is passed to select the appropriate column
   *
   * @param string $table Database table to update row in
   * @param int $id Id on row to update
   * @param array $values Array with key=>values to update
   * @param string $condition conditional statement
   * @return boolean True on success, otherwise false.
   *
   */
  public function conditionalUpdate($table, $id, $values, $condition)
  {
    return $this->conditionalUpdateBy($table, 'id', $id, $values, $condition);
  }

  /**
   * function to insert new row in database
   *
   * @param string $table Database table to update row in
   * @param array $values Array with key=>values to update
   * @return boolean True on success, otherwise false.
   *
   */
  public function save($table, $values)
  {
    $query= 'INSERT INTO ' . $table . " (";
    foreach ($values as $key=>$value){
      if (!is_null($value)) $query = $query . $key . ",";
    }
    $query = rtrim($query, ",") . ') VALUES (';
    foreach ($values as $key=>$value){
      if (!is_null($value)) $query = $query . "'" . $value . "',";
    }
    $query = rtrim($query, ",");
    $query = $query . ")";
    return $this->query($query, false);
  }
  /**
   * helper function to collect last row[s] in database
   *
   * @param string $table Database table to update row in
   * @param int $nr Number of rows to collect. NULL=>inifinity. DEFAULT=1.
   * @return mixed Array with values from Db row or 2d-array with multiple rows
or false on failure.
   *
   */
  public function last($table, $nr=1)
  {
    return Db::findBy($table, null, null, $nr, 1);
  }

  /**
   * main function used to get rows from Db table. 
   *
   * @param string $table Database table to update row in
   * @param string $key Column to select rows by
   * @param string $value Value to select rows by
   * @param int $nr Number of rows to collect. NULL=>inifinity. Default=NULL.
   * @param int $rev rev=1 indicates order should be reversed. Default=NULL.
   * @return mixed Array with values from Db row or 2d-array with multiple rows
   *
   */
  public function findBy($table, $key, $value, $nr=null, $rev=null)
  {
    return $this->findByMultiple($table, array($key=>$value), $nr, $rev);
  }

  /**
   * main function used to get rows by multiple key=>value pairs from Db table. 
   *
   * @param string $table Database table to update row in
   * @param array $where Array with column=>values to select rows by
   * @param int $nr Number of rows to collect. NULL=>inifinity. Default=NULL.
   * @param int $rev rev=1 indicates order should be reversed. Default=NULL.
   * @param string distinct Select rows with distinct columns, Default=NULL
   * @return mixed Array with values from Db row or 2d-array with multiple rows
   *
   */
  public function findByMultiple($table, $where, $nr=null, $rev=null, $distinct=null)
  {
    $value=""; /* quiet the PHP Notice */
    $match=null; /* quiet the PHP Notice */
    $query="SELECT";
    if ($distinct!=null) {
      $query.= " DISTINCT " . $distinct;
    } else {
      $query.= " *";
    }
    $query.= " FROM " . $table;
    if ($where!=null){ 
      foreach ($where as $key=>$value) {
	if ($key!=null) {
	  if ($value!=null) $match.= " ". $key . " = '" . $value . "' and";
	  else $match.= " ". $key . " is NULL and";
	}
      }
      if ($match!=null) $query .= " WHERE" . $match;
      $query=rtrim($query, "and");
      $query=rtrim($query);
    }
    if ($rev==1) $query.= " ORDER BY id DESC";
    if ($nr!=null) $query.= " LIMIT " . $nr;

    $result = $this->query($query, true);
    if (!$result) return false;
   
    if ($nr==1) {
      $row = $result->fetch(PDO::FETCH_ASSOC);
      $result->closeCursor();
      return $row;
    } 
    else {
      $collection=array();
      while($row = $result->fetch(PDO::FETCH_ASSOC)){
	$collection[]=$row;
      }
      $result->closeCursor();
      return $collection;
    }

  }

  /**
   * main function used to delete rows by multiple key=>value pairs from Db table. 
   *
   * @param string $table Database table to delete row in
   * @param array $where Array with column=>values to select rows by
   * @param int $nr Number of rows to collect. NULL=>inifinity. Default=NULL.
   * @param int $rev rev=1 indicates order should be reversed. Default=NULL.
   * @param string distinct Select rows with distinct columns, Default=NULL
   * @return boolean True on success, otherwise false.
   *
   */
  public function deleteByMultiple($table, $where, $nr=null, $rev=null)
  {
    $query="DELETE";
    $query.= " FROM " . $table;
    if ($where!=null){ 
      $query.= " WHERE";
      foreach ($where as $key=>$value) {
	$query.= " ". $key . " = '" . $value . "' and";
      }
      $query=rtrim($query, "and");
      $query=rtrim($query);
    }
    if ($rev==1) $query.= " ORDER BY id DESC";
    if ($nr!=null) $query.= " LIMIT " . $nr;
    return $this->query($query, false);
  }


  /**
   * Function to do a custom query on database connection 
   *
   * @param string $query Database query
   * @return mixed 
   *
   */
  public function customQuery($query)
  {
    return $this->query($query, true);
  }

  /**
   * Function to do a custom query on database connection 
   *
   * @return int number of rows affected by last statement or 0 if database connection is not functional.
   *
   */
  public function rowCount()
  {
    if($this->result) { 
      $count=count($this->result->fetchAll());
      $this->result->closeCursor();
      return $count;
    } else {
      return 0;
    }
  }

  /**
   * helper function used to get rows from Db table in reversed order. 
   * defaults to obtaining 1 row. 
   *
   * @param string $table Database table to update row in
   * @param string $key Column to select rows by
   * @param string $value Value to select rows by
   * @param int $nr Number of rows to collect. NULL=>inifinity. Default=1.
   * @return mixed Array with values from Db row or 2d-array with multiple rows or false on failure.
   *
   */
  public function lastBy($table, $key, $value, $nr=1)
  {
    return Db::findBy($table, $key, $value, $nr, 1);
  }

  /**
   * helper function used to get rows from Db table in standard order. 
   * defaults to obtaining 1 row. 
   *
   * @param string $table Database table to update row in
   * @param string $key Column to select rows by
   * @param string $value Value to select rows by
   * @param int $nr Number of rows to collect. NULL=>inifinity. Default=1.
   * @return mixed Array with values from Db row or 2d-array with multiple rows or false on failure.
   *
   */
  public function firstBy($table, $key, $value, $nr=1)
  {
    return Db::findBy($table, $key, $value, $nr);
  }
  
}


?>
