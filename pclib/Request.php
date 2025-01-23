<?php
/**
 * @file
 * Http-Request class
 *
 * @author -dk- <lenochware@gmail.com>
 * @link https://pclib.brambor.net/
 * @license MIT (https://opensource.org/licenses/MIT)
 */

namespace pclib;
use pclib;

/**
 * Provides unified access to HTTP request.
 * Can detect request method, url, user agent, request headers and such.
 * For any getXxx() method, you can use shortcut ->xxx - e.g. print $app->request->url;
 */
class Request extends system\BaseObject implements IService
{

protected $userAgents = array('Chrome', 'Safari', 'Opera', 'Firefox', 'MSIE');

protected $headers;

/** Return request method. 'POST','GET' etc. */
function getMethod()
{
	return $_SERVER['REQUEST_METHOD'];
}

/** Is current request AJAX request? */
function isAjax()
{
	return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) and $_SERVER['HTTP_X_REQUESTED_WITH']=='XMLHttpRequest');
}

/** We have HTTPS? */
function isSSL()
{
	return (!empty($_SERVER['HTTPS']) and strtolower($_SERVER['HTTPS']) != 'off');
}

/** Return http host. */
function getHost()
{
	return $_SERVER['HTTP_HOST'];
}

/** Return request port. */
function getPort()
{
	return $_SERVER['SERVER_PORT'];
}

/** Return current url. */
function getUrl()
{
	$scheme = $this->isSSL()? 'https://' : 'http://';
	return sprintf("%s%s%s", $scheme, $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']);
}

/** Return root Url. */
function getRootUrl()
{
	$scheme = $this->isSSL()? 'https://' : 'http://';
	return sprintf("%s%s%s", $scheme, $_SERVER['HTTP_HOST'], $this->getBaseUrl());	
}


/** 
 * Return client IP address. 
 * Warning: Can be faked!
 */
function getClientIp()
{
	if (!empty($_SERVER['HTTP_CLIENT_IP']))
		$ip = $_SERVER['HTTP_CLIENT_IP'];
	else if(!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	else if(!empty($_SERVER['REMOTE_ADDR']))
		$ip = $_SERVER['REMOTE_ADDR'];
	else $ip = '0.0.0.0';

	if (strpos($ip,',')) $ip = substr($ip,0,strpos($ip,','));

	return $ip;
}

/** Return server IP address (safe). */
function getServerIp()
{
	return array_key_exists('SERVER_ADDR',$_SERVER) ? $_SERVER['SERVER_ADDR'] : $_SERVER['LOCAL_ADDR'];
}

function isConsole()
{
	return (php_sapi_name() == "cli");
}

/** Return base Url. */
function getBaseUrl()
{
	return rtrim(dirname($_SERVER['PHP_SELF']),"/\\").'/';
}

function getUrlComponents()
{
	return parse_url($this->url);
}

/** Return document root. */
function getWebRoot()
{
	return $_SERVER["DOCUMENT_ROOT"];
}

/**
* Return request headers array.
* @return array headers [name => content] pairs
*/
function getHeaders()
{
	if ($this->headers) return $this->headers;

	if (function_exists('apache_request_headers'))
		$headers = array_change_key_case(apache_request_headers(), CASE_LOWER);
	else {
		$headers = array();
		foreach ($_SERVER as $k => $v) {
			if (strncmp($k, 'HTTP_', 5) == 0) {
				$k = substr($k, 5);
			} elseif (strncmp($k, 'CONTENT_', 8)) {
				continue;
			}
			$headers[ strtr(strtolower($k), '_', '-') ] = $v;
		}
	}
	$this->headers = $headers;
	return $this->headers;
}

/**
 * Return request body.
 */
function getRawBody()
{
  return file_get_contents('php://input');
}

/**
 * Match current url against pattern.
 * @param string $pattern fnmatch pattern Example: 'http://localhost/*'
 */
function urlMatch($pattern)
{
	return fnmatch($pattern, $this->url);
}

/**
 * It will try detect user agent, version and OS.
 * @return array [$os,$agent,$version]
 */
function getUserAgent()
{
	$agent = '';
	$signature = $_SERVER['HTTP_USER_AGENT'];
	foreach ($this->userAgents as $name) {
		if (stristr($signature, $name)) { $agent = $name; break; }
	}

	if (stristr($signature, ' Edg/')) $agent = 'Edge';

	if ($agent) {
		$version = substr(stristr($signature, $agent),strlen($agent)+1);
		list($major,$minor) = sscanf($version, "%d.%d");
		$version = $major.(isset($minor)? '.'.substr($minor,0,1) : '');
	}

	if (!$agent and stristr($signature, 'Mozilla')) $agent = 'Mozilla like';
	if (!$agent) $agent = '?';
	
	if (stristr($signature, 'Windows')) $os = 'Windows';
	elseif (stristr($signature, 'iPhone')) $os = 'iPhone';
	elseif (stristr($signature, 'Android')) $os = 'Android';
	elseif (stristr($signature, 'Mac OS')) $os = 'MacOS';
	elseif (stristr($signature, 'Linux')) $os = 'Linux';
	else $os = '?';

	return array($os,$agent,$version);
}

function __get($name)
{
	$getter = 'get'.$name;
	if (method_exists($this, $getter))  return $this->$getter();
	return parent::__get($name);
}

} //class
