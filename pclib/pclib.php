<?php
/**
 * @file
 * Global class pclib. Perform library initialization. Including this file is REQUIRED.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link https://pclib.brambor.net/
 * @license MIT (https://opensource.org/licenses/MIT)
 */

/**
 * PClib version string
 */
define('PCLIB_VERSION', '3.2.0');

/* Find out where library reside. MUST BE an absolute path. */
if (!defined('PCLIB_DIR')) {
	define('PCLIB_DIR',
		rtrim(dirname(__FILE__),"/\\").DIRECTORY_SEPARATOR);
}

if (!defined('BASE_URL')) {
	define ('BASE_URL',
		str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']));
}

require_once PCLIB_DIR . 'system/exceptions.php';
require_once PCLIB_DIR . 'Str.php';
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

	/** PClib intialization - it's called just once before using %pclib. */
	function init()
	{
		$aliases = array(
			'PCStr' => '\pclib\Str',
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
			'PCFileStorage' => '\pclib\FileStorage',
			'PCMailer' => '\pclib\Mailer',
		);

		$this->version = PCLIB_VERSION;
		$autoload = new \pclib\system\Autoloader;
		$autoload->addDirectory(PCLIB_DIR, array('namespace' => 'pclib'));
		$autoload->addAliases($aliases);
		$autoload->register();
		$this->autoloader = $autoload;

		ini_set('docref_root', 'http://php.net/');

		//%form button hack
		if (is_array(array_get($_REQUEST, 'pcl_form_submit'))) {
			$tmp = array_keys($_REQUEST['pcl_form_submit']);
			$_REQUEST['pcl_form_submit'] = $tmp[0];
		}
	}
} //class pclib

global $pclib;
$pclib = new Pclib();
$pclib->init();

?>