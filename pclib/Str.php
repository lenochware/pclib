<?php
/**
 * @file
 * String utilities.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib;
use pclib;

/**
 * String utilities for utf-8 strings.
 * PHP mbstring extension is required.
 * Example of usage: print Str::upper("Hello world");
 */
class Str {

const ALPHANUM = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
const ALPHASPEC = self::ALPHANUM . '!@#$%^&*()-_=+[]{}|;:\,.<>/?';

/** 
 * Replace {param} placeholders in string with values from array $param.
 * Example: print Str::format("Hello {name}", ['name' => 'World']);
 * If $params is two-dimensional array of n-rows, $str is written for each row.
 *
 * @param string $str string with {param} placeholders
 * @param array $params array of PARAM=>VALUE or array of rows
 * @param bool $keepEmpty Keep (don't delete) tags undefined in $param
 * @return string $str
 */
static function format($str, array $params, $keepEmpty = false)
{
	preg_match_all("/{([a-z0-9_.]+)}/i", $str, $found);
	if (!$found[1]) return $str;
	if (!is_array(array_get($params, 0))) $params = array($params);

	$n = count($params);
	$newstr = '';
	for ($i = 0; $i < $n; $i++) {
		$from = $to = null;
		foreach($found[1] as $key) {
			if ($keepEmpty and !isset($params[$i][$key])) continue;
			$from[] = '{'.$key.'}';
			$to[] = $params[$i][$key];
		}
		$newstr .= str_replace($from, $to, $str);
	}
	return $newstr;
}

/**
 * Extract part of the filesystem path.
 * Example: Str::extractPath($path, "%f.%e"); //extract "filename.extension"
 *
 * @param string $path Filesystem path
 * @param string $format Use following placeholders: %d for directory, %f filename, %e extension.
 * @return string $path
 */
static function extractPath($path, $format)
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
 * Create html tag.
 * Example: Str::htmlTag('a', ['href' => 'www.example.com'], 'Example');
 *
 * @param string $name Name of the tag
 * @param string $attr Tag attributes
 * @return string $content Content of the tag
 */
static function htmlTag($name, array $attr = [], $content = null)
{
	return (new pclib\Tpl)->htmlTag($name, $attr, $content);
}

/**
 * Return random string with $size characters.
 *
 * @param int $size Number of characters
 * @param string $characters Source characters (You can use Str::ALPHASPEC or Str::APLHANUM constants)
 * @return string $str Random string
 */
static function random($size = 16, $characters = self::ALPHANUM)
{
	$s = '';
	$max = mb_strlen($characters, '8bit') - 1;
	for ($i = 0; $i < $size; ++$i) {
		$s .= $characters[mt_rand(0, $max)];
	}
	return $s;
}

/**
 * Match $pattern on $str and return result or empty string.
 *
 * @param string $str
 * @param string $pattern Regexp pattern
 * @return string $str Matched string
 */
static function match($str, $pattern)
{
    preg_match($pattern.'u', $str, $matches);

    if (! $matches) {
        return '';
    }

    return $matches[1] ?? $matches[0];
}

/**
 * Match $pattern on $str and return all results as array.
 *
 * @param string $str 
 * @param string $pattern Regexp pattern
 * @return array $results
 */
static function matchAll($str, $pattern)
{
    preg_match_all($pattern.'u', $str, $matches);

    if (empty($matches[0])) {
        return [];
    }

    return $matches[1] ?? $matches[0];
}

/**
 * Replace all occurences of $search in $str with $replace.
 *
 * @param string $search Regexp pattern
 * @param string $str
 * @param string $replace
 */
static function replace($str, $search, $replace)
{
	return preg_replace($search.'u', $replace, $str);
}

/** @see mb_substr() */
static function substr($str, $start, $length = null)
{
	return mb_substr($str, $start, is_null($length)? mb_strlen($str, 'UTF-8') : $length, 'UTF-8');
}

/** @see mb_strpos() */
static function strpos($str, $search, $offset = 0)
{
	return mb_strpos($str, $search, $offset, 'UTF-8');
}

/** @see mb_strlen() */
static function length($str)
{
	return mb_strlen((string)$str, 'UTF-8');
}

/** @see mb_strtoupper() */
static function upper($str)
{
	return mb_strtoupper($str, 'UTF-8');
}

/** @see mb_strtolower() */
static function lower($str)
{
	return mb_strtolower($str, 'UTF-8');
}

/** @see str_pad() */
static function lpad($str, $length, $pad = ' ')
{
  $short = max(0, $length - mb_strlen($str));
  return mb_substr(str_repeat($pad, $short), 0, $short).$str;
}

/** @see str_pad() */
static function rpad($str, $length, $pad = ' ')
{
  $short = max(0, $length - mb_strlen($str));
  return $str.mb_substr(str_repeat($pad, $short), 0, $short);
}

/** Return true if  $str starts with $search. */
static function startsWith($str, $search)
{
	return (strpos($str, $search) === 0);
}

/** Return true if  $str ends with $search. */
static function endsWith($str, $search)
{
	$end = substr($str, -strlen($search));
	return ($search === $end);
}

/**
 * Return true if $str contains $search.
 *
 * @param string $str
 * @param string $search
 * @param bool $ignoreCase
 * @return bool $result
 */
static function contains($str, $search, $ignoreCase = false)
{
    if ($ignoreCase) {
        $str = mb_strtolower($str);
        $search = mb_strtolower($search);
    }

    if (strpos($str, $search) !== false) {
        return true;
    }

    return false;
}

/**
 * Transform string $str into url-ready form 'some-string-1234'.
 * @see Str::id()
 */
static function webalize($str)
{
	return Str::id($str, '\w-', '-');
}

/**
 * Remove diacriticts and non-alphanum characters from $str, convert to lower-case and replace spaces with '_'.
 *
 * @param string $str Source string
 * @param string $preserve Which characters should be preserved (regex)
 * @param $separator Separator of words, '_' by default
 * @param bool $lower Make string lower-case?
 */
static function id($str, $preserve = '\w', $separator = '_', $lower = true)
{
	$str = Str::ascii($str);
	if ($lower) $str = strtolower($str);
	$words = preg_split("/\s+/", $str);
	return Str::replace(implode($separator, $words), '~[^'.$preserve.']+~', '');
}

/**
 * Replace characters with diacritics to plain ascii characters.
 */
static function ascii($str)
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

	return preg_replace('/[^(\x20-\x7F)]*/','', strtr($str, $accents));
}

/** @see htmlspecialchars() */
static function htmlspecialchars($str)
{
	return htmlspecialchars((string)$str, ENT_COMPAT, 'UTF-8');
}


}