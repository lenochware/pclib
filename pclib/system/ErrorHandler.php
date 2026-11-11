<?php
/**
 * @file
 * ErrorHandler class
 *
 * @author -dk- <lenochware@gmail.com>
 * @link https://pclib.brambor.net/
 * @license MIT (https://opensource.org/licenses/MIT)
 */

namespace pclib\system;
use pclib\Tpl;
use pclib\Str;

/**
 * Catch errors and exceptions and show improved error messages.
 * You can configure behavior of $app->errorHandler with pclib.errors config parameter.
 * Features:
 * - Development error reporting with stack trace
 * - Customizable error template for production environment
 * - Store errors to the log
 * - Hook your own function (e.g. mail) to onException handler
 */
class ErrorHandler extends BaseObject
{
	/** var array [log, display, develop, error_reporting, template] */
	public $options = [];

	public $MESSAGE_PATTERN = "<br><b>{severity} {code}: {exceptionClass}</b> {message}";
	public $MESSAGE_LOG_PATTERN = "{exceptionClass}: {message} in '{file}' on line {line} processing '{route}' at {timestamp}.\n\nStack trace:\n{trace}";

	/** var Logger */
	public $logger; 

	/** var Debugger */
	public $debugger;

	/**
	 * Register ErrorHandler.
	 */	
	function register()
	{
		//error types are filtered in _onError() according to options['error_reporting']
		set_error_handler([$this, '_onError']);
		set_exception_handler([$this, '_onException']);
	}

	/**
	 * Unregister ErrorHandler.
	 */	
	function unregister()
	{
		restore_error_handler();
		restore_exception_handler();
	}

	/*
	 * Setup this service from configuration file.
	 */
	public function setOptions(array $options)
	{
		$this->options = $options;
	}

	/**
	 * Callback for exception handling.
	 */	
	function _onException($e)
	{
		// disable error capturing to avoid recursive errors
		restore_exception_handler();

		if (array_get($this->options, 'display') === 'php') throw $e;

		http_response_code(500);
		$this->trigger('php-exception', ['Exception' => $e]);
		if (array_get($this->options, 'log')) $this->logError($e);
		if (!array_get($this->options, 'display')) exit(1);

		if ($e instanceof \pclib\ApiException) {
			http_response_code(500);
			die($e->getMessage());
		}

		if (array_get($this->options, 'develop')) {
			$this->displayError($e);
		}
		else {
			$this->displayProductionError($e);
		}

		exit(1);
	}

	/**
	 * Callback for error handling.
	 * Errors raised inside this handler are passed to php standard handler by php itself.
	 */
	function _onError($code, $message, $file, $line, $context = null)
	{
		//Not configured yet or error type not handled - use php standard handler.
		if (!$this->options) return false;
		if (!(array_get($this->options, 'error_reporting', E_ALL ^ E_NOTICE) & $code)) return false;

		if (array_get($this->options, 'display') === 'php') return false;

		//Handle warnings...
		if ($this->codeSeverity($code) != 'Error') {

			//skip warning when '@' operator is used (in php 8 error_reporting() is not 0 there)
			if (!(error_reporting() & $code)) return;

			$this->_onWarning(
				new \ErrorException($message, $code, 0, $file, $line)
			);
			
			return;
		}

		$this->_onException(
			new \ErrorException($message, $code, 0, $file, $line)
		);
	}

	protected function codeSeverity($code)
	{
		switch ($code) {
			case E_WARNING:
			case E_USER_WARNING:
				return 'Warning';

			case E_DEPRECATED:
			case E_USER_DEPRECATED:
			case E_NOTICE:
			case E_USER_NOTICE:
				return 'Notice';
			default:
				return 'Error';
		}
	}

	/**
	 * Callback for warning handling.
	 */	
	function _onWarning($e)
	{
		$event = $this->trigger('php-warning', ['Exception' => $e]);
		if ($event and !$event->propagate) return;

		if (array_get($this->options, 'log')) $this->logError($e);
		if (!array_get($this->options, 'develop')) return;
		if (!array_get($this->options, 'display')) return;

		$this->displayError($e);
	}

	protected function getValues($e)
	{
		$values = array(
			'code' => $e->getCode(),
			'exceptionClass' => get_class($e),
			'severity' => $this->codeSeverity($e->getCode()),
			'message' => $e->getMessage(),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
			'trace' => $e->getTraceAsString(),
			'htmlTrace' => $this->getHtmlTrace($e),
			'route' => array_get($_REQUEST, 'r'),
			'timestamp' => date('Y-m-d H:i:s'),
		);
		return $values;
	}

	protected function getHtmlTrace($e)
	{
		return $this->service('debugger')->getTrace($e);
	}

	/**
	 * Display error in development mode (with stack trace).
	 */	
	function displayError($e)
	{
		try {
			//throw new Exception('ErrorHandlerDisplayBug');
			$this->service('debugger')->errorDump(
			Str::format($this->MESSAGE_PATTERN, $this->getValues($e)),$e);
		}
		//fallback to most straighforward error message
		catch(\Throwable $ex) {
			print $e->getMessage();
			print "<br>Error while displaying exception: ".$ex->getMessage();
		}
	}

	/**
	 * Display error in production mode (uses template).
	 */	
	function displayProductionError($e)
	{
		if (!headers_sent()) {
			header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 500 Internal Server Error', true, 500);
		}

		try {
			$t = new Tpl(array_get($this->options, 'template'));
			$t->values = $this->getValues($e);
			print $t->html();
		}
		catch(\Throwable $ex) {
			print $e->getMessage();
			print "<br>Error while displaying exception: ".$ex->getMessage();
		}
	}

	function logError($e)
	{
		try {
			$error = $this->getValues($e);
			
			$logger = $this->service('logger');
			if (!$logger) return;

			$logger->log('php/error', $error['severity'],
				Str::format($this->MESSAGE_LOG_PATTERN, $error)
			);
		}
		catch(\Throwable $ex) {
			//print "<br>Error while logging exception: ".$ex->getMessage(); //silently ignore logger exceptions
		}
	}

}

?>