<?php
/*
	yubikey.php
	Written by John E. Woltman IV
	Released under the LGPL v2.
	Based on code from Yubico's C and Java server samples.
	
	WARNING: I have not tested this with an actual yubikey yet, only with
	the sample output included in yubico-c's README file.
	
	NOTICE: This class DOES NOT track or log a Yubikey's counters and
	timestamps.  You can use this code to integrate Yubikey authentication
	into your own backend authentication system and keep track of the necessary
	information yourself.
	
	Please see the file yubitest.php for a test scenario.
	
*/
 require_once('AES128.php');
 
 if (!isset($trace)) { $trace = 0; }
 
 /* 
 * Class ModHex
 * Encapsulates encoding/decoding text with the ModHex encoding from Yubico.
 * ModHex::Decode decodes a ModHex string
 * ModHex::Encode encodes a regular string into ModHex
 *
 */
class ModHex {

	static $TRANSKEY = "cbdefghijklnrtuv"; // translation key used to ModHex a string
	
	// ModHex encodes the string $src
	static function encode($src) {
		$encoded = "";
		$i = 0;
		$srcLen = strlen($src);
		for ($i = 0; $i < $srcLen; $i++) {
			$bin = (ord($src[$i]));
			$encoded .= ModHex::$TRANSKEY[((int)$bin >> 4) & 0xf];
			$encoded .= ModHex::$TRANSKEY[ (int)$bin & 0xf];
		}
		return $encoded;		
	}
	
	// ModHex decodes the string $token.  Returns the decoded string if successful,
	// or zero if an encoding error was found.
	static function decode($token) {
		$tokLen = strlen($token);	// length of the token
		$decoded = "";				// decoded string to be returned
		
		// strings must have an even length
		if ( $tokLen % 2 != 0 ) { return FALSE; }
		
		for ($i = 0; $i < $tokLen; $i=$i+2 ) {
			$high = strpos(ModHex::$TRANSKEY, $token[$i]);
			$low = strpos(ModHex::$TRANSKEY, $token[$i+1]);

			// if there's an invalid character in the encoded $token, fail here.
			if ( $high === FALSE || $low === FALSE ) 
				return FALSE;

			$decoded .= chr(($high << 4) | $low);
		}
		return $decoded;
	}
	

}

/*
 * Class Yubikey
 * This class does most of the hard work to give you useable data.
 *
 * Please refer to the documentation for further information on usage.
 *
 */

class Yubikey {

	// Some magic numbers for processing the keys
	const OTP_STRING_LENGTH = 32;	// # of characters in the encrypted token of the OTP
	const UID_SIZE = 12; // # of characters in the private ID
	const CRC_OK_RESIDUE = 0xf0b8;
	
	// Error codes
	const ERROR_TOO_SHORT = 1;
	const ERROR_BAD_CRC = 2;
	const ERROR_MODHEX_FAILED = 3;
	
	// Decrypts a ModHexed one-time-password $ModHexOTP with the given $key
	static function decrypt_otp($ModHexOTP, $key) {
		$aes = new AES128(true);
		$decoded = ModHex::Decode($ModHexOTP);
		if ( $decoded === FALSE )
			return FALSE;
		return $aes->blockDecrypt($decoded, $aes->makeKey($key));
	}
	
	static function calculate_crc($token) {
		
		$crc = 0xffff;

 		for ($i = 0; $i < 16; $i++ ) {
			$b = hexdec($token[$i*2].$token[($i*2)+1]);
			
			$crc = $crc ^ ($b & 0xff);
			
			for ($j = 0; $j < 8; $j++) {
				$n = $crc & 1;
				$crc = $crc >> 1;
				if ( $n != 0) { $crc = $crc ^ 0x8408; }
			}
		}
		return $crc;
	}
	
	static function crc_is_good($token) {
		global $trace;
		$crc = Yubikey::calculate_crc($token);
		if ($trace) echo 'Calculated CRC='.$crc."\n";
		return $crc == Yubikey::CRC_OK_RESIDUE;
	}
	
	/**
	 * Returns the public ID of the given ModHexed one-time-password
	 */
	static function get_public_id($otp) {
		return substr($otp, 0, strlen($otp) - Yubikey::OTP_STRING_LENGTH);
	}
	
	/**
	 * Decode
	 */
	static function decode( $otp, $key ) {
		global $trace;
		$otpLen = strlen($otp);

		$decoded = array();	// hold our return values
		
		// if the $otp is longer than 32, assume the extra characters
		// are the yubikey's unchanging public ID.  If the $otp is too short,
		// return immediately.
		if ($otpLen > Yubikey::OTP_STRING_LENGTH) {
			$decoded["public_id"] = substr($otp, 0, $otpLen - Yubikey::OTP_STRING_LENGTH);
		} elseif ( $otpLen < Yubikey::OTP_STRING_LENGTH ) {
			if ($trace) echo 'OTP too short';
			return Yubikey::ERROR_TOO_SHORT;
		}
		
		// Decrypt the token (the last 32 characters of the $otp)
		$decoded["token"] = Yubikey::decrypt_otp(substr($otp, 0-Yubikey::OTP_STRING_LENGTH), $key);
		if ( $decoded["token"] === FALSE ) {
			if ($trace) echo 'Decryption failure';
			return Yubikey::ERROR_MODHEX_FAILED;
		}
		
		//// Raw values extracted from the decoded OTP
		//
		
		// Private ID
		$start = 0;
		$decoded["private_id"] = substr($decoded["token"], $start, Yubikey::UID_SIZE);
		$start += Yubikey::UID_SIZE;
		
		// Session Counter
		$scounter = hexdec(substr($decoded["token"], $start+2, 2).substr($decoded["token"], $start, 2)) & 0xffff;
		$start += 4;
		$decoded['session_counter'] = $scounter;
		if ($trace) echo 'Sess ctr='.$scounter."\n";
		
		// Time stamp LOW
		$timelow = hexdec(substr($decoded["token"], $start+2, 2).substr($decoded["token"], $start, 2)) & 0xffff;
		$start += 4;
		$decoded["low"] = $timelow;
		if ($trace) echo 'TS lo='.$timelow."\n";
		
		// Time stamp HIGH
		$timehigh = hexdec(substr($decoded["token"], $start, 2)) & 0xff;
		$start += 2;
		$decoded["high"] = $timehigh;
		if ($trace) echo 'TS hi='.$timehigh."\n";

		// Session Use
		$session_use = hexdec(substr($decoded["token"], $start, 2)) & 0xff;
		$start += 2;
		$decoded["session_use"] = $session_use;
		if ($trace) echo 'Use='.$session_use."\n";
		
		// Randomness - No need to return this
		$decoded["random"] = hexdec(substr($decoded["token"], $start+2, 2).substr($decoded["token"], $start, 2));
		$start += 4;
		if ($trace) echo 'Rand='.$decoded["random"]."\n";

		// CRC
		$decoded["crc"] = hexdec(substr($decoded["token"], $start+2, 2).substr($decoded["token"], $start, 2));
		if ($trace) echo 'CRC='.$decoded["crc"]."\n";
		
		//// Derived values for convenient use
		//

		// Full time stamp
		$decoded["timestamp"] = ($timehigh << 16) + $timelow;
		if ($trace) echo 'Timestamp='.$decoded["timestamp"]."\n";
		
		// Session Counter
		$decoded["counter"] = ($scounter<<8) + $session_use;

		if ( Yubikey::crc_is_good($decoded["token"]) ) {
			if ($trace) echo "Good CRC\n";
			return $decoded;
		}
		if ($trace) echo "Bad CRC\n";
		return Yubikey::ERROR_BAD_CRC;
	}
	
}
?>
