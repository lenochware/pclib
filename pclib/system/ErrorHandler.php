<?php
/**
 * @file
 * ErrorHandler class
 *
 * @author -dk- <lenochware@gmail.com>
 * @link http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib\system;
use pclib\Tpl;

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
	public $options = array();

	public $MESSAGE_PATTERN = "<b>{severity} {code}: {exceptionClass}</b> {message}";

	/** Occurs before Exception handling. */ 
	public $onException;

	/** var Logger */
	public $logger; 

	/** var Debugger */
	public $debugger;

	/**
	 * Register ErrorHandler.
	 */	
	function register()
	{
		$codes = $this->options['error_reporting'];
		set_error_handler(array($this, '_onError'), isset($codes)? $codes : E_ALL ^ E_NOTICE);
		set_exception_handler(array($this, '_onException'));
	}

	/**
	 * Unregister ErrorHandler.
	 */	
	function unregister()
	{
		restore_error_handler();
		restore_exception_handler();
	}

	/**
	 * Callback for exception handling.
	 */	
	function _onException($e)
	{
		// disable error capturing to avoid recursive errors
		restore_exception_handler();
		$this->onException($e);
		if (in_array('log', $this->options)) $this->logError($e);
		if (!in_array('display', $this->options)) return;

		if ($e instanceof \pclib\ApiException) {
			http_response_code(500);
			die($e->getMessage());
		}

		if (in_array('develop', $this->options)) {
			$this->displayError($e);
		}
		else {
			$this->displayProductionError($e);
		}
		exit(1);
	}

	/**
	 * Callback for error handling.
	 */	
	function _onError($code, $message, $file, $line, $context = null)
	{
		// disable error capturing to avoid recursive errors
		restore_error_handler();

		//Handle warnings...
		if ($this->codeSeverity($code) != 'Error') {
			
			//skip warning when '@' operator is used.
			if (!error_reporting()) return;

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
		if (in_array('log', $this->options)) $this->logError($e);
		if (!in_array('develop', $this->options)) return;

		$this->service('debugger')->errorDump(
		paramStr($this->MESSAGE_PATTERN, $this->getValues($e)),$e);
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
			'route' => $_REQUEST['r'],
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
			paramStr($this->MESSAGE_PATTERN, $this->getValues($e)),$e);
		}
		//fallback to most straighforward error message
		catch(\Exception $ex) {
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
			header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
		}

		try {
			$template = $this->options['template'];
			$t = new Tpl($template? $template : PCLIB_DIR.'assets/error.tpl');
			$t->values = $this->getValues($e);
			print $t->html();
		}
		catch(\Exception $ex) {
			print $e->getMessage();
			print "<br>Error while displaying exception: ".$ex->getMessage();
		}
	}

	function logError($e)
	{
		try {
			$error = $this->getValues($e);
			
			$this->service('logger')->log($error['severity'], $error['severity'],
				paramStr("{exceptionClass}: {message} in '{file}' on line {line} processing '{route}' at {timestamp}", $error)
			);
		}
		catch(\Exception $ex) {
			print "<br>Error while logging exception: ".$ex->getMessage();
		}
	}

}

?>