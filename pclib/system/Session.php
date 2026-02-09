<?php
/**
 * @file
 * PHP Sessions wrapper.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link https://pclib.brambor.net/
 * @license MIT (https://opensource.org/licenses/MIT)
 */

namespace pclib\system;
use RuntimeException;

class Session
{

public $autoStart = false;
public $id;
protected $options;

function __construct()
{
  $isHttps =
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

	$this->options = [
    // Cookies
    'cookie_httponly' => true,    // JS nemůže číst session cookie
    'cookie_secure'  => $isHttps, // cookie se posílá jen přes HTTPS
    'cookie_samesite'=> 'Lax',    // ochrana proti CSRF

    // Session chování
    'use_strict_mode'=> true,   // odmítne cizí session ID
    'use_only_cookies'=> true,  // zakáže SID v URL
  ];
}

/*
 * Setup session options ie. session_start() parameters.
 */
public function setOptions(array $options)
{
  if (session_id()) throw new RuntimeException("Cannot be set. Session is already initialized.");  
  $this->options = $options + $this->options;
}

/*
 * Set session lifetime in seconds.
 */
public function setLifeTime($seconds)
{
  if (session_id()) throw new RuntimeException("Cannot be set. Session is already initialized.");
  ini_set('session.gc_maxlifetime', $seconds);  
}

/*
 * Start session with security presets.
 */
public function start()
{
	session_start($this->options);
  $this->id = session_id();

  if (!$this->id) {
    throw new RuntimeException('Session initialization failed.');
  }
}

/*
 * Get session variable - you can use dot notation 'group.variable' for organization.
 */
public function get($key, $default = null)
{
	if (!session_id()) {
		if ($this->autoStart) $this->start();
		else throw new RuntimeException('Session is not initialized.');
	}

  $segments = explode('.', $key);
  $value = $_SESSION;

  foreach ($segments as $segment) {
      if (!is_array($value) || !array_key_exists($segment, $value)) {
          return $default;
      }
      $value = $value[$segment];
  }

  return $value;
}

/*
 * Set session variable - you can use dot notation 'group.variable' for organization.
 */
public function set($key, $value)
{
	if (!session_id()) {
		if ($this->autoStart) $this->start();
		else throw new RuntimeException('Session is not initialized.');
	}

  $segments = explode('.', $key);
  $ref =& $_SESSION;

  foreach ($segments as $segment) {
      if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
          $ref[$segment] = [];
      }
      $ref =& $ref[$segment];
  }

  $ref = $value;
}

/*
 * Delete session variable.
 */
public function delete($key)
{
  $segments = explode('.', $key);
  $last = array_pop($segments);

  $ref =& $_SESSION;

  foreach ($segments as $segment) {
      if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
          return; // cesta neexistuje
      }
      $ref =& $ref[$segment];
  }

  unset($ref[$last]);
}

/*
 * Destroy session including session cookie.
 */
public function destroy()
{
  session_start();
  session_unset();
  session_destroy();

  if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(
          session_name(),
          '',
          time() - 42000,
          $params['path'],
          $params['domain'],
          $params['secure'],
          $params['httponly']
      );
  }
}

}

 ?>