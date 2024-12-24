<?php
/**
 * @file
 * Application router.
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

/**
 * Translates URL to instance of Action class.
 * Action contains class and method name (with parameters) which will be called.
 */
class Router extends system\BaseObject implements IService
{

	/** Create friendly URL? */
	public $friendlyUrl = false;

	public $baseUrl;

	/** var Action Current %Action */
	public $action;

	/** var index Default index page - for example 'index-dev.php' */
	public $index = '';

	public $redirects;

function __construct()
{
	parent::__construct();
	$this->baseUrl = BASE_URL;
	$this->action = $this->getAction();
}

/**
 * Create Action from current request.
 * Override for your own URL format.
 * @return Action $action
 */
function getAction()
{
	$action = new Action($_GET);

	//%form button has been pressed, set route accordingly.
	if (!empty($_REQUEST['pcl_form_submit'])) {
		$action->method = Str::filter($_REQUEST['pcl_form_submit'], "\w-");
	}

	return $action;
}

/**
 * Set route to be redirected.
 * @param string $old Old route
 * @param string $new New route
 * @param string $code HTTP status code: 302 temporary | 301 permanent
 */
function addRedirect($old, $new, $code = 302)
{
	$this->redirects[$old] = ['code' => $code, 'to' => $new];
}

/**
 * Redirect old route to new route, if it was added by addRedirect().
 */
function followRedirects()
{
	$redirect = $this->redirects[$this->action->path];
	if (!$redirect) return;

	$this->action->path = $redirect['to'];
	$url = $this->createUrl($this->action);
	header('Location: '. $url, true, $redirect['code']);
	exit;
}

/**
 * Redirect to $route or url.
 */
function redirect($route, $code = null)
{
	if ($code) http_response_code($code);

	if ($route == '/self') {
		$this->reload();
	}

	if (is_array($route)) {
		$url = $route['url'];
	}
	else {
		$url = $this->createUrl($route);
	}

	$this->trigger('router.redirect', ['url' => $url]);

	header("Location: $url");
	exit();
}

function reload()
{
	global $pclib;
	$this->redirect(['url' => $pclib->app->request->url]);
}

/**
 * Transform internal action (for example 'products/edit/id:1') to URL.
 * @param string|Action $s
 * @return string $url
 */
function createUrl($s)
{
	$action = is_string($s)? new Action($s) : $s;
	//TODO: test instanceof Action

	if (!$action->controller) return $this->baseUrl;

	if ($this->friendlyUrl) {
		$params = $action->params;
		return $this->baseUrl.$action->path.($params? '?'.$this->buildQuery($params) : '');
	} else {
		$params = array('r' => $action->path) + $action->params;
		return $this->baseUrl.$this->index.'?'.$this->buildQuery($params);
	}
}


protected function buildQuery($query_data)
{
	$trans = array('%2F'=>'/','%3A'=>':','%2C'=>',');
	return strtr(http_build_query($query_data), $trans);
}

} //Router

/**
 * It encapsulates call of the controller's action: $controller->method($params).
 * It Can be mapped to URL.
 */
class Action
{
	/** Name of the plugin module. */
	public $module;

	/** Name of the controller. */
	public $controller;

	/** Controller's method name. */
	public $method;

	/** Array of action parameters. */
	public $params;

	function __construct($s = null)
	{
		if (is_string($s)) {
			$this->fromString($s);
		}
		elseif(is_array($s)) {
			$this->fromArray($s);
		}
	}

	function getPath()
	{
		$pa = [];

		if ($this->module) $pa[] = '-'.$this->module;
		if ($this->controller) $pa[] = $this->controller;
		if ($this->method) $pa[] = $this->method;

		return implode('/', $pa);
	}

	protected function getParamsString()
	{
		$params = [];
		foreach ($this->params as $key => $value) {
			$params[] = $key.':'.$value;
		}

		return $params? '/'.implode('/',$params) : '';
	}

	/**
	 * Convert route to string.
	 * @return string $route
	 */
	function toString()
	{
		return $this->getPath().$this->getParamsString();
	}

	function __toString() {
		return $this->toString();
	}

	public function __get($name)
	{
		if ($name != 'path') {
			throw new Exception("Invalid field name: '$name'.");
		}

		return $this->getPath();
	}

	/**
	 * Create new Action object from the string.
	 * @param string $s string-route - example: 'orders/edit/id:1'
	 */
	function fromString($s)
	{
		$this->module = $this->controller = $this->method = '';

		$allowedChars = "a-z0-9_:,;@ \-\.\/";	
		$parts = $s? explode('/', preg_replace("/[^$allowedChars]/i","", $s)) : [];

		$params = [];

		foreach($parts as $part)
		{
			if (strpos($part, ':')) {
				list($name, $value) = explode(':', $part);
				$params[$name] = $value;
				continue;
			}

			if ($part == '__GET__') { $params += $_GET; continue; }

			if ($this->method) continue;
			if ($part[0] == '-') $this->module = substr($part, 1);
			elseif ($this->controller) $this->method = $part;
			else $this->controller = $part;

		}

		$this->params = $params;
	}

	/**
	 * Create new Action object from $_GET array.
	 * @param array $get
	 */
	function fromArray($get)
	{
		$this->fromString($get['r'] ?? '');	
		unset($get['r']);
		$this->params = $get;
	}

} //Action
