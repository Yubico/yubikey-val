<?php

# Copyright (c) 2009-2013 Yubico AB
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

abstract class Db
{
  /**
   * static function to determine database type and instantiate the correct subclass
   *
   * */
  public static function GetDatabaseHandle($baseParams, $logname)
  {
    if(substr($baseParams['__YKVAL_DB_DSN__'], 0, 3) == 'oci') {
      require_once 'ykval-db-oci.php';
    } else {
      require_once 'ykval-db-pdo.php';
    }
    return new DbImpl($baseParams['__YKVAL_DB_DSN__'],
                          $baseParams['__YKVAL_DB_USER__'],
                          $baseParams['__YKVAL_DB_PW__'],
                          $baseParams['__YKVAL_DB_OPTIONS__'],
                          $logname . ':db');
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
