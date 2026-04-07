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

/**
 * Get or set application session variables.
 * Example: $key = $app->session->get('some.key'); $app->session->set('some.key', 'value');
 */
class Session
{

/** Start session at first use. */
public $autoStart = false;

/** Session id. */
public $id;

protected $options = [];
protected $section;

/**
 * Create sesion object.
 * @param string $section All variables will be stored in $_SESSION[$section].
 */
function __construct($section = '')
{
  $this->section = $section;
}

/*
 * Setup session options ie. session_start() parameters.
 * @param array $options See session_start()
 */
public function setOptions(array $options)
{
  if (session_id()) throw new RuntimeException("Cannot be set. Session is already initialized.");  
  $this->options = $options;
}

/*
 * Set session lifetime in seconds.
 * @param int $seconds
 */
public function setLifeTime($seconds)
{
  if (session_id()) throw new RuntimeException("Cannot be set. Session is already initialized.");
  ini_set('session.gc_maxlifetime', $seconds);  
}

/*
 * Start session with security aware presets.
 */
public function start()
{
  $isHttps =
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

  $defaults = [
    // Cookies
    'cookie_httponly' => true,    // JS nemůže číst session cookie
    'cookie_secure'  => $isHttps, // cookie se posílá jen přes HTTPS
    'cookie_samesite'=> 'Lax',    // ochrana proti CSRF

    // Session chování
    'use_strict_mode'=> true,   // odmítne cizí session ID
    'use_only_cookies'=> true,  // zakáže SID v URL
  ];

  session_start($this->options + $defaults);
  $this->id = session_id();

  if (!$this->id) {
    throw new RuntimeException('Session initialization failed.');
  }
}

/*
 * Get session variable - you can use dot notation 'group.variable'.
 * Throws exception when session is not initialized and $default is not set.
 * @param string $key Variable name 
 * @param mixed $default Default value
 * @return $value
 */
public function get($key, $default = null)
{
  if (!session_id()) {
    if ($this->autoStart) $this->start();
    elseif(isset($default)) return $default;
    else throw new RuntimeException('Session is not initialized.');
  }

  if ($this->section) $key = $this->section . '.' . $key;

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
 * Set session variable - you can use dot notation 'group.variable'.
 * Throws exception when session is not initialized. 
 * @param string $key Variable name  
 * @param mixed $value Variable value  
 */
public function set($key, $value)
{
  if (!session_id()) {
    if ($this->autoStart) $this->start();
    else throw new RuntimeException('Session is not initialized.');
  }

  if ($this->section) $key = $this->section . '.' . $key;

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
 * @param string $key Variable name  
 */
public function delete($key = null)
{
  if (empty($key)) {
    if ($this->section) unset($_SESSION[$this->section]);
    else $_SESSION = [];
    return;
  }

  if ($this->section) $key = $this->section . '.' . $key;

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
 * Destroy whole session including session cookie.
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