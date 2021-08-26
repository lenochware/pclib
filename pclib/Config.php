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
$config = [
	'pclib.errors' => ['display', 'develop', /*,'log','template'=>'error.tpl' */],
	'pclib.locale' => ['date' => '%d. %m. %Y', 'datetime' => '%d.%m.%Y %H:%M%:%S'],

	'pclib.directories' => [
		'assets' => '{pclib}/assets/',
		'localization' => '{webroot}{pclib}/localization/',
		'uploaded' => '{basedir}/uploaded',
	],

	'pclib.security' => ['tpl-escape' => false, 'csrf' => false, 'form-prevent-mass' => false],
	'pclib.auth' => ['algo' => 'md5', 'secret' => 'write any random string!', 'realm' => ''],

	'pclib.app' => [
		'db' => '',
		'auth' => false,
		'logger' => false,
		'file-storage' => false,
		'language' => 'cs',
		'default-route' => '',
		'layout' => '',
	],
];

?>