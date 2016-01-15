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

/**
 * Translates URL to instance of Route class.
 * Route contains class and method name (with parameters) which will be called.
 */
class Router implements IService
{
	public $friendlyUrl = false;
	public $baseUrl;

	/** var Route */
	public $currentRoute;


function __construct()
{
	$this->baseUrl = BASE_URL;
}

/**
 * Read current URL and return route. (URL -> Route translation)
 * It will set currentRoute variable of the router.
 * Override for your own URL format.
 * @return Route $route
 */
function getRoute()
{
	list($controller, $action) = explode('/', $_GET['r']);
	$params = $_GET;
	unset($params['r']);

	//%form button has been pressed, set route accordingly.
	if ($_REQUEST['pcl_form_submit']) {
		$action = $_REQUEST['pcl_form_submit'];
	}

	$this->currentRoute = new Route($controller, $action, $params);
	return $this->currentRoute;
}

/**
 * Get route and return URL. (Route -> URL translation)
 * Override for your own URL format.
 * @param Route $route
 * @return string $url
 */
function getUrl(Route $route)
{
	if (!$route->controller) return $this->baseUrl;

	$path = $route->controller.($route->action?'/'.$route->action:'');

	if ($this->friendlyUrl) {
		$params = $route->params;
		return $this->baseUrl.$path.($params? '?'.$this->buildQuery($params) : '');
	} else {
		$params = array('r' => $path) + $route->params;
		return $this->baseUrl.'?'.$this->buildQuery($params);
	}
}

/**
 * Get string-route and return URL. (Route -> URL translation)
 * @uses getUrl()
 * @param string $route
 * @return string $url
 */
function createUrl($stringRoute, $paramFunction = null)
{
	return $this->getUrl(Route::createFromString($stringRoute, $paramFunction));
}


protected function buildQuery($query_data)
{
	$trans = array('%2F'=>'/','%3A'=>':','%2C'=>',');
	return strtr(http_build_query($query_data), $trans);
}

} //Router

/**
 * It encapsulates call of the object method: $controller->action($params).
 * Can be mapped to URL. Framework will translate URL to Route and call proper method with parameters.
 */
class Route {
	//public $path; //cesta k souboru s controllerem
	public $controller;
	public $action;
	public $params;

	function __construct($controller = '', $action = '', array $params = array())
	{
		$this->controller = $controller;
		$this->action = $action;
		$this->params = $params;
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
		return $this->controller.($this->action? '/'.$this->action : '').($params? '/'.implode('/',$params) : '');

	}

	/**
	 * Create new Route object from the string.
	 * @param string $s string-route e.g. 'orders/edit/id:1'
	 * @return Route $route
	 */
	static function createFromString($s, $paramFunction = null)
	{
		$ra = explode('/', self::replaceParams($s, $paramFunction));

		$params = array();

		foreach($ra as $section) {
			if ($section == '__GET__') { $params += $_GET; continue; }
			list($name,$value) = explode(':', $section);
			if (isset($value)) $params[$name] = $value;
			else $path[] = $section;
		}

		return new Route($path[0], $path[1], $params);
	}

	protected static function replaceParams($s, $paramFunction)
	{
		preg_match_all("/{([a-z0-9_.]+)}/i", $s, $found);
		
		//dump($found);
		if ($found[0]) {
			$values = array();
			foreach ($found[1] as $name) {
				if ($name == 'GET') $value = '__GET__';
				elseif (substr($name,0,4) == 'GET.') $value = $_GET[substr($name,4)];
				elseif($paramFunction) $value = call_user_func($paramFunction, $name); 
				else $value = '';
				$values[] = $value;
			}
			return str_replace($found[0], $values, $s);
		}
		else {
			return $s;
		}
	}
} //Route
