<?php
/**
 * @file
 * Logger class
 *
 * @author -dk- <lenochware@gmail.com>
 * @link http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib;
use pclib;
use pclib\system\storage\LoggerDbStorage;

/**
 * This class offers fast and space-saving database logger.
 * You can store any program events (actions) alltogether with user_id,
 * item_id, browser, ip-address or even with additional message.
 * Only numerical ID are stored into LOG so it is very compact.
 * The lookup table which contains text labels bounded with IDs is
 * updated automatically.
 */

class Logger extends system\BaseObject implements IService
{

/** Name of the logger */
public $name = 'LOGGER';

/** var App */
protected $app;

/** var LoggerDbStorage */
public $storage;

/** var Auth */
public $auth;

/**
 * @param $name Logger name (Same as application name by default)
 */
function __construct($name = null)
{
	global $pclib;
	parent::__construct();

	$this->app = $pclib->app;

	$options = $this->app->config['pclib.logger'] ?? [];
	if ($options) $this->setOptions($options);

	if (isset($name)) $this->name = $name;
}

/*
 * Setup this service from configuration file.
 */
public function setOptions(array $options)
{
	$this->name = $options['name'];	
}

/** Return storage object - if not exists, create one. */
protected function getStorage()
{
	if (!$this->storage) $this->storage = new LoggerDbStorage($this);
	return $this->storage;
}

/**
 * Store message to the log.
 * @param string $category Message category such as AUTH_WARNING or PHP_ERROR.
 * @param string $message_id For logging repeated events such as 'user/delete'
 * @param string $message Fill it if you need log full text message
 * @param int $item_id Additional id of related object
 * @return $id
 */
function log($category, $message_id, $message = null, $item_id = null)
{
	$ua = $this->app->request->userAgent;

	$message = array(
		'LOGGER' => $this->name,
		'CATEGORY' => $category,
		'ACTION'  => $message_id,
		'MESSAGE' => $message,
		'ITEM_ID' => $item_id,
		'IP'       => ip2long($this->app->request->clientIp),
		'UA'       => $ua[0] . ' ' . $ua[1],
		'DT'       => date("Y-m-d H:i:s"),
	);

	if ($this->service('auth', false)) {
		$user = $this->auth->getUser();
		if ($user) {
			$data = $user->getValues();
			$message['USER_ID'] = $data['ID'];
		}
	}

	$this->trigger('logger.log', $message);

	return $this->getStorage()->log($message);
}

/**
 * Return last $rowcount records from the log.
 * @param int $rowCount Number of rows
 * @param array $filter Set filter on USER,ACTION,LOGGER. Ex: array('USER'=>'joe')
 * @return array Array of records
 */
function getLog($rowCount, array $filter = null)
{
	return $this->getStorage()->getLog($rowCount, $filter);
}

/**
 * Return size of the log in MB.
 * @return int $size
 */
function getSize()
{
	return $this->getStorage()->getSize();
}

/**
 * Remove old records in log. It will keep $keepdays in the log.
 * @param int $keepDays Number of days to keep in the log
 * @param bool $allLogs This logger only / all loggers
 */
function deleteLog($keepDays, $allLogs = false)
{
	return $this->getStorage()->delete($keepDays, $allLogs);
}

}