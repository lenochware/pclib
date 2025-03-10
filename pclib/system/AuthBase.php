<?php
/**
 * @file
 * Base class for most classes of authorization system.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link https://pclib.brambor.net/
 * @license MIT (https://opensource.org/licenses/MIT)
 */

namespace pclib\system;
use pclib\AuthException;

/**
 * Base class for most classes of authorization system.
 */
class AuthBase extends BaseObject
{

/** var App */
protected $app;

/** Array of error messages (if any) */
public $errors = array();

/** Secret string used for enpowerment of md5 hash */
public $secret;

/** Password algorhitm - can be 'md5', 'bcrypt', 'bcrypt-md5' */
public $passwordAlgo;

/** Bcrypt cost. */
public $passwordCost = 10;

/** Throws exceptions instead of just collecting errors in ->errors */
public $throwsExceptions = false;

/**
 * Constructor - load config parameters.
 */
function __construct()
{
	global $pclib;
	
	parent::__construct();
	$this->app = $pclib->app;
}


/**
 * Return password hash.
 * @param string $password
 * @return string $hash
**/
function passwordHash($password)
{
	switch ($this->passwordAlgo) {
		case 'md5': 
			return md5($this->secret.$password);
		case 'bcrypt': 
			return password_hash($password , PASSWORD_BCRYPT, array('cost' => $this->passwordCost));
		case 'bcrypt-md5': 
			return password_hash(md5($this->secret.$password) , PASSWORD_BCRYPT, array('cost' => $this->passwordCost));
		default:
			throw new AuthException('Unknown password-hash algorihtm');
	}	
}

/**
 * Verify password hash.
 * @param string $password
 * @param string $hash
 * @return bool $valid
**/
function passwordHashVerify($password, $hash)
{
	switch ($this->passwordAlgo) {
		case 'md5': 
			return (md5($this->secret.$password) == $hash);		
		case 'bcrypt': 
			return password_verify($password, $hash);
		case 'bcrypt-md5': 
			return password_verify(md5($this->secret.$password), $hash);
		default:
			throw new AuthException('Unknown password-hash algorihtm');
	}
}

/** log security issue using App->logger. */
protected function log($category, $messageId, $message = null, $itemId = null)
{
	$this->app->log($category, $messageId, $message, $itemId);
}

/**
 * Add error message into ->errors variable.
 * @param string $message Message with %s placeholders
**/
function setError($message)
{
	$args = array_slice(func_get_args(), 1) ;
	$message = $this->app->text($message, $args);

	if ($this->throwsExceptions) {
		throw new AuthException($message);
	}
	else {
		$this->errors[] = $message;
	}
}

}