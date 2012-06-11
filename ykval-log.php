<?php

class Log
{

  function __construct($name='ykval')
  {
    $this->name=$name;
    $this->fields=array();

    $this->LOG_LEVELS = array(LOG_EMERG=>'LOG_EMERG',
			      LOG_ALERT=>'LOG_ALERT',
			      LOG_CRIT=>'LOG_CRIT',
			      LOG_ERR=>'LOG_ERR',
			      LOG_WARNING=>'LOG_WARNING',
			      LOG_NOTICE=>'LOG_NOTICE',
			      LOG_INFO=>'LOG_INFO',
			      LOG_DEBUG=>'LOG_DEBUG');

    openlog("ykval", LOG_PID, LOG_LOCAL0);
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
      $msg_fields .= "[" . $value . "] ";
    }
    syslog($priority,
	   $this->LOG_LEVELS[$priority] . ':' .
	   $this->name . ':' .
	   $msg_fields .
	   $message);
  }

}

?>
