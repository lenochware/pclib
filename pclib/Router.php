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

	/** Occurs after Action is created from HTTP request and before dispatch. */ 
	public $onGetAction;

	/** Occurs when url (link) is created from Action. */ 
	public $onCreateUrl;

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
		$action->method = $_REQUEST['pcl_form_submit'];
	}

	$this->onGetAction($action);
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
 * Transform internal action (for example 'products/edit/id:1') to URL.
 * @param string|Action $s
 * @return string $url
 */
function createUrl($s)
{
	$action = is_string($s)? new Action($s) : $s;
	//TODO: test instanceof Action

	$this->onCreateUrl($action);

	if (!$action->controller) return $this->baseUrl;

	if ($this->friendlyUrl) {
		$params = $action->params;
		return $this->baseUrl.$action->path.($params? '?'.$this->buildQuery($params) : '');
	} else {
		$params = array('r' => $action->path) + $action->params;
		return $this->baseUrl.'?'.$this->buildQuery($params);
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
	/** Complete path such as 'products/edit', 'admin/products/edit'. */
	public $path;

	/** Name of the module which owns controller. */
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

	/**
	 * Convert route to string.
	 * @return string $route
	 */
	function toString()
	{
		$params = array();
		foreach ($this->params as $key => $value) {
			$params[] = $key.':'.$value;
		}
		return $this->path.($params? '/'.implode('/',$params) : '');
	}

	function __toString() {
		return $this->toString();
	}

	/**
	 * Create new Action object from the string.
	 * @param string $s string-route - example: 'orders/edit/id:1'
	 */
	function fromString($s)
	{
		$ra = explode('/', $this->replaceParams($s, null));

		$params = $path = array();

		foreach($ra as $section) {
			if ($section == '__GET__') { $params += $_GET; continue; }
			@list($name,$value) = explode(':', $section);
			if (isset($value)) $params[$name] = $value;
			else $path[] = $section;
		}

		$this->path = implode('/', $path);
		$this->params = $params;

		$n = count($path);
		if ($n >= 3) {
			$path = array_slice($path, -3);
			$this->module = array_shift($path);
		}
		$this->controller = $path[0];
		$this->method = array_get($path, 1);
	}

	/**
	 * Create new Action object from $_GET array.
	 * @param array $get
	 */
	function fromArray($get)
	{
		$this->path = array_get($get, 'r', '');
		$path = explode('/', $this->path);
		$n = count($path);

		if ($n >= 3) {
			$path = array_slice($path, -3);
			$this->module = array_shift($path);
		}
		$this->controller = $path[0];
		$this->method = isset($path[1])? $path[1] : '';
				
		unset($get['r']);
		$this->params = $get;
	}

	protected function replaceParams($s, $params)
	{
		preg_match_all("/{([a-z0-9_.]+)}/i", $s, $found);
		
		if ($found[0]) {
			$values = array();
			foreach ($found[1] as $name) {
				if ($name == 'GET') $value = '__GET__';
				elseif (substr($name,0,4) == 'GET.') $value = $_GET[substr($name,4)];
				elseif($params) $value = $params[$name]; 
				else $value = '';
				$values[] = $value;
			}
			return str_replace($found[0], $values, $s);
		}
		else {
			return $s;
		}
	}
} //Action
