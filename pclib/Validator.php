<?php 
/**
 * @file
 * Validator with common validation rules.
 * @author -dk-
 * http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib;
use pclib;

/**
 * Validator with common validation rules.
 */
class Validator extends system\ValidatorBase
{
	const PATTERN_EMAIL = '/^[_\w\.\-]+@[\w\.-]+\.[a-z]{2,6}$/';
	const PATTERN_FILENAME = '/^[a-zA-Z0-9_.-]+$/';
	const PATTERN_IDENTIFIER = '/^[a-z_][a-z0-9_]+$/i';
	const PATTERN_TIME24 = '/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/';

	public $dateTimeFormat = '%d.%m.%Y %H:%M%:%S';

	function __construct($path = '')
	{
		parent::__construct($path);

		//naatavit primo do array?
		$this->setRule('email', array($this, 'isEmail'), "Invalid email address!");
		$this->setRule('url', array($this, 'isUrl'), "Invalid url!");
		$this->setRule('date', array($this, 'isDateTime'), "Invalid date!");
		$this->setRule('time', array($this, 'isTime'), "Invalid time!");
		$this->setRule('file', array($this, 'matchShell'), "Bad file type!");
		$this->setRule('pattern', array($this, 'matchPattern'), "Value does not match requested format!");
		$this->setRule('range', array($this, 'inRange'), 'Value is not in range [%4$s] !');
		$this->setRule('number', array($this, 'isNumeric'), "Not a number!");
		$this->setRule('integer', array($this, 'isNumericInt'), "Not an integer value!");
		$this->setRule('minlength', array($this, 'minLength'), 'Minimum %4$s characters required!');
	}

	/** Rule handler: Match regexp pattern. */
	function matchPattern($value, $pattern)
	{
		return (bool)preg_match('~^'.$pattern.'$~', $value);
	}

	/** Rule handler: Email address. */
	function isEmail($value)
	{
		return (bool)filter_var($value, FILTER_VALIDATE_EMAIL);
	}

	/** Rule handler: URL starting http://. */
	function isUrl($value)
	{
		return (bool)filter_var($value, FILTER_VALIDATE_URL);
	}

	/** Rule handler: Match php identifier. */
	function isIdentifier($value)
	{
		return (bool)preg_match(self::PATTERN_IDENTIFIER, $value);
	}

	/** Rule handler: Match integer. */
	function minLength($value, $length)
	{
		return (utf8_strlen($value) >= $length);
	}

	/** Rule handler: Match wildcards. */
	function matchShell($value, $wildcards)
	{
		if ($wildcards == 1) $wildcards = '*';
		$wildcards = explode(';', $wildcards);

		foreach ($wildcards as $wildcard) {
			if (fnmatch($wildcard, $value)) return true;
		}

		return false;
	}

	/** Rule handler: Match number or numeric string. */
	function isNumeric($value)
	{
		return is_numeric($value);
	}
	
	/** Rule handler: Match integer. */
	function isNumericInt($value)
	{
		return (bool)filter_var($value, FILTER_VALIDATE_INT);
	}

	/** Rule handler: Check if value is in range [min, max]. */
	function inRange($value, $range)
	{
		if (!is_array($range)) $range = $this->parseRange($range);
		return ($range[0] <= $value and $range[1] >= $value);
	}

	protected function parseRange($s)
	{
		$a = explode('..', $s);
		if ($a[0] == '') $a[0] = -INF;
		if ($a[1] == '') $a[1] = INF;

		return array((float)$a[0], (float)$a[1]);
	}

protected function parseDate($datestr, $format)
{
	$fmtspec = array('d','m','Y','H','M','S');
	$d = preg_split("/[^0-9]+/", $datestr, null, PREG_SPLIT_NO_EMPTY);
	$f = array_flip(preg_split("/[^a-z]+/i", $format, null, PREG_SPLIT_NO_EMPTY));
	$datearray = array();
	foreach($fmtspec as $i) {
		$datearray[] = isset($f[$i])? $d[$f[$i]] : strftime("%$i");
	}
	return $datearray;
}

/** Rule handler: Match datetime against format $format. */
function isDateTime($value, $format = '')
{
	if (!$format or $format == 1) $format = $this->dateTimeFormat;
	list($d,$m,$y,$h,$i,$s) = $this->parseDate($value, $format);
	if (!checkdate($m,$d,$y)) return false;
	if (isset($h) and !$this->isTime($h.':'.$i.':'.($s ?: '00'))) return false;
	return true;
}

/** Rule handler: Match time in format HH:MM:SS. */
function isTime($value)
{
	return (bool)preg_match(self::PATTERN_TIME24, $value);
}

}

?>