<?php
/**
 * @file
 * PClib default configuration.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link https://pclib.brambor.net/
 * @license MIT (https://opensource.org/licenses/MIT)
 */

/** pclib default configuration */
$config = [
	'pclib.errors' => ['display' => true, 'develop' => true, 'log' => false, 'template'=> PCLIB_DIR.'tpl/error.tpl' ],
	'pclib.locale' => ['date' => 'd. m. Y', 'datetime' => 'd.m.Y H:i:s'],

	'pclib.paths' => [
		'assets' => '{pclib}/www/',
		'localization' => '{webroot}{pclib}/localization/',
		'templates' => '{webroot}{pclib}/tpl/',
	],

	'pclib.security' => ['tpl-escape' => true, 'csrf' => false, 'form-prevent-mass' => false],
	'pclib.auth' => ['algo' => 'md5', 'secret' => 'write any random string!', 'realm' => ''],

	'pclib.app' => [
		'language' => 'cs',
		'default-route' => '',
		'layout' => '',
		'autostart' => [],
	],
];

$develop = [
	'pclib.errors' => ['develop' => true],
];

$production = [
	'pclib.errors' => ['develop' => false, 'log' => true],
];

?>