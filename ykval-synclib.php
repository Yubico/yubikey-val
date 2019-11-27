<?php

# Copyright (c) 2009-2015 Yubico AB
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

require_once 'ykval-config.php';
require_once 'ykval-common.php';
require_once 'ykval-db.php';
require_once 'ykval-log.php';

function _get($arr, $key) {
	return array_key_exists($key, $arr) ? $arr[$key] : "?";
}

class SyncLib
{
	public $syncServers = array();
	public $dbConn = null;
	public $curlopts = array();

	public function __construct($logname='ykval-synclib')
	{
		$this->myLog = new Log($logname);
		global $baseParams;
		$this->syncServers = $baseParams['__YKVAL_SYNC_POOL__'];
		$this->db = Db::GetDatabaseHandle($baseParams, $logname);
		$this->isConnected=$this->db->connect();
		$this->server_nonce=md5(uniqid(rand()));

		if (array_key_exists('__YKVAL_SYNC_CURL_OPTS__', $baseParams))
		{
			$this->curlopts = $baseParams['__YKVAL_SYNC_CURL_OPTS__'];
		}
	}

	public function addField($name, $value)
	{
		$this->myLog->addField($name, $value);
		$this->db->addField($name, $value);
	}

	public function isConnected()
	{
		return $this->isConnected;
	}

	public function getNumberOfServers()
	{
		return count($this->syncServers);
	}

	public function getNumberOfValidAnswers()
	{
		if (isset($this->valid_answers))
			return $this->valid_answers;

		return 0;
	}

	public function getNumberOfAnswers()
	{
		if (isset($this->answers))
			return $this->answers;

		return 0;
	}

	public function getClientData($client)
	{
		$res = $this->db->customQuery("SELECT id, secret FROM clients WHERE active='1' AND id='" . $client . "'");
		$r = $this->db->fetchArray($res);
		$this->db->closeCursor($res);

		if ($r)
			return $r;

		return false;
	}

	public function getQueueLength()
	{
		return count($this->db->findBy('queue', null, null, null));
	}

	public function getQueueLengthByServer()
	{
		$counters = array();

		foreach ($this->syncServers as $server)
		{
			$counters[$server] = 0;
		}

		$result = $this->db->customQuery('SELECT server, COUNT(server) as count FROM queue GROUP BY server');

		while ($row = $this->db->fetchArray($result))
		{
			$counters[$row['server']] = $row['count'];
		}

		$this->db->closeCursor($result);

		return $counters;
	}

	public function queue($otpParams, $localParams)
	{
		$info = $this->createInfoString($otpParams, $localParams);
		$this->otpParams = $otpParams;
		$this->localParams = $localParams;

		$queued = time();
		$result = true;

		foreach ($this->syncServers as $server)
		{
			$arr = array(
				'queued' => $queued,
				'modified' => $otpParams['modified'],
				'otp' => $otpParams['otp'],
				'server' => $server,
				'server_nonce' => $this->server_nonce,
				'info' => $info
			);

			if (! $this->db->save('queue', $arr))
				$result = false;
		}

		return $result;
	}

	public function log($priority, $msg, $params=null)
	{
		if ($params)
			$msg .= ' modified=' . _get($params, 'modified') .
				' nonce=' . _get($params, 'nonce') .
				' yk_publicname=' . _get($params, 'yk_publicname') .
				' yk_counter=' . _get($params, 'yk_counter') .
				' yk_use=' . _get($params, 'yk_use') .
				' yk_high=' . _get($params, 'yk_high') .
				' yk_low=' . _get($params, 'yk_low');

		if ($this->myLog)
			$this->myLog->log($priority, $msg);
		else
			error_log("Warning: myLog uninitialized in ykval-synclib.php. Message is " . $msg);
	}

	public function getLocalParams($yk_publicname)
	{
		$this->log(LOG_DEBUG, "searching for yk_publicname $yk_publicname in local db");

		$res = $this->db->findBy('yubikeys', 'yk_publicname', $yk_publicname, 1);

		if (!$res)
		{
			$this->log(LOG_NOTICE, "Discovered new identity $yk_publicname");

			$this->db->save('yubikeys', array(
				'active' => 1,
				'created' => time(),
				'modified' => -1,
				'yk_publicname' => $yk_publicname,
				'yk_counter' => -1,
				'yk_use' => -1,
				'yk_low' => -1,
				'yk_high' => -1,
				'nonce' => '0000000000000000',
				'notes' => ''
			));

			$res = $this->db->findBy('yubikeys', 'yk_publicname', $yk_publicname, 1);
		}

		if ($res)
		{
			$localParams = array(
				'modified' => $res['modified'],
				'nonce' => $res['nonce'],
				'active' => $res['active'],
				'yk_publicname' => $yk_publicname,
				'yk_counter' => $res['yk_counter'],
				'yk_use' => $res['yk_use'],
				'yk_high' => $res['yk_high'],
				'yk_low' => $res['yk_low']
			);

			$this->log(LOG_INFO, "yubikey found in db ", $localParams);
			return $localParams;
		}

		$this->log(LOG_NOTICE, "params for yk_publicname $yk_publicname not found in database");
		return false;
	}

	public function updateDbCounters($params)
	{
		if (!isset($params['yk_publicname']))
			return false;

		$arr = array(
			'modified' => $params['modified'],
			'yk_counter' => $params['yk_counter'],
			'yk_use' => $params['yk_use'],
			'yk_low' => $params['yk_low'],
			'yk_high' => $params['yk_high'],
			'nonce' => $params['nonce']
		);

		$condition = '('.$params['yk_counter'].'>yk_counter or ('.$params['yk_counter'].'=yk_counter and ' . $params['yk_use'] . '>yk_use))';

		if (! $this->db->conditionalUpdateBy('yubikeys', 'yk_publicname', $params['yk_publicname'], $arr, $condition))
		{
			$this->log(LOG_CRIT, 'failed to update internal DB with new counters');
			return false;
		}

		if ($this->db->rowCount() > 0)
			$this->log(LOG_INFO, 'updated database ', $params);
		else
			$this->log(LOG_INFO, 'database not updated', $params);

		return true;
	}

	public function countersHigherThan($p1, $p2)
	{
		if ($p1['yk_counter'] > $p2['yk_counter'])
			return true;

		if ($p1['yk_counter'] == $p2['yk_counter'] && $p1['yk_use'] > $p2['yk_use'])
			return true;

		return false;
	}

	public function countersHigherThanOrEqual($p1, $p2)
	{
		if ($p1['yk_counter'] > $p2['yk_counter'])
			return true;

		if ($p1['yk_counter'] == $p2['yk_counter'] && $p1['yk_use'] >= $p2['yk_use'])
			return true;

		return false;
	}

	public function countersEqual($p1, $p2)
	{
		return ($p1['yk_counter'] == $p2['yk_counter'] && $p1['yk_use'] == $p2['yk_use']);
	}

	// queue daemon
	public function reSync($older_than, $timeout)
	{
		$this->log(LOG_DEBUG, 'starting resync');

		/* Loop over all unique servers in queue */
		$queued_limit = time()-$older_than;
		$server_res = $this->db->customQuery("select distinct server from queue WHERE queued < " . $queued_limit . " or queued is null");
		$server_list = array();
		$mh = curl_multi_init();
		$ch = array();
		$entries = array();
		$handles = 0;
		$num_per_server = 4;
		$curlopts = $this->curlopts;

		while ($my_server = $this->db->fetchArray($server_res))
		{
			$server = $my_server['server'];
			$this->log(LOG_DEBUG, "Processing queue for server " . $server);

			$res = $this->db->customQuery("select * from queue WHERE (queued < " . $queued_limit . " or queued is null) and server='" . $server . "' LIMIT 1000");

			$list = array();

			while ($entry = $this->db->fetchArray($res))
			{
				$list[] = $entry;
			}
			$server_list[$server] = $list;

			$this->db->closeCursor($res);
		}
		$this->db->closeCursor($server_res);

		/* add up to n entries for each server we're going to sync */
		foreach ($server_list as $server) {
			$items = array_slice($server, 0, $num_per_server);
			$counter = 0;
			foreach ($items as $entry) {
				$label = "{$entry['server']}:$counter";
				$handle = curl_init();
				$ch[$label] = $handle;
				$counter++;
				$this->log(LOG_INFO, "server=" . $entry['server'] . ", server_nonce=" . $entry['server_nonce'] . ", info=" . $entry['info']);

				$url = $this->buildSyncUrl($entry);

				$curlopts[CURLOPT_PRIVATE] = $label;

				curl_settings($this, 'YK-VAL resync', $handle, $url, $timeout, $curlopts);
				$entries[$label] = $entry;
				curl_multi_add_handle($mh, $handle);
				$handles++;
			}
			$empty = array();
			array_splice($server, 0, $num_per_server, $empty);
			if(count($server) == 0) {
				unset($server_list[$entry['server']]);
			}
		}

		while($handles > 0) {
			while (curl_multi_exec($mh, $active) == CURLM_CALL_MULTI_PERFORM);

			while ($info = curl_multi_info_read($mh)) {
				$handle = $info['handle'];
				$server = strtok(curl_getinfo($handle, CURLINFO_EFFECTIVE_URL), "?");
				$label = curl_getinfo($handle, CURLINFO_PRIVATE);
				$entry = $entries[$label];
				$this->log(LOG_DEBUG, "handle indicated to be for $server.");
				curl_multi_remove_handle($mh, $handle);
				$handles--;
				if ($info['result'] === CURLE_OK) {
					$response = curl_multi_getcontent($handle);
					if (preg_match('/status=OK/', $response))
					{
						$resParams = $this->parseParamsFromMultiLineString($response);
						$this->log(LOG_DEBUG, 'response contains ', $resParams);

						/* Update database counters */
						$this->updateDbCounters($resParams);

						/* Retrieve info from entry info string */

						/* This is the counter values we had in our database *before* processing the current OTP. */
						$validationParams = $this->localParamsFromInfoString($entry['info']);

						/* This is the data from the current OTP. */
						$otpParams = $this->otpParamsFromInfoString($entry['info']);

						/* Fetch current information from our database */
						$localParams = $this->getLocalParams($otpParams['yk_publicname']);

						$this->log(LOG_DEBUG, 'validation params: ', $validationParams);
						$this->log(LOG_DEBUG, 'OTP params: ', $otpParams);

						/* Check for warnings  */

						if ($this->countersHigherThan($validationParams, $resParams))
						{
							$this->log(LOG_NOTICE, 'Remote server out of sync compared to counters at validation request time. ');
						}

						if ($this->countersHigherThan($resParams, $validationParams))
						{
							if ($this->countersEqual($resParams, $otpParams))
							{
								$this->log(LOG_INFO, 'Remote server had received the current counter values already. ');
							}
							else
							{
								$this->log(LOG_NOTICE, 'Local server out of sync compared to counters at validation request time. ');
							}
						}

						if ($this->countersHigherThan($localParams, $resParams))
						{
							$this->log(LOG_WARNING, 'Remote server out of sync compared to current local counters.  ');
						}

						if ($this->countersHigherThan($resParams, $localParams))
						{
							$this->log(LOG_WARNING, 'Local server out of sync compared to current local counters. Local server updated. ');
						}

						if ($this->countersHigherThan($resParams, $otpParams))
						{
							$this->log(LOG_ERR, 'Remote server has higher counters than OTP. This response would have marked the OTP as invalid. ');
						}
						elseif ($this->countersEqual($resParams, $otpParams) && $resParams['nonce'] != $otpParams['nonce'])
						{
							$this->log(LOG_ERR, 'Remote server has equal counters as OTP and nonce differs. This response would have marked the OTP as invalid.');
						}

						/* Deletion */
						$this->log(LOG_DEBUG, 'deleting queue entry with modified=' . $entry['modified'] .
							' server_nonce=' . $entry['server_nonce'] .
							' server=' . $entry['server']);

						$this->db->deleteByMultiple('queue', array(
							'modified' => $entry['modified'],
							'server_nonce' => $entry['server_nonce'],
							'server' => $entry['server']
						));

					}
					else if (preg_match('/status=BAD_OTP/', $response))
					{
						$this->log(LOG_WARNING, 'Remote server says BAD_OTP, pointless to try again, removing from queue.');
						$this->db->deleteByMultiple('queue', array(
							'modified' => $entry['modified'],
							'server_nonce' => $entry['server_nonce'],
							'server' => $entry['server']
						));
					}
					else
					{
						$this->log(LOG_ERR, 'Remote server refused our sync request. Check remote server logs.');
					}

					if (array_key_exists($server, $server_list)) {
						$entry = array_shift($server_list[$server]);
						if(count($server_list[$server]) == 0) {
							$this->log(LOG_DEBUG, "All entries for $server synced.");
							unset($server_list[$server]);
						}
						$this->log(LOG_INFO, "server=" . $entry['server'] . ", server_nonce=" . $entry['server_nonce'] . ", info=" . $entry['info']);

						$url = $this->buildSyncUrl($entry);

						$curlopts[CURLOPT_PRIVATE] = $label;
						curl_settings($this, 'YK-VAL resync', $handle, $url, $timeout, $curlopts);
						$entries[$label] = $entry;
						curl_multi_add_handle($mh, $handle);
						$handles++;
					}
				} else {
					$this->log(LOG_NOTICE, 'Timeout. Stopping queue resync for server ' . $entry['server']);
					unset($server_list[$server]);
				}
			}
		}

		foreach ($ch as $handle) {
			curl_close($handle);
		}

		curl_multi_close($mh);

		return true;
	}

	// blocks verify requests
	public function sync($ans_req, $timeout=1)
	{
		// construct URLs
		$urls = array();
		$res = $this->db->findByMultiple('queue', array(
			'modified'     => $this->otpParams['modified'],
			'server_nonce' => $this->server_nonce
		));
		foreach ($res as $row)
		{
			$urls[] = $this->buildSyncUrl($row);
		}

		// send out requests
		$ans_arr = retrieveURLasync('YK-VAL sync', $urls, $this->myLog, $ans_req, $match='status=OK', $returl=true, $timeout, $this->curlopts);

		if ($ans_arr === false)
		{
			$this->log(LOG_WARNING, 'No responses from validation server pool');
			$ans_arr = array();
		}

		// parse responses
		$localParams = $this->localParams;

		$this->answers = count($ans_arr);
		$this->valid_answers = 0;

		foreach ($ans_arr as $answer)
		{
			// parse out parameters from each response
			$resParams=$this->parseParamsFromMultiLineString($answer);
			$this->log(LOG_DEBUG, 'local db contains ', $localParams);
			$this->log(LOG_DEBUG, 'response contains ', $resParams);
			$this->log(LOG_DEBUG, 'OTP contains ', $this->otpParams);

			// update internal DB (conditional)
			$this->updateDbCounters($resParams);

			/**
			 * Check for warnings
			 *
			 * See https://developers.yubico.com/yubikey-val/doc/ServerReplicationProtocol.html
			 *
			 * NOTE: We use localParams for validationParams comparison since they are actually the
			 *	same in this situation and we have them at hand.
			 */

			if ($this->countersHigherThan($localParams, $resParams))
			{
				$this->log(LOG_NOTICE, 'Remote server out of sync');
			}

			if ($this->countersHigherThan($resParams, $localParams))
			{
				$this->log(LOG_NOTICE, 'Local server out of sync');
			}

			if ($this->countersEqual($resParams, $localParams) && $resParams['nonce'] != $localParams['nonce'])
			{
				$this->log(LOG_NOTICE, 'Servers out of sync. Nonce differs. ');
			}

			if ($this->countersEqual($resParams, $localParams) && $resParams['modified'] != $localParams['modified'])
			{
				$this->log(LOG_NOTICE, 'Servers out of sync. Modified differs. ');
			}

			if ($this->countersHigherThan($resParams, $this->otpParams))
			{
				$this->log(LOG_WARNING, 'OTP is replayed. Sync response counters higher than OTP counters.');
			}
			elseif ($this->countersEqual($resParams, $this->otpParams) && $resParams['nonce'] != $this->otpParams['nonce'])
			{
				$this->log(LOG_WARNING, 'OTP is replayed. Sync response counters equal to OTP counters and nonce differs.');
			}
			else
			{
				// the answer is ok since a REPLAY was not indicated
				$this->valid_answers++;
			}

			// delete entry from table
			$this->deleteQueueEntry($answer);
		}

		/**
		 * NULL queued_time for remaining entries in queue, to allow
		 *	daemon to take care of them as soon as possible.
		 */
		$this->db->updateBy('queue', 'server_nonce', $this->server_nonce, array('queued'=>null));

		/**
		 * Return true if valid answers equals required answers.
		 *	Since we only obtain the required amount of answers from
		 *	retrieveAsync this indicates that all answers were actually valid.
		 *	Otherwise, return false.
		 */
		if ($this->valid_answers == $ans_req)
			return true;

		return false;
	}

	private function createInfoString($otpParams, $localParams)
	{
		# FIXME &local_counter
		return 'yk_publicname=' . $otpParams['yk_publicname'] .
			'&yk_counter=' . $otpParams['yk_counter'] .
			'&yk_use=' . $otpParams['yk_use'] .
			'&yk_high=' . $otpParams['yk_high'] .
			'&yk_low=' . $otpParams['yk_low'] .
			'&nonce=' . $otpParams['nonce'] .
			',local_counter=' . $localParams['yk_counter'] .
			'&local_use=' . $localParams['yk_use'];
	}

	private function otpParamsFromInfoString($info)
	{
		$out = explode(',', $info);
		parse_str($out[0], $params);
		return $params;
	}

	private function otpPartFromInfoString($info)
	{
		$out = explode(',', $info);
		return $out[0];
	}

	private function localParamsFromInfoString($info)
	{
		$out = explode(',', $info);
		parse_str($out[1], $params);

		return array(
			'yk_counter' => $params['local_counter'],
			'yk_use' => $params['local_use']
		);
	}

	private function parseParamsFromMultiLineString($str)
	{
		$i = preg_match("/^modified=(-1|[0-9]+)/m", $str, $out);
		if ($i != 1) {
			$this->log(LOG_ALERT, "cannot parse modified value: $str");
		}
		$resParams['modified']=$out[1];

		$i = preg_match("/^yk_publicname=([cbdefghijklnrtuv]+)/m", $str, $out);
		if ($i != 1) {
			$this->log(LOG_ALERT, "cannot parse publicname value: $str");
		}
		$resParams['yk_publicname']=$out[1];

		$i = preg_match("/^yk_counter=(-1|[0-9]+)/m", $str, $out);
		if ($i != 1) {
			$this->log(LOG_ALERT, "cannot parse counter value: $str");
		}
		$resParams['yk_counter']=$out[1];

		$i = preg_match("/^yk_use=(-1|[0-9]+)/m", $str, $out);
		if ($i != 1) {
			$this->log(LOG_ALERT, "cannot parse use value: $str");
		}
		$resParams['yk_use']=$out[1];

		$i = preg_match("/^yk_high=(-1|[0-9]+)/m", $str, $out);
		if ($i != 1) {
			$this->log(LOG_ALERT, "cannot parse high value: $str");
		}
		$resParams['yk_high']=$out[1];

		$i = preg_match("/^yk_low=(-1|[0-9]+)/m", $str, $out);
		if ($i != 1) {
			$this->log(LOG_ALERT, "cannot parse low value: $str");
		}
		$resParams['yk_low']=$out[1];

		$i = preg_match("/^nonce=([[:alnum:]]+)/m", $str, $out);
		if ($i != 1) {
			$this->log(LOG_ALERT, "cannot parse nonce value: $str");
		}
		$resParams['nonce']=$out[1];

		return $resParams;
	}

	private function deleteQueueEntry($answer)
	{
		preg_match('/url=(.*)\?/', $answer, $out);
		$server = $out[1];

		$this->log(LOG_INFO, "deleting server=" . $server .
				" modified=" . $this->otpParams['modified'] .
				" server_nonce=" . $this->server_nonce);

		$this->db->deleteByMultiple('queue', array(
			'modified' => $this->otpParams['modified'],
			'server_nonce' => $this->server_nonce,
			'server' => $server
		));
	}

	private function buildSyncUrl($entry)
	{
		return $entry['server'] .
			"?otp=" . $entry['otp'] .
			"&modified=" . $entry['modified'] .
			"&" . $this->otpPartFromInfoString($entry['info']);
	}
}
