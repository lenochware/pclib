<?php
/**
 * @file
 * PClib constants, global functions and variables
 *
 * @author -dk- <lenochware@gmail.com>
 * @link http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

use pclib\Str;


/**
 * Dump variable(s) for debugging and stop application.
 * Usage: dump($a,$b,...);
 **/
function dump()
{
	global $pclib;
	$debug = $pclib->app->debugger;
	$args = func_get_args();
	$s = $debug->getDump($args);
	$debug->errorDump('DUMP: Application stopped at');
	die($s);
}

/**
 * Dump variable(s) for debugging to the debug log.
 * Usage: ddump($a,$b,...);
 **/
function ddump()
{
	$dd = pclib\Extensions\DebugBar::getInstance();
	$dd->dump(func_get_args());
}

/**
 * Dump variable(s) for debugging to the javascript console.
 * Usage: jdump($a,$b,...);
 **/
function jdump()
{
	global $pclib;
	$args = func_get_args();

	$js = $pclib->app->getSession('pclib.jdump') ?: '';
	
	foreach($args as $var) {
		
		if (is_object($var)) {
			$var = ["__class__" => "[object ".get_class($var)."]"] + (array)$var;
		}

		if (is_array($var)) {
			$output = [];
			foreach ($var as $key => $value) {
				if (is_object($value)) $value = "[object ".get_class($value)."]";
				if (is_array($value)) $value = "[array(".count($value).")]";
				$key = str_replace("\0", ' ', $key);
				$output[$key] = $value;
			}

			$output = ["__class__" => "[PHPArray(".count($var).")]"] + (array)$output;
		}
		else {
			$output = $var;
		}

		$js .= 'console.log('.json_encode($output).');';
	}

	$pclib->app->setSession('pclib.jdump', $js);
}

/** 
 * Return string "ID='$id'".
 * Helper for db queries on primary key.
 * @deprecated Use [ID => $id] instead.
 */
function pri($id)
{
	$id = (int)$id;
	return "ID='$id'";
}

/** 
 * Replace {param} placeholders in string with values from array $param.
 * @deprecated Use Str::format() instead.
 */
function paramStr($str, $params, $keepEmpty = false)
{
	return pclib\Str::format($str, $params, $keepEmpty);
}

function pcl_build_query($query_data)
{
	$trans = array('%2F'=>'/','%3A'=>':','%2C'=>',');
	return strtr(http_build_query($query_data), $trans);
}

/**
 * Return mime-type of the file $path.
 * It uses linux command 'file', so it works only on *nix with exec() allowed.
 * On windows you can set array of extension => mime-type pairs into pclib.mimetypes
 * config variable.
**/
function mimetype($path)
{
	global $app;
	$ext = strtolower(Str::extractPath($path,'%e'));
	if ($app and $app->config['pclib.mimetypes'][$ext])
		$ret = $app->config['pclib.mimetypes'][$ext];
	else
		$ret = @exec("file -bi " . escapeshellarg($path));

	return $ret? $ret : 'application/octet-stream';
}

/**
 * Send file $path to output through php (so real path to file is hidden).
 * You can force browser download dialog or show file name as $filename.
**/
function filedata($path, $force_download = false, $filename = null)
{
	if (!file_exists($path)) throw new Exception('File not found.'); //FileNotFoundException;
	if (!$filename) $filename = Str::extractPath($path, '%f.%e');

	if ($force_download) {
		header('Content-type: '.mimetype($path));
		header('Content-Disposition: attachment; filename="'.$filename.'"');
	}
	else {
		header('Content-type: '.mimetype($path));
		header('Content-Disposition: inline; filename="'.$filename.'"');
	}

	readfile($path);
	die();
}

/**
 * Configure session cookie for safe usage and call session_start().
**/
function safe_session_start($httpsOnly = false)
{
  //ini_set('session.use_only_cookies', 1);
  ini_set('session.cookie_httponly', 1);
  if ($httpsOnly) ini_set('session.cookie_secure', 1);
  ini_set('session.use_strict_mode', 1);
  ini_set('session.cookie_samesite', 'lax');

  session_start();
}

/* fnmatch does not exists on windows systems */
if(!function_exists('fnmatch')) {
		function fnmatch($pattern, $string) {
				return preg_match("#^".strtr(preg_quote($pattern, '#'), array('\*' => '.*', '\?' => '.'))."$#i", $string);
		}
} // end if

function array_get($a, $key, $default = null)
{
	if (is_array($key)) {
		return isset($a[$key[0]])? (isset($a[$key[0]][$key[1]])? $a[$key[0]][$key[1]] : $default) : $default;
	}

	return isset($a[$key])? $a[$key] : $default;
}

function jdump_sql()
{
  global $app;
  $app->db->on('db.after-query', function($e) { jdump($e->data['sql']); });
}

?>