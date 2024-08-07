<?php

namespace pclib\extensions;
use pclib\system\BaseObject;

/**
 * Show DebugBar in your web-application.
 * Usually you enable debugbar by adding $app->debugMode = true; line into your
 * index.php file.
 */
class DebugBar
{
protected $app;

protected $queryTime;
protected $startTime;
protected $queryTimeSum;

protected $positionDefault = 'position:fixed;bottom:10px;right:10px;';

public $registered = false;
public $hideErrors = true;

private static $instance;

private function __construct()
{
	global $pclib;
	$this->app = $pclib->app;

	if (!isset($_SESSION['pclib.debugbar'])) {
		$_SESSION['pclib.debugbar'] = [
			'records' => [],
			'input' => '',
			'action' => null,
			'viewed' => null,
			'errors' => 0,
			'window' => '',
		];
	}

	if (!$this->isDebugBarRequest()) {
		if (isset($_SESSION['pclib.debugbar']['viewed'])) $_SESSION['pclib.debugbar']['records'] = [];
		$_SESSION['pclib.debugbar']['viewed'] = null;
		$_SESSION['pclib.debugbar']['errors'] = 0;
	}	

	$this->logUrl();
}

public static function getInstance()
{
  if (self::$instance === null) {
      self::$instance = new self;
  }

  return self::$instance;
}

public static function register()
{
	$that = self::getInstance();

	if ($that->registered) return;

	$events = [
		'app.before-out' => [$that, 'onBeforeOut'],
		'app.after-out'  => [$that, 'onAfterOut'],
		'app.before-run' => [$that, 'onBeforeRun'],
		'app.error' => [$that, 'onError'],
		'php-exception'   => [$that, 'onPhpException'],
		'php-warning'   => [$that, 'onPhpWarning'],
		'db.before-query' => [$that, 'onBeforeQuery'],
		'db.after-query'  => [$that, 'onAfterQuery'],
		'router.redirect' => [$that, 'onRedirect'],
	];

	$that->addEvents($events);
		
	$that->startTime = microtime(true);
	$that->queryTimeSum = 0;
	$that->registered = true;
}


protected function addEvents($events)
{
	foreach ($events as $name => $fn) {
		$this->app->events->on($name, $fn);
	}
}

protected function log($category, $message)
{
	if ($this->isDebugBarRequest()) return;
	$_SESSION['pclib.debugbar']['records'][] = ['category' => $category, 'message' => $message];
}

function html()
{
	$t = new PCTpl(PCLIB_DIR.'tpl/debugbar/main.tpl');
	$t->values['POSITION'] = array_get($this->app->config, 'pclib.debugbar.position', $this->positionDefault);
	$t->values['VERSION'] = PCLIB_VERSION;
	$t->values['TIME'] = $this->getTime($this->startTime);
	$t->values['MEMORY'] = round(memory_get_peak_usage()/1048576,2);
	$t->values['ERRORS'] = $_SESSION['pclib.debugbar']['errors'];
	
	return $t->html();
}

function onBeforeOut($event)
{
	$this->app->layout->values['CONTENT'] .= $this->html();
}


function onAfterOut($event)
{
	$message = "Time: ". $this->getTime($this->startTime).' ms, db: '.$this->queryTimeSum.' ms';
	$message = "<b>$message</b>";
	$this->log('time', $message);
}


function onBeforeRun($event)
{

	if (!$this->isDebugBarRequest()) {
		$_SESSION['pclib.debugbar']['action'] = $event->action;
		return;
	}

	switch ($event->action->method) {
		case 'show':  
			if ($_SESSION['pclib.debugbar']['window'] == 'console') $this->printConsoleWindow();
			else  $this->printLogWindow(); break;
		case 'log': $this->printLogWindow(); break;
		case 'variables': $this->printInfoWindow(); break;
		case 'clear': $_SESSION['pclib.debugbar']['viewed'] = true; break;
		case 'cshow': $_SESSION['pclib.debugbar']['records'] = []; $this->printLogWindow(); break;
		case 'console': $this->printConsoleWindow(); break;
		case 'execute': 
  		$post = json_decode(file_get_contents('php://input'), true);
			$this->executePHP($post); 
			break;
	}

	$event->propagate = false;
}

function onBeforeQuery($event)
{
	$this->queryTime = microtime(true);
}

function onAfterQuery($event)
{
	$msec = $this->getTime($this->queryTime);
	$this->queryTimeSum += $msec;
	$this->log('query', htmlspecialchars($event->sql) ." <span style=\"color:blue\">($msec ms)</span>");
}

function onError($event)
{
	$this->log('error', "<span style=\"color:red\">".$event->message."</span>");
}

function onPhpException($event)
{
	$_SESSION['pclib.debugbar']['errors']++;
	$this->log('error', $event->Exception->getMessage()  . ' ' . $this->app->debugger->getHtmlErrorDump($event->Exception));
}

function onPhpWarning($event)
{
	$_SESSION['pclib.debugbar']['errors']++;
	$this->log('warning', $event->Exception->getMessage()  . ' ' . $this->app->debugger->getHtmlErrorDump($event->Exception));
	if ($this->hideErrors) $event->stopPropagation();
}

function onRedirect($event)
{
	$this->log('redirect', 'Redirect to ' . $event->url);
}

protected function logUrl()
{
	$request = $this->app->request;
	$message = '<b>'
	.($request->isAjax()? 'AJAX ': '')
	.$request->method
	.'</b> '
	.$request->url;

	$this->log('url', $message);

	if ($request->method == 'POST') {
		$this->log('dump', $this->getDump($_POST));
	}	
}

protected function printLogWindow()
{
	$_SESSION['pclib.debugbar']['window'] = 'log';
	print file_get_contents(PCLIB_DIR.'tpl/debugbar/menu.tpl');
	$grid = new PCGrid(PCLIB_DIR.'tpl/debugbar/list.tpl');
	$grid->setArray($_SESSION['pclib.debugbar']['records']);
	print $grid;	
	die();
}

protected function printConsoleWindow()
{
	$_SESSION['pclib.debugbar']['window'] = 'console';
	print file_get_contents(PCLIB_DIR.'tpl/debugbar/menu.tpl');
	$tpl = new PCTpl(PCLIB_DIR.'tpl/debugbar/console.tpl');
	$tpl->values['code'] = $_SESSION['pclib.debugbar']['input'];
	print $tpl;
	die();
}

protected function printInfoWindow()
{
	print file_get_contents(PCLIB_DIR.'tpl/debugbar/menu.tpl');
	print '<h4>_SESSION</h4>';
	print $this->getDump($_SESSION);
	print '<h4>_COOKIE</h4>';
	print $this->getDump($_COOKIE);
	die();
}

protected function executePHP($post)
{
	if ($this->app->request->serverIp !== '127.0.0.1') die('This function is for localhost only.');

	$_SESSION['pclib.debugbar']['input'] = $post['code'];

	$app = $this->app;
	$action = $_SESSION['pclib.debugbar']['action'];
	$c = $app->newController($action->controller, $action->module);
	eval($post['code']);
	die();
}

protected function getTime($startTime)
{
	return round((microtime(true) - $startTime) * 1000, 1);
}

protected function isDebugBarRequest()
{
	return ($this->app->controller == 'pclib_debugbar');
}

protected function getDump()
{
	return $this->app->debugger->getDump(func_get_args());
}

public function dump($vars)
{
	$dbg = $this->app->debugger;
	$message = $dbg->getDump($vars);
	$this->log('dump', $message);
}

}