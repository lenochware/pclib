<?php 
/**
 * @file
 * Authentication and authorization.
 *
 * @author -dk- <lenochware@gmail.com>
 * http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib;
use pclib;
use pclib\system\storage\AuthDbStorage;

/**
 * Provides authentication and authorization support.
 * Features:
 * - Unlimited number of users.
 * - Each user has assigned roles, each role has permissions.
 * - Exceptions for single users, default passwords, logging security issues and more.
 * @see AuthManager User account management.
 * @note
 * This class requires enabled sessions.
 */
class Auth extends system\AuthBase implements IService
{

/** var AuthDbStorage */
public $storage;

/** Apps with the same realm share authentization session. */
public $realm;

/** var AuthUser User which is logged in. */
public $loggedUser;

/** Occurs after login. */
public $onAfterLogin;

/** Occurs before logout. */
public $onBeforeLogout;

/**
 * Take \b $user and log him in. See also #$loggedUser.
 * @param AuthUser $user
 */
function setLoggedUser(pclib\AuthUser $user)
{
	$this->loggedUser = $user;
	$this->setSessionUser($user);
}

/**
 * Get user ip-address, check if changed and log notice if so.
 */
protected function getUserIp(pclib\AuthUser $user)
{
	$ip = ip2long($this->app->request->getClientIp());
	if ($ip != $user->values['IP']) {
		$this->log('AUTH_NOTICE', 'Access from different ip-address.', $user->values['ID']);
	}
	return $ip;
}

/**
 * Without parameter it returns logged user, user $userName otherwise.
 * @param string $userName
 * @return AuthUser $user
 */
function getUser($userName = null)
{
	if (!$userName) return $this->loggedUser;
	$user = $this->getStorage()->getUser($userName);
	if ($user) $user->auth = $this;
	return $user;
}

/**
 * Check if user $userName exists.
 * @param string $userName
 * @return bool $yes
 */
function exists($userName)
{
	return (bool)$this->getUser($userName);
}

function __construct(Db $db = null)
{
	parent::__construct();

	if (!session_id()) throw new RuntimeException('Session is not initialized. Perhaps missing session_start()?');

	$this->realm = $this->app->config['pclib.auth']['realm'] ?: $this->app->name;
	$this->loggedUser = $this->getSessionUser();
	if ($db) $this->getStorage()->db = $db;
}

/** Return storage object - if not exists, create one. */
function getStorage()
{
	if (!$this->storage) $this->storage = new AuthDbStorage;
	return $this->storage;	
}

/**
 * Authenticate user \b $userName with password \b $password. If user passed,
 * log him in.
 *
 * @param string $userName
 * @param string $password
 * @return bool $success
 */
function login($userName, $password)
{
	if (!$userName or !is_string($userName)) {
		throw new AuthException("Invalid username: '%s'", $userName);
	}

	$user = $this->getUser($userName);

	$result = 'LOGIN_OK';

	if (!$user) $result = 'User does not exists!';
	else {
		if (!$user->isValid()) $result = 'Invalid user!';
		if (!$user->passwordVerify($password)) $result = 'Invalid password!';	
	}
	
	if ($result == 'LOGIN_OK') {
		$this->setLoggedUser($user);
		$user->values['IP'] = $this->getUserIp($user);
		$user->values['LAST_LOGIN'] = date('Y-m-d H:i:s');
		$this->getStorage()->setUser($user);
		$this->onAfterLogin($user);
	}
	else {
		$this->setError($result);
		if ($user) {
			$this->log('AUTH_NOTICE', $result, $user->values['ID']);
		}
		else {
			$this->log('AUTH_NOTICE', $result, null, "Failed login of user '$userName'");
		}
	}

	return ($result == 'LOGIN_OK');
}

/**
 * Logout active user.
 */
function logout()
{
	$this->onBeforeLogout($this->loggedUser);
	$this->loggedUser = null;
	$this->setSessionUser(null);
}

/** 
 * Load user from session storage, check session validity. 
 * @return AuthUser $user;
 */
protected function getSessionUser()
{
	$data = $this->app->getSession('pclib.user', $this->realm);
	if (!$data) return null;

	if ($data['sessionHash'] != $this->sessionHash($data)) {
		$this->log('AUTH_ERROR', 'Authentication failed - invalid session.', $data['ID']);
		throw new AuthException("Authentication failed. Access denied.");
	}

	$user = new AuthUser;
	$user->values = $data;
	$user->auth = $this;
	return $user;
}

/** 
 * Store user to session. 
 * @param AuthUser $user; 
 */
protected function setSessionUser(pclib\AuthUser $user = null)
{
	if ($user) {
		$data = $user->values;
		$data['sessionHash'] = $this->sessionHash($data);
	}
	
	$this->app->setSession('pclib.user', $data, $this->realm);
}

protected function sessionHash($data)
{
	 return md5(
		 $_SERVER['REMOTE_ADDR']    //We fight against session stealing
		 .$this->realm
		 .$data['ID']               //Forbid changing user or role
		 .$data['ROLES']
		 .$this->secret
		 );
}

/**
 * Check if logged user has permission $name.
 * If not, throw exception and log security issue.
 * @param string $name Name of permission.
 * @param int $objectId resource object id
 */
function testRight($name, $objectId = 0)
{
	if ($this->loggedUser and $this->loggedUser->hasRight($name, $objectId)) {
		return true;
	}

	$message = "Required permission '$name'. Access denied.";

	$this->log('AUTH_ERROR', 'Unauthorized access!', null, $message);
	throw new AuthException($message);
}

/**
 * Check if someone is logged in.
 */
function isLogged()
{
	return $this->loggedUser? $this->loggedUser->isLogged() : false;
}

/**
 * Check if logged user has permission $name.
 * @param string $name
 * @return bool $yes
 */
function hasRight($name, $objectId = 0)
{
	return $this->loggedUser? $this->loggedUser->hasRight($name, $objectId) : false;
}

/**
 * Check if logged user has role $role.
 * @param string $role
 * @return bool $yes
 */
function hasRole($role)
{
	return $this->loggedUser? $this->loggedUser->hasRole($role) : false;
}

}

?>