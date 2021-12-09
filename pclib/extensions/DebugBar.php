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
protected $logger;
protected $app;

protected $queryTime;
protected $startTime;
protected $queryTimeSum;

protected $positionDefault = 'position:fixed;bottom:10px;right:10px;';

protected $updating = false;
public $registered = false;

private static $instance;

private function __construct()
{
	global $pclib;
	$this->app = $pclib->app;
	$this->logger = $this->getLogger();

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
		'db.before-query' => [$that, 'onBeforeQuery'],
		'db.after-query'  => [$that, 'onAfterQuery'],
		'router.redirect' => [$that, 'onRedirect'],
	];

	$that->addEvents($events);
		
	$that->startTime = microtime(true);
	$that->queryTimeSum = 0;
	$that->registered = true;
}

protected function getLogger()
{
	//Use logger with independent db connection to avoid conflicts.
	$logger = new PCLogger('debuglog');
	$logger->storage = new \pclib\system\storage\LoggerDbStorage($logger);
	$logger->storage->db = clone $this->app->db;	
	return $logger;
}

protected function addEvents($events)
{
	foreach ($events as $name => $fn) {
		$this->app->events->on($name, $fn);
	}
}

protected function log($category, $message)
{
	if ($this->updating) return;
	if ($this->isDebugBarRequest()) return;

	$this->updating = true;

	if (rand(1,100) == 1) {
		$this->logger->deleteLog(1);
	}
		
	$this->logger->log('DEBUG', $category, $message);
	$this->updating = false;
}

function html()
{
	$t = new PCTpl(PCLIB_DIR.'assets/debugbar.tpl');
	$t->values['POSITION'] = $this->app->config['pclib.debugbar.position'] ?: $this->positionDefault;
	$t->values['VERSION'] = PCLIB_VERSION;
	$t->values['TIME'] = $this->getTime($this->startTime);
	$t->values['MEMORY'] = round(memory_get_peak_usage()/1048576,2);

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
	if (!$this->isDebugBarRequest()) return;

	switch ($event->action->method) {
		case 'show': $this->printLogWindow(); break;
		case 'clear': $this->logger->deleteLog(0); break;
		case 'variables': $this->printInfoWindow(); break;
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
	$this->log('query',
		preg_replace("/(\s*[\r\n]+\s*)/m", "\\1<br>", htmlspecialchars($event->sql)) // \n => <br>
		." <span style=\"color:blue\">($msec ms)</span>"
	);
}

function onError($event)
{
	$this->log('error', "<span style=\"color:red\">".$event->message."</span>");
}

function onRedirect($event)
{
	$this->log('redirect', '<b>Redirect to ' . $event->url . '</b>');
}

protected function logUrl()
{
	$request = $this->app->request;
	$message = date("H:m:s ").'<b>'
	.($request->isAjax()? 'AJAX ': '')
	.$request->method
	.'</b> '
	.$request->url;

	if ($request->method == 'POST') {
		$message = $this->getDump($_POST) . '<br>' . $message;
	}

	$this->log('url', $message . '<hr>');
}

protected function printLogWindow()
{
	print file_get_contents(PCLIB_DIR.'assets/debugmenu.tpl');
	print '<h4>Debug Log</h4>';
	$grid = new PCGrid(PCLIB_DIR.'assets/debuglog.tpl');
	$data = $this->logger->getLog(100, array('LOGGERNAME' => $this->logger->name));
	$grid->setArray($data);
	print $grid;
	die();
}

protected function printInfoWindow()
{
	print file_get_contents(PCLIB_DIR.'assets/debugmenu.tpl');
	print '<h4>_SESSION</h4>';
	print $this->getDump($_SESSION);
	print '<h4>_COOKIE</h4>';
	print $this->getDump($_COOKIE);
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

	$sav = $dbg->useHtml;
	$dbg->useHtml = false;

	$message = $dbg->getDump($vars);
	$this->log('dump', $message);
	
	$dbg->useHtml = $sav;	
}

}
