<?php

# Copyright (c) 2012-2013 Yubico AB
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are
# met:
#
#   * Redistributions of source code must retain the above copyright
#     notice, this list of conditions and the following disclaimer.
#
#   * Redistributions in binary form must reproduce the above
#     copyright notice, this list of conditions and the following
#     disclaimer in the documentation and/or other materials provided
#     with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
# "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
# LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
# A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
# OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
# SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
# LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
# DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
# THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
# (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
# OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

/**
 * Class for managing database connection
 */

require_once('ykval-log.php');
require_once('ykval-db.php');

class DbImpl extends Db
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
    $this->result = null;

    $this->myLog=new Log($name);
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

  protected function query($query, $returnresult=false) {
    if(!$this->isConnected()) {
      $this->connect();
    }
    if($this->isConnected()) {
      $this->myLog->log(LOG_DEBUG, 'DB query is: ' . $query);

      try {
	$this->result = $this->dbh->query($query);
      } catch (PDOException $e) {
	$this->myLog->log(LOG_INFO, 'Database query error: ' . preg_replace('/\n/',' ',print_r($this->dbh->errorInfo(), true)));
	$this->dbh = Null;
	return false;
      }
      if ($returnresult) return $this->result;
      else return true;
    } else {
      $this->myLog->log(LOG_CRIT, 'No database connection');
      return false;
    }
  }


  /**
   * function to get a row from the query result
   * Once all rows have been fetch, function closeCursor needs to be called
   *
   * @param object $result Query result object or null to use the current one
   * @return array a query row
   *
   */
  public function fetchArray($result=null){
    if(!$result) $result = $this->result;
    if(!$result) return null;

    return $result->fetch(PDO::FETCH_ASSOC);
  }

  /**
   * function to close the cursor after having fetched rows
   * 
   * @param object $result Query result object or null to use the current one
   *
   */
  public function closeCursor($result=null){
    if(!$result) $result = $this->result;
    if($result) $result->closeCursor();
  }

  public function truncateTable($name)
  {
    $this->query("TRUNCATE TABLE " . $name);
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
      $row = $this->fetchArray($result);
      $this->closeCursor($result);
      return $row;
    }
    else {
      $collection=array();
      while($row = $this->fetchArray($result)){
	$collection[]=$row;
      }
      $this->closeCursor($result);
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
   * Function to get the number of rows
   *
   * @param object $result Query result object or null to use the current one
   * @return int number of rows affected by last statement or 0 if database connection is not functional.
   *
   */
  public function rowCount($result=null)
  {
    if(!$result) $result = $this->result;
    if($result) {
	$count=$result->rowCount();
	$result->closeCursor();
	return $count;
    } else {
	return 0;
    }
  }
}


?>
