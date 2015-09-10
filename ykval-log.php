<?php

# Copyright (c) 2010-2015 Yubico AB
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

class Log
{
	private $log_levels = array(
			LOG_EMERG => 'LOG_EMERG',
			LOG_ALERT => 'LOG_ALERT',
			LOG_CRIT => 'LOG_CRIT',
			LOG_ERR => 'LOG_ERR',
			LOG_WARNING => 'LOG_WARNING',
			LOG_NOTICE => 'LOG_NOTICE',
			LOG_INFO => 'LOG_INFO',
			LOG_DEBUG => 'LOG_DEBUG',
	);

	private $fields = array();

	public function __construct ($name = 'ykval')
	{
		$this->name = $name;

		openlog('ykval', LOG_PID, LOG_LOCAL0);
	}

	public function addField ($name, $value)
	{
		$this->fields[$name] = $value;
	}

	public function log ($priority, $message, $extra = NULL)
	{
		$prefix = '';
		foreach ($this->fields as $val)
			$prefix .= "[$val] ";

		$suffix = '';
		if (is_array($extra)) {
			foreach($extra as $key => $value) {
				if (is_array($value)) {
					$value = implode(':', $value);
				}
				$suffix .= " $key=$value ";
			}
		}

		$message = $prefix . $message . $suffix;

		$message = implode(':', array(
				$this->log_levels[$priority],
				$this->name,
				$message
			));

		syslog($priority, $message);
	}
}
