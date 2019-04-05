<?php

# Copyright (c) 2010-2016 Yubico AB
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

require_once 'ykval-common.php';

class LogVerify
{
	public $format = null;

	private $fields = array(
		'time_start' => null,
		'time_end' => null,
		'time_taken' => null,
		'ip' => null,
		'client' => null,
		'public_id' => null,
		'otp' => null,
		'status' => null,
		'nonce' => null,
		'signed' => null,
		'counter' => null,
		'low' => null,
		'high' => null,
		'use' => null,
		'tls' => null,
		'protocol' => null,
		'sl' => null,
		'timeout' => null,
	);

	/**
	 * Set field value.
	 *
	 * @param $name string
	 * @param $value mixed
	 * @return bool
	 */
	public function set($name, $value)
	{
		// not settable from outside
		if ($name === 'time_end' || $name === 'time_taken')
			return false;

		if (array_key_exists($name, $this->fields) === false)
			return false;

		$this->fields[$name] = $value;
		return true;
	}

	/**
	 * Write verify request log line to syslog.
	 *
	 * @return bool
	 */
	public function write()
	{
		if ($this->format === null)
			return false;

		$values = array();
		foreach ($this->sanitized() as $key => $val)
		{
			$values['%'.$key.'%'] = $val;
		}

		$message = strtr($this->format, $values);

		if (!is_string($message))
			return false;

		return syslog(LOG_INFO, $message);
	}

	/**
	 * Sanitize untrusted values from clients before writing them to syslog.
	 *
	 * P.S. signed, status, time_start, tls are assumed safe,
	 *	since they are set internally.
	 *
	 * @return array sanitized $this->fields
	 */
	private function sanitized()
	{
		$a = $this->fields;

		if (preg_match('/^[cbdefghijklnrtuv]+$/', $a['public_id']) !== 1
			|| strlen($a['public_id']) < 1
			|| strlen($a['public_id']) > (OTP_MAX_LEN - TOKEN_LEN))
		{
			$a['public_id'] = '-';
		}

		if (preg_match('/^[cbdefghijklnrtuv]+$/', $a['otp']) !== 1
			|| strlen($a['otp']) < TOKEN_LEN
			|| strlen($a['otp']) > OTP_MAX_LEN)
		{
			$a['otp'] = '-';
		}

		if (preg_match('/^[0-9]+$/', $a['client']) !== 1)
			$a['client'] = '-';

		if (filter_var($a['ip'], FILTER_VALIDATE_IP) === false)
			$a['ip'] = '-';

		if (is_int($a['counter']) === false)
			$a['counter'] = '-';

		if (is_int($a['low']) === false)
			$a['low'] = '-';

		if (is_int($a['high']) === false)
			$a['high'] = '-';

		if (is_int($a['use']) === false)
			$a['use'] = '-';

		if (preg_match('/^[a-zA-Z0-9]{16,40}$/', $a['nonce']) !== 1)
			$a['nonce'] = '-';

		if (is_float($a['protocol']) === true)
			$a['protocol'] = sprintf('%.1f', $a['protocol']);
		else
			$a['protocol'] = '-';

		if (   $a['sl'] !== 'fast'
			&& $a['sl'] !== 'secure'
			&& (preg_match('/^[0-9]{1,3}$/', $a['sl']) !== 1 || (((int) $a['sl']) > 100)))
		{
			$a['sl'] = '-';
		}

		if (preg_match('/^[0-9]+$/', $a['timeout']) !== 1)
			$a['timeout'] = '-';

		$start = explode(' ', $a['time_start']);
		$start_msec = $start[0];
		$start_sec = $start[1];
		$start = bcadd($start_sec, $start_msec, 8);
		unset($start_sec, $start_msec);

		$end = explode(' ', microtime());
		$end_msec = $end[0];
		$end_sec = $end[1];
		$end = bcadd($end_sec, $end_msec, 8);
		unset($end_sec, $end_msec);

		$taken = bcsub($end, $start, 8);

		$a['time_start'] = $start;
		$a['time_end'] = $end;
		$a['time_taken'] = $taken;

		return $a;
	}
}
