<?php

class Log
{
  
  function __construct($name='ykval')
  {
    $this->name=$name;
    $this->fields=array();
  }
  
  function addField($name, $value) 
  {
    $this->fields[$name]=$value;
  }
  
  function log($priority, $message, $arr=null){
    if (is_array($arr)) {
      foreach($arr as $key=>$value){
	$message.=" $key=$value ";
      }
    }
    # Add fields
    $msg_fields = "";
    foreach ($this->fields as $field=>$value) {
      $mes_fields .= "[" . $value . "] ";
    }
    syslog($priority, $this->name . ':' . $msg_fields . $message);
  }
  
}

?>
