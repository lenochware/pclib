<?php
/**
 * @file
 * Class AuthBase - PClib authentication and authorization system.
 * @author -dk-
 * http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib\system;

/**
 * @class AuthBase
 * Base class for all classes of authorization system.
 * Contains common functions and variables. Do not use directly.
 */
class AuthBase extends BaseObject
{

/** var Db */	
public $db;

/** var Translator */
public $translator;

/** var App */
protected $app;

public $realm;

/** Link to array of configuration parameters. See config.php. */
protected $config = array();

/** Array of error messages (if any) */
public $errors = array();

public $USERS_TAB = 'AUTH_USERS',
	$REGISTER_TAB = 'AUTH_REGISTER',
	$ROLES_TAB    = 'AUTH_ROLES',
	$RIGHTS_TAB   = 'AUTH_RIGHTS',
	$USERROLE_TAB = 'AUTH_USER_ROLE';


/**
 * Secret string added to passwords for increasing safety.
 * Use some kind of random string e.g. "a8XwZ21$/p".
 * @note
 * Setting this variable is required!
 */
public $secret;

/* Password hash algorithm. */
public $hashFunction;

public $defaultPasswordLength = 8;

/**
 * Constructor - load config parameters.
 */
function __construct()
{
	global $pclib;

	parent::__construct();

	if (!$pclib->app) throw new \RuntimeException('No instance of application (class app) found.');

	$this->app = $pclib->app;
	$this->config = $this->app->config;

	$this->service('db');

	$this->hashFunction = array($this, 'passwordHash');
	
	$this->realm = ifnot($this->config['pclib.auth.realm'], $this->app->name);
	$this->secret = $this->config['pclib.auth.secret'];
	if (!$this->secret) throw new NoValueException('Parameter auth->secret required.');
}

/**
 * Translate "system name" of auth entity to numeric ID.
 * Entity can be role, right or user. For system name see column SNAME
 * in AUTH_* tables - ID is primary key from relevant db-table.
 *
 * @param string $sname "entity_name" or "#entity_id"
 * @param enum $type ("user", "role", "right")
 */
function sname($sname, $type)
{
	if (substr($sname,0,1) == '#') return (int)substr($sname, 1);
	$sname = strtolower($sname);

	switch($type) {
		case 'user':
		list($id) = $this->db->select($this->USERS_TAB.':ID',
			"USERNAME='{0}'", $sname);
		break;

		case 'role':
		list($id) = $this->db->select($this->ROLES_TAB.':ID',
			"SNAME='{0}'", $sname);
		break;

		case 'right':
		list($id) = $this->db->select($this->RIGHTS_TAB.':ID',
			"SNAME='{0}'", $sname);
		break;
	}

	if (!$id) $this->setError('%s not found.', $sname);
	return (int)$id;
}

/**
 * A default hash algorihtm wrapper (md5 with auth::$secret).
 * @param Auth $o Auth object
 * @param string $password
 * @return string $md5Hash
**/
function passwordHash($password, $secret)
{
	return md5($secret.$password);
}

/**
 * Generate string for session integrity check.
 * @param string $user - current user array ( see auth::getuser() )
 * @return security hash
**/
function getSecureString($user)
{
	 return md5(
		 $_SERVER['REMOTE_ADDR']    //We fight against session stealing
		 .$this->realm
		 .$user['ID']               //Forbid changing user or role
		 .$user['ROLES']
		 .$this->secret
		 );
}

/**
 * Generate random password.
 * @return string $password
**/
function genPassw()
{
	return randomstr($this->defaultPasswordLength);
}

/**
 * Set error message into auth_base::$errors
 * @param string $message - message with %s placeholders
**/
function setError($message)
{
	$args = array_slice(func_get_args(), 1) ;
	$this->errors[] = vsprintf($this->t($message), $args);
}

protected function t($s) {
	return $this->service('translator', false)? $this->translator->translate($s) : $s;
}

}