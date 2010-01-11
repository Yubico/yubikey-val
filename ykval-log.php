<?php

class Log
{
  
  function __construct($name='ykval')
  {
    $this->name=$name;
  }
  function log($priority, $message, $arr=null){
    if (is_array($arr)) {
      foreach($arr as $key=>$value){
	$message.=" $key=$value ";
      }
    }
    syslog($priority, $this->name . ':' . $message);
  }
  
}

?>
