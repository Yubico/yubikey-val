<?php

include 'common.php';

$data = array(
	      #'http://ykksm.example.com/wsapi/decrypt/?otp=dteffujehknhfjbrjnlnldnhcujvddbikngjrtgh', # Valid response
	      'http://www.google.com:4711', # Connection times out
	      'http://smtp1.google.com', # Connection refused
	      'http://www.google.com/unknown', # 404
	      'http://josefsson.org/key.txt', # incorrect data, but takes some time to load
	      'http://klcxkljsdfiojsafjiosaiojd.org/', # No such domain
);
echo '<pre>';

$r = retrieveURLasync($data);
if ($r) {
  print "ok $r";
 } else {
  print "err";
 }

?>
