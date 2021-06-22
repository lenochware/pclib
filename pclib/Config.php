<?php
/**
 * @file
 * PClib default configuration.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

/** pclib default configuration */
$config = array(
	'pclib.errors' => array('display', 'develop', /*,'log','template'=>'error.tpl' */),
	'pclib.locale' => array('date' => '%d. %m. %Y', 'datetime' => '%d.%m.%Y %H:%M%:%S'),
	'pclib.logger' => array('log' => ['ALL']),

	'pclib.directories' => array(
		'logs' => 'temp/log/',
		'assets' => '{pclib}/assets/',
		'localization' => '{webroot}{pclib}/localization/',
	),

	'pclib.loader' => array(
		'controller' => array('dir' => 'controllers', 'namespace' => '', 'postfix' => 'Controller'),
	),

	'pclib.security' => array('tpl-escape' => false, 'csrf' => false, 'form-prevent-mass' => false),
	'pclib.auth' => array('algo' => 'md5', 'secret' => 'write any random string!', 'realm' => ''),
);

?>