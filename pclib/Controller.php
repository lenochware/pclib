<?php

namespace pclib;
use pclib;

/**
 *  Base class for any application controller.
 *  Define your controller, say 'products', in file controllers/ProductsController.php
 *  like class ProductsController extends Controller.
 *  Now you can define actions such as: function editAction() { ... return 'your html'; }
 * It will be called on url '?r=products/edit'.
 * @see App::run()
 */
class Controller extends system\BaseObject
{

/** var App Link to application */
protected $app;

/** Name of the controller without postfix. */
public $name;

/** Name of the called action without postfix. */
public $action;

/** authorize() fallback when user is not logged in. */
public $authorizeRedirect = 'user/signin';

/** Convert url action some-thing into someThingAction() call. */
public $allowDashInAction = true;

/**
 * Each action method name must have following postfix.
 * Only action methods are callable by sending request from user browser.
 */
protected $ACTION_POSTFIX = 'Action';

function __construct(App $app)
{
	parent::__construct();
	$this->app = $app;
}

/**
 * Called before every action.
 * Override for controller's setup, testing access permissions, etc.
 **/
function init()
{
	$this->trigger('controller.init');
}

/*
 * Return list of arguments for requested method, based on supplied params.
 */
function getArgs($actionMethod, array $params)
{
	$args = array();
	$rm = new \ReflectionMethod($this, $actionMethod);
	foreach($rm->getParameters() as $param)  {
		$param_value = array_get($params, $param->name);
		if (!strlen($param_value) and !$param->isOptional()) {
			$this->app->error('Required parameter "%s" for page "%s" missing.', null, $param->name, get_class($this) .'/'.$this->action);
		}
		$args[] = isset($param_value)? $param_value : $param->getDefaultValue();
	}

	return $args;
}

/*
 * Return name of the action to be actually called.
 */
function findActionName($action)
{
	if (!$action) $action = 'index';

	if ($this->allowDashInAction and strpos($action, '-')) {
		if (strpos($action, '--')) return false;
		$action = lcfirst(str_replace('-', '', ucwords(strtolower($action), '-')));
		if (in_array($action.$this->ACTION_POSTFIX, get_class_methods($this))) return $action;
		return false;
	}

	if (method_exists($this, $action.$this->ACTION_POSTFIX)) {
		return $action;
	}

	return false;
}

/**
 * Call action method of the controller, feeding it with required parameters.
 * @param Action $action called action.
 */
public function run($action)
{
	$this->name = $action->controller;
	$this->action = $this->findActionName($action->method);
	$this->init();

	if ($this->action) {
		$action_method = $this->action.$this->ACTION_POSTFIX;
		$args = $this->getArgs($action_method, $action->params);

		return call_user_func_array([$this, $action_method], $args);
	}
	else {
		return $this->defaultAction($action);
	}
}

public function defaultAction($action)
{
	$this->app->httpError(404, 'Page not found: "%s"', null, $action->path);
}

/**
 * Call route $rs and return result of the controller's action.
 * @param string $rs Route path i.e. 'comment/edit/id:1'
 * @return string $output
 */
function action($rs)
{
	$action = new pclib\Action($rs);
	$ct = $this->app->newController($action->controller);

	if (!$ct) throw new Exception('Build of '.$action->controller.' failed.');

	return $ct->run($action);
}

/**
 * Create template $path, populated with $data.
 * @param string $path Path to template
 * @param array $data Template values
 * @return Tpl $template
 */
public function template($path, $data = [])
{
  $templ = new pclib\Tpl($path);
  $templ->values = $data;
  return $templ;
}

/**
 * Redirect to $route.
 **/
function redirect($route)
{
	$this->app->redirect($route);
}

/**
 * Return model for table $tableName.
 **/
function model($tableName, $id = null)
{
	$model = orm\Model::create($tableName, array(), false);
	
	if ($id) {
		$found = $model->find($id);
		if (!$found) return null;
	}

	return $model;
}

/**
 * Return orm\Selection class.
 **/
function selection($from = null)
{
	$sel = new orm\Selection;
	if ($from) $sel->from($from);
	return $sel;
}

/**
 * Check if user has permission $perm. If not, redirect to sign-in or throw error.
 * If $perm is empty, any logged user is allowed.
 **/
function authorize($perm = '')
{
	$auth = $this->app->getService('auth');

	if (!$auth) {
		$this->app->error('Permission denied.');
	}

	if (!$perm and $auth->isLogged()) return;

	if (!$auth->isLogged()) {
		$this->app->message('Please sign in.');
		$this->app->setSession('backurl', $this->app->request->getUrl());
		$this->redirect($this->authorizeRedirect);
	}

	if (!$auth->hasRight($perm)) {
		http_response_code(403);
		$this->app->error('Permission denied.');
	}
}

/**
 * Output json data and exit. Use for actions called by ajax.
 * @param array $data
 * @param string $code Http response code
 **/
public function outputJson(array $data, $code = '')
{
	if ($code) {
		http_response_code($code);
	}

	header('Content-Type: application/json; charset=utf-8');
	die(json_encode($data, JSON_UNESCAPED_UNICODE));
}


}