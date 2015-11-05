<?php

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
protected $positionDefault = 'position:absolute;top:10px;right:10px;';

protected $updating = false;

function __construct()
{
	global $pclib;
	$this->app = $pclib->app;

	//Use logger with independent db connection to avoid conflicts.
	$this->logger = new Logger('debuglog');
	$this->logger->storage = new LoggerDbStorage($this->logger);
	$this->logger->storage->db = clone $this->app->db;

	$this->logUrl();
}

function addEvents($events)
{
	foreach ($events as $name => $closure) {
		list($className, $eventName) = explode('.', $name);
		BaseObject::$defaults[$className][$eventName][] = $closure;
	}
}

function register()
{
	$events = array(
		'App.onBeforeOut' => array($this, 'hook'),
		'App.onBeforeRun' => array($this, 'hook'),
		'App.onError' => array($this, 'hook'),
		'Db.onBeforeQuery' => array($this, 'hook'),
		'Db.onAfterQuery' => array($this, 'hook'),
		//'Func.onLogDump' => array($this, 'onLogDump'),
	);

	$this->addEvents($events);
	
	$this->app->loadDefaults();
	if ($this->app->db) {
		$this->app->db->loadDefaults();
	}

	$this->startTime = microtime(true);
}

function html()
{
	$t = new Tpl(PCLIB_DIR.'assets/debugbar.tpl');
	$t->values['POSITION'] = ifnot($this->app->config['pclib.debugbar.position'], $this->positionDefault);
	$t->values['VERSION'] = PCLIB_VERSION;
	$t->values['TIME'] = $this->getTime($this->startTime);
	$t->values['MEMORY'] = round(memory_get_peak_usage()/1048576,2);

	return $t->html();
}

function hook($event)
{
	if ($this->updating) return;

	$this->updating = true;
	$name = $event->name;
	$this->$name($event);
	$this->updating = false;
}

function onBeforeOut($event)
{
	$this->app->layout->values['CONTENT'] .= $this->html();
}

function onBeforeRun($event)
{
	if ($this->app->routestr == 'pclib/debuglog') {
		$this->printLogWindow();
		$event->propagate = false;
	}
	if ($this->app->routestr == 'pclib/debuglog/clear') {
		$this->logger->deleteLog(0);
		$event->propagate = false;
	}
	elseif($this->app->routestr == 'pclib/debuginfo') {
		$this->printInfoWindow();
		$event->propagate = false;
	}
}

function onBeforeQuery($event)
{
	$this->queryTime = microtime(true);
}

function onAfterQuery($event)
{
	$msec = $this->getTime($this->queryTime);
	$this->logger->log('DEBUG', 'query',
		preg_replace("/(\s*[\r\n]+\s*)/m", "\\1<br>", $event->data[0]) // \n => <br>
		." <span style=\"color:blue\">($msec ms)</span>"
	);
}

function onError($event)
{
	$this->logger->log('DEBUG', 'error', $event->data[0]);
}

function onLogDump($event)
{
	$dbg = $this->app->debugger;

	$dbg->useHtml = false;
	$path = htmlspecialchars($dbg->tracePath(2));
	$dbg->useHtml = true;

	$this->logger->log('DEBUG', 'DUMP',
		"<span title=\"$path\">".$dbg->getDump($event->data).'</span>'
	);
}

protected function logUrl()
{
	if ($this->app->routestr == 'pclib/debuglog') return;

	$request = $this->app->request;
	$message = '<b>'
	.($request->isAjax()? 'AJAX ': '')
	.$request->method
	.'</b> '
	.$request->url;

	if ($request->method == 'POST')
		$message .= '<br>'.$this->app->debugger->getDump(array($_POST));

	$this->logger->log('DEBUG', 'url', $message);
}

protected function printLogWindow()
{
	$grid = new Grid(PCLIB_DIR.'assets/debuglog.tpl');
	$data = $this->logger->getLog(100, array('LOGGERNAME' => $this->logger->name));
	$grid->setArray($data);
	print $grid;
	die();
}

protected function printInfoWindow()
{
	$dbg = $this->app->debugger;
	print "<a href=\"?r=pclib/debuglog/clear\">Clear debuglog</a><br>";
	print '<h1>_SESSION</h1>';
	print $dbg->getDump(array($_SESSION));
	print '<h1>_COOKIE</h1>';
	print $dbg->getDump(array($_COOKIE));
	die();
}

protected function getTime($startTime)
{
	return round((microtime(true) - $startTime) * 1000, 1);
}

}

?>
