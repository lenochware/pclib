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

/** Placeholders in string $str will be replaced with values from $param array.
 *  Format is the same like for template file. \n
 *  Ex: print paramstr("{A} is {B}", array('A' => 'pclib', 'B' => 'best')); \n
 *  $param can be two-dimensional array of n-rows, each row is formated with string $str.
 *
 * @param string $str string with {PARAM} parameters (placeholders)
 * @param array $param associative array PARAM=>VALUE
 * @param bool $keepEmpty Keep (don't delete) tags undefined in $param
 * @return string $str
 */
function paramstr($str, $param, $keepEmpty = false)
{
	preg_match_all("/{([a-z0-9_.]+)}/i", $str, $found);
	if (!$found[1]) return $str;
	if (!is_array($param[0])) $param = array($param);

	$n = count($param);
	$newstr = '';
	for ($i = 0; $i < $n; $i++) {
		$from = $to = null;
		foreach($found[1] as $key) {
			if ($keepEmpty and !isset($param[$i][$key])) continue;
			$from[] = '{'.$key.'}';
			$to[] = $param[$i][$key];
		}
		$newstr .= str_replace($from, $to, $str);
	}
	return $newstr;
}
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
 * Dump variable(s) for debugging to the javascript console.
 * Usage: jdump($a,$b,...);
 **/
function jdump()
{
	global $pclib;
	$js = '';
	$args = func_get_args();
	
	foreach($args as $var) {
		$js .= 'console.log('.json_encode($var).');';
	}
	
	if ($pclib->app->layout)
		$pclib->app->layout->addInline("<script>$js</script>");
	else
		print "<script>$js</script>";
}

/**
 * Dump variable(s) for debugging to the text file / database log.
 * Usage: logdump($a,$b,...);
 **/
function logdump()
{
	global $pclib;
	$app = $pclib->app;
	$args = func_get_args();
	
	$debug = $app->debugger;
	$sav = $debug->useHtml;
	$debug->useHtml = false;
	$s = $debug->getDump($args);
	$debug->useHtml = $sav;

	$dir = $app->config['pclib.directories']['logs'];
	$logfile = $dir.'logdump.log';
	$s = "\n--- ".date("Y-m-d H:i:s")." ---\n".$s;
	file_put_contents($logfile, $s, FILE_APPEND);
}


/** 
 * Return string "ID='$id'".
 * Helper for db queries on primary key. \n
 * Ex: $db->select('PRODUCTS', pri($id));
 */
function pri($id)
{
	$id = (int)$id;
	return "ID='$id'";
}

/**
 * Return part of the filesystem path.
 * Format can use placeholders %d directory, %f filename, %e extension.
 * Example: extractpath($path, "%f.%e"); //return "filename.extension"
 */
function extractpath($path, $format)
{
	$path_a = pathinfo($path);
	$trans = array(
		'%d' => rtrim($path_a['dirname'],"/\\"),
		'%f' => $path_a['filename'],
		'%e' => $path_a['extension'],
	);
	return strtr($format, $trans);
}

/**
 * Convert string to identificator.
 * Convert characters to ascii and remove other characters. You can set words
 * separator or convert to uppercase/lowercase.
 *
 * @param string $s input text
 * @param string $options ex: '' : camelcase, '-' : 'camel-case', '_U': CAMEL_CASE etc.
 * @return string $identificator
**/
function mkident($s, $options = '')
{
	$s = preg_replace('/[^\w]+/',' ', utf8_ascii($s));
	if (strlen($options) == 1) {
	 if(ctype_alnum($options)) $o1 = $options; else $o2 = $options;
	}
	else { $o1 = $options{1}; $o2 = $options{0}; }

	switch($o1) {
		case 'u': $s = ucwords($s);    break;
		case 'U': $s = strtoupper($s); break;
		case 'l': $s = strtolower($s); break;
	}
	$s = str_replace(' ',$o2, $s);
	return $s;
}

/**
 * Similar to array_shift() but for string.
 * Return beginning of the string to the separator $separ, shortening original $str
 */
function str_shift($separ, &$str)
{
	$pos = strpos($str, $separ);
	if ($pos === false) return '';
	$beg = substr($str, 0, $pos);
	$str = substr($str, $pos + strlen($separ));
	return $beg;
}

/**
 * Convert string to pcl identificator, valid for system purposes.
 * Reduce input text to alphanumeric characters plus underscore, dot and hyphen
 *
 * @param string $str input text
 * @return string $identificator system correct
**/
function pcl_ident($str)
{
	return preg_replace("/[^a-z0-9_\-\.]/i","", strtr($str,' -','__'));
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
	$ext = extractpath($path,'%e');
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
	if (!$filename) $filename = extractpath($path, '%f.%e');

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
 * Return string of size $size with random characters.
**/
function randomstr($size, $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
{
	$s = '';
	$max = mb_strlen($characters, '8bit') - 1;
	for ($i = 0; $i < $size; ++$i) {
		$s .= $characters[mt_rand(0, $max)];
	}
	return $s;
}

/* fnmatch does not exists on windows systems */
if(!function_exists('fnmatch')) {
		function fnmatch($pattern, $string) {
				return preg_match("#^".strtr(preg_quote($pattern, '#'), array('\*' => '.*', '\?' => '.'))."$#i", $string);
		}
} // end if

// Utf-8 wrapper

function utf8_preg_replace($pattern, $replacement ,$subject)
{
	if (extension_loaded('mbstring')) $pattern .= 'u';
	return preg_replace($pattern, $replacement ,$subject);
}


function utf8_substr($s , $start, $length = null)
{
	return extension_loaded('mbstring')? mb_substr($s , $start,
		is_null($length)? mb_strlen($s, 'UTF-8') : $length, 'UTF-8')
		: substr($s , $start, $length);
}

function utf8_strpos($haystack , $needle ,$offset = 0)
{
	return extension_loaded('mbstring')? mb_strpos($haystack , $needle ,$offset, 'UTF-8')
		: strpos($haystack , $needle ,$offset);
}

function utf8_strlen($s)
{
	return extension_loaded('mbstring')? mb_strlen($s, 'UTF-8') : strlen($s);
}

function utf8_strtoupper($s)
{
	return extension_loaded('mbstring')? mb_strtoupper($s, 'UTF-8') : strtoupper($s);
}

function utf8_strtolower($s)
{
	return extension_loaded('mbstring')? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

//Remove accents in $s
function utf8_ascii($s)
{
	static $accents = array(
	//german
	'ä'=>'a','ö'=>'o','ü'=>'u','ß'=>'ss',
	'Ä'=>'A','Ö'=>'O','Ü'=>'U',
	//french
	'û'=>'u','ÿ'=>'y','â'=>'a','æ'=>'ae','ç'=>'c','ê'=>'e','ë'=>'e','ï'=>'i','î'=>'i','ô'=>'o','œ'=>'oe',
	'Û'=>'U','Ÿ'=>'Y','Â'=>'A','Æ'=>'AE','Ç'=>'C','Ê'=>'E','Ë'=>'E','Ï'=>'I','Î'=>'I','Ô'=>'O','Œ'=>'OE',
	//czech
	'ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z','á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n','ó'=>'o','ř'=>'r','š'=>'s','ť'=>'t',
	'Ú'=>'U','Ů'=>'U','Ý'=>'Y','Ž'=>'Z','Á'=>'A','Č'=>'C','Ď'=>'D','É'=>'E','Ě'=>'E','Í'=>'I','Ň'=>'N','Ó'=>'O','Ř'=>'R','Š'=>'S','Ť'=>'T',
	//italian
	'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
	'À'=>'A','È'=>'E','Ì'=>'I','Ò'=>'O','Ù'=>'U',
	//polish
	'ą'=>'a','ć'=>'c','ę'=>'e','ł'=>'l','ń'=>'n','ś'=>'s','ź'=>'z','ż'=>'z',
	'Ą'=>'A','Ć'=>'C','Ę'=>'E','Ł'=>'L','Ń'=>'N','Ś'=>'S','Ź'=>'Z','Ż'=>'Z',
	//spanish
	'ñ'=>'n','Ñ'=>'N',
	//swed/danisch/dutch/fin/nor
	'å'=>'a','ø'=>'o',
	'Å'=>'A','Ø'=>'O',
	//hungarian
	'ő'=>'o','ű'=>'u',
	'Ő'=>'O','Ű'=>'U',
	);

	return preg_replace('/[^(\x20-\x7F)]*/','', strtr($s, $accents));
}


function utf8_htmlspecialchars($s)
{
	return htmlspecialchars($s, ENT_COMPAT, 'UTF-8');
}

function startsWith($s, $substr)
{
	return (strpos($s, $substr) === 0);
}

?>