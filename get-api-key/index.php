<?php
if (isset($_REQUEST["email"])) {
  $email = $_REQUEST["email"];
} else {
  $email = "";
}
if (isset($_REQUEST["otp"])) {
  $otp = $_REQUEST["otp"];
} else {
  $otp = "";
}

# Quit early on no input
if ($email && $otp) {

  # Change URL as appropriate.  Use https for non-local connections.
  $url = "http://localhost/wsapi/getapikey?email=" .
    $email . "&otp=" . $otp;
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_USERAGENT, "Get_API_Key");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  $result = curl_exec($ch);
  curl_close($ch);

  if (preg_match('/^code=ok\nid=([0-9]+)\nkey=(.*)/', $result, $out)) {
    $id = $out[1];
    $key = $out[2];
  } else {
    error_log ("YK-GAK bad curl output: $result");
  }
}
?>
<html>
  <head>
    <title>Yubico - Get API Key</title>
  </head>

  <body onLoad="document.getapikey.email.focus();">
    <h1>Yubico - Get API Key</h1>

    <?php if (isset($id) && isset($key)) { ?>

    <p>Congratulations!  Please find below your client identity and
      client API key.

    <p><table border=1>
	<tr><td>Id:</td><td><?php print $id; ?></td></tr>
	<tr><td>API Key:</td><td><?php print $key; ?></td></tr>
      </table>

    <p>For more information on how to use this, see the Developers web
      pages.

    <?php } else { ?>

    <p>Here you can generate a shared symmetric key for use with the
      Yubico Web Services.  You need to authenticate yourself using a
      Yubikey One-Time Password and provide your e-mail address as a
      reference.

    <p><hr>

      <?php if (isset($result)) { ?>
      <h1 style="font-weight: bold; color:#EE1111">
	Authentication failure. Please try again. </h1>
      <?php } ?>

    <p><form name="getapikey" method="post"><table>
	  <tr><td>E-mail address:</td>
	    <td><input type="text" name="email"></td></tr>
	  <tr><td>Yubikey OTP:</td>
	    <td><input autocomplete="off" type="password" name="otp"></td></tr>
	  <tr><td colspan="2">
	      <input type="submit" value="Generate API Key"></td></tr>
	</table>
      </form>

    <?php } ?>

  </body>
</html>
