<?php
/**
 * @file
 * Global class pclib. Perform library initialization. Including this file is REQUIRED.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

/**
 * PClib version string
 */
define('PCLIB_VERSION', '2.1.0');

/* Find out where library reside. MUST BE an absolute path. */
if (!defined('PCLIB_DIR')) {
	define('PCLIB_DIR',
		rtrim(dirname(__FILE__),"/\\").DIRECTORY_SEPARATOR);
}

if (!defined('BASE_URL')) {
	define ('BASE_URL',
		rtrim(dirname($_SERVER['PHP_SELF']),"/\\").'/');
}

require_once PCLIB_DIR . 'system/exceptions.php';
require_once PCLIB_DIR . 'Func.php';
require_once PCLIB_DIR . 'system/Autoloader.php';

/**
 * %Pclib initialization and autoloading.
 */
class Pclib
{
	/** var App PClib application */
	public $app;

	public $version;

	/** var Autoloader */
	public $autoloader;

	public $legacyAliases = array(
		'App' => '\pclib\App',
		'Db' => '\pclib\Db',
		'Tpl' => '\pclib\Tpl',
		'Grid' => '\pclib\Grid',
		'Form' => '\pclib\Form',
		'Auth' => '\pclib\Auth',
		'Tree' => '\pclib\Tree',
		'Logger' => '\pclib\Logger',
		'Translator' => '\pclib\Translator',
		'App_Controller' => '\pclib\Controller',		
	);

	/** PClib intialization - it's called just once before using %pclib. */
	function init()
	{
		$aliases = array(
			'PCApp' => '\pclib\App',
			'PCDb' => '\pclib\Db',
			'PCTpl' => '\pclib\Tpl',
			'PCGrid' => '\pclib\Grid',
			'PCForm' => '\pclib\Form',
			'PCAuth' => '\pclib\Auth',
			'PCTree' => '\pclib\Tree',
			'PCLogger' => '\pclib\Logger',
			'PCTranslator' => '\pclib\Translator',
			'PCController' => '\pclib\Controller',
			'PCModel' => '\pclib\orm\Model',
			'PCValidator' => '\pclib\Validator',
			'PCSelection' => '\pclib\orm\Selection',
		);

		$this->legacyAliases += array_change_key_case($this->legacyAliases, CASE_LOWER);

		$this->version = PCLIB_VERSION;
		$autoload = new \pclib\system\Autoloader;
		$autoload->addDirectory(PCLIB_DIR, array('namespace' => 'pclib'));
		$autoload->addAliases($aliases);
		$autoload->register();
		$this->autoloader = $autoload;

		ini_set('docref_root', 'http://php.net/');

		//%form button hack
		if (is_array($_REQUEST['pcl_form_submit'])) {
			$tmp = array_keys($_REQUEST['pcl_form_submit']);
			$_REQUEST['pcl_form_submit'] = $tmp[0];
		}
	}
} //class pclib

global $pclib;
$pclib = new Pclib();
$pclib->init();

?>