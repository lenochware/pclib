<?php
/**
 * @file
 * Http-Request class
 *
 * @author -dk- <lenochware@gmail.com>
 * @link http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

/**
 * Provides unified access to HTTP request.
 * Can detect request method, url, user agent, request headers and such.
 */
class Request extends BaseObject implements IService
{

protected $userAgents = array('Chrome', 'Safari', 'Konqueror', 'Opera', 'Firefox', 'Netscape', 'MSIE');

protected $headers;

/** Return request method. 'POST','GET' etc. */
function getMethod()
{
	return $_SERVER['REQUEST_METHOD'];
}

/** Is current request AJAX request? */
function isAjax()
{
	return ($_SERVER['HTTP_X_REQUESTED_WITH']=='XMLHttpRequest');
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

/** Return current url. */
function getUrl()
{
	$scheme = $this->isSSL()? 'https://' : 'http://';
	return sprintf("%s%s%s", $scheme, $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']);
}

/** Return client IP address. */
function getRemoteIp()
{
	if ($_SERVER['HTTP_CLIENT_IP'])
		$ip = $_SERVER['HTTP_CLIENT_IP'];
	else if($_SERVER['HTTP_X_FORWARDED_FOR'])
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	else if($_SERVER['REMOTE_ADDR'])
		$ip = $_SERVER['REMOTE_ADDR'];
	else $ip = '127.0.0.1';

	if (strpos($ip,',')) $ip = substr($ip,0,strpos($ip,','));

	return $ip;
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

	if ($agent) {
		$version = substr(stristr($signature, $agent),strlen($agent)+1);
		list($major,$minor) = sscanf($version, "%d.%d");
		$version = $major.(isset($minor)? '.'.substr($minor,0,1) : '');
	}

	if (!$agent and stristr($signature, 'Mozilla')) $agent = 'Mozilla like';
	if (!$agent) $agent = '?';
	if (stristr($signature, 'Linux')) $os = 'Linux';
	elseif (stristr($signature, ' Mac')) $os = 'MacOS';
	elseif (stristr($signature, 'Windows')) $os = 'Windows';
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
