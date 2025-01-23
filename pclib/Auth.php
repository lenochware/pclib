<?php 
/**
 * @file
 * Authentication and authorization.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link https://pclib.brambor.net/
 * @license MIT (https://opensource.org/licenses/MIT)
 */

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

/** Check if remote address changed. */
public $verifyRemote = true;

/** Delete plain-text default password from database on first login. */
public $cleanDefaultPassword = true;

/** var AuthUser User which is logged in. */
public $loggedUser;

/**
 * Take \b $user and log him in. See also #$loggedUser.
 * @param AuthUser $user
 */
function setLoggedUser(pclib\AuthUser $user = null)
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
		$this->log('auth/fail', 'auth/ip-address-changed');
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
 * Reload logged user from database.
 * Use if you need propagate changes immediately.
 * @return AuthUser $user
 */
function reloadLoggedUser()
{
	if (!$this->loggedUser) return;
	$name = $this->loggedUser->values['USERNAME'];
	$user = $this->getUser($name);

	$this->setLoggedUser($user->isValid()? $user : null);
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

	$cfg = $this->app->config;
	$this->setOptions($cfg['service.auth'] ?? $cfg['pclib.auth']);

	$this->loggedUser = $this->getSessionUser();
	if ($db) $this->getStorage()->db = $db;
}

/*
 * Setup this service from configuration file.
 */
public function setOptions(array $options)
{
	$this->passwordAlgo = $options['algo'];
	$this->secret = $options['secret'];	
	$this->realm = $options['realm'] ?: $this->app->name;
	if (isset($options['dsn'])) $this->getStorage()->db = new pclib\Db($options['dsn']);
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
	if (!is_string($userName) or !is_string($password)) {
		throw new AuthException("Invalid username or password.");
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

		if ($this->cleanDefaultPassword and $user->hasDefaultPassword()) {
			$user->changePassword($password);
		}

		$user->values['LAST_LOGIN'] = date('Y-m-d H:i:s');
		$this->getStorage()->setUser($user);
		$this->trigger('auth.login', ['user' => $user]);
	}
	else {
		http_response_code(401);
		$this->setError($result);
		if ($user) {
			$this->log('auth/fail', $result, null, $user->values['ID']);
		}
		else {
			$this->log('auth/fail', $result, "Failed login of user '$userName'");
		}
	}

	return ($result == 'LOGIN_OK');
}

/**
 * Logout active user.
 */
function logout()
{
	$this->trigger('auth.logout', ['user' => $this->loggedUser]);
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
		$this->log('auth/fail', 'auth/invalid-session');
		$this->logout();
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
	else {
		$data = null;
	}
	
	$this->app->setSession('pclib.user', $data, $this->realm);
}

protected function sessionHash($data)
{
	$remoteAddr = $this->verifyRemote? $_SERVER['REMOTE_ADDR'] : '';
	return md5(
		 $remoteAddr    	//We fight against session stealing
		 .$this->realm
		 .$data['ID']     //Forbid changing user or role
		 .implode(',', $data['roles'])
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

	http_response_code(403);
	$message = "Required permission '$name'. Access denied.";
	$this->log('auth/fail', 'auth/unauthorized-access', $message);
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