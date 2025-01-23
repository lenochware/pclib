<?php
/**
 * @file
 * Debugger class
 *
 * @author -dk- <lenochware@gmail.com>
 * @link https://pclib.brambor.net/
 * @license MIT (https://opensource.org/licenses/MIT)
 */

namespace pclib;
use pclib;

/**
 * @class Debugger
 *  Provides API for variable dumps, stack trace and profiling.
 */
class Debugger extends system\BaseObject implements IService
{

/** Html or plaintext output.*/
public $useHtml = true;

/** Maximum level of vardump. \see getdump() */
public $maxLevel = 4;

public $showPrivate = true;
public $showTips = true;
public $showSource = false;

public $INDENT_WIDTH = 2;

protected $profile;

/** Colors for html output.*/
public $colors = array('string' => 'red', 'number' => 'blue', 'keyword' => 'brown', 'other' => 'green');

function __construct()
{
	parent::__construct();
	if (version_compare(phpversion(), "5.3.0", "<")) $this->showPrivate = false;
}

//More unique uniqid()
protected function uniqId()
{
	static $id = 1;

	return 'd-'.($id++);
}

//VARDUMP

/**
 * Return metadata of the $variable.
 * It is called recursively for nested variables.
 * @see getdump()
 */
protected function dumpArray($name, $variable, $level = 0)
{
	$type = gettype($variable);
	$meta = array(
	'id' => $this->uniqId(),
	'name' => $name,
	'type' => $type,
	'level' => $level,
	'value' => $this->stringify($variable),
	);
	if ($level + 1 > $this->maxLevel) return $meta;
	
	if (is_array($variable)) {
		$meta['indexed'] = true;
		$meta['nested'] = false;
		$meta['printsize'] = 0;
		$i = 0; foreach($variable as $k=>$v) {
			if ($k !== $i++) $meta['indexed'] = false;
			if(is_array($v) or is_object($v)) $meta['nested'] = true;
			else $meta['printsize'] += strlen((string)$v)+1;
		}

		$meta['nodes'] = $this->getArray($variable, $level+1);
	}
	elseif(is_object($variable))
		$meta['nodes'] = $this->getObject($variable, $level+1);

	return $meta;
}

/**
 * Return metadata of the array $variable.
 * @see dumparray()
 */
protected function getArray($variable, $level)
{
	$nodes = array();
	foreach($variable as $key => $value) {
		$node = $this->dumpArray($key, $value, $level);
		$node['keytype'] = gettype($key);
		$node['name'] = $this->stringify($key, false);
		$nodes[] = $node;
	}
	return $nodes;
}

/**
 * Return metadata of the object $variable.
 * @see dumparray()
 */
protected function getObject($variable, $level)
{
	$nodes = array();
	$rc = new \ReflectionClass($variable);
	$props = $rc->getProperties();
	foreach ($props as $prop) {
		$private = $prop->isPrivate()? 1 : ($prop->isProtected()? 2 : 0);
		if ($private and !$this->showPrivate) continue;
		if ($private) $prop->setAccessible(true);
		
		$node = $this->dumpArray($prop->getName(), $prop->getValue($variable), $level);
		$node['private'] = $private;
		$nodes[] = $node;
	}
	return $nodes;
}

/**
 * Return simple dump of indexed array $nodes -- [one,two,three,...].
 * @see getdump()
 */
protected function strSimpleArray(array $nodes = null)
{
	if (!$nodes) return "\n";
	foreach($nodes as $node) { $html[] = $node['value']; }
	return " [".implode(',',$html)."]\n";
}

/**
 * Make clickable/expandable group around $content.
 */
protected function spanBox($id, $title, $content, $opened = true, $css_title = 'cursor:pointer')
{
	$css_dots = $css_content = '';

	if ($opened) {
		$css_dots = "style=\"display:none\"";
	}
	else {
		$css_content = "style=\"display:none\"";
	}

	return "<span style=\"$css_title\" onclick=\"var e=document.getElementById('$id');e.nextSibling.style.display=e.style.display;e.style.display=(e.style.display=='none'?'inline':'none');\">$title</span><span id=\"$id\" $css_content>$content</span><span $css_dots> ...\n</span>";
}

/**
 * Convert metadata to html/text output recursively
 * @see getdump()
 */
protected function strDump(array $meta, array $options = array())
{
	$html = '';
	$lpad = str_repeat(' ',$this->INDENT_WIDTH*$meta[0]['level']);
	foreach($meta as $node) {
		$html .= $lpad;
		$name = $node['name'];
		$value = $node['value'];
		$nodes = array_get($node, 'nodes');
		$opened = isset($options['opened'])? $options['opened'] : ($node['type'] == 'array');


		if (array_get($node, 'indexed') and !$node['nested'] and $node['printsize'] < 500) {
			$html .= $name.': '.$value;
			$html .= $this->strSimpleArray($nodes);
			continue;
		}
		
		if ($nodes) {
			$fmt = ($node['type'] == 'array')? " [\n%s$lpad]\n":" {\n%s$lpad}\n";
			$s = sprintf($fmt,$this->strDump($nodes, $options));
			if ($this->useHtml) $html .= $this->spanBox($node['id'], $name.': '.$value, $s, $opened);
			else $html .= $name.': '.$value.$s;
		}
		else {
			if ($this->useHtml and $node['type'] == 'string' and substr_count($value,"\n")>4)
				$html .= $this->spanBox($node['id'], $name.': ', $value, $opened)."\n";
			else
				$html .= $name.': '.$value."\n";
		}
	}
	return $html;
}

/**
 * Return variable dump as html/plaintext. It is advanced version of var_dump().
 * Typically you will use pclib shortcut function dump(), which is wrapper of this method.
 * Switch text/html output with $app->debugger->usehtml = true/false;
 * @params $variables (array of variables)
 * @return string $html
 */
function getDump(array $variables, array $options = array())
{
	$s = '';
	foreach($variables as $i => $variable) {
		$meta = $this->dumpArray('var'.($i+1), $variable);
		$s .= $this->strDump(array($meta), $options);
	}
	if ($this->useHtml) $s = "<pre class=\"debug-dump\">$s</pre>";
	return $s;
}

/**
 * Return string representation of variable $v.
 */
protected function stringify($v, $colorize = true)
{
	$type = gettype($v);
	switch($type) {
		case 'string':
			if ($this->useHtml) $v = htmlspecialchars($v);
			$s = '"'.$v.'"';
		break;
		case 'NULL':    $s = 'null'; break;
		case 'boolean': $s = $v? 'true':'false'; break;
		case 'array':   $s = ($n = count($v))? "array($n)" : "array()"; break;
		case 'object':  $s = 'object('.get_class($v).')'; break;
		case 'resource': $s = 'resource('.get_resource_type($v).')'; break;
		default: $s = $v;
	}
	if ($this->useHtml and $colorize) $s = $this->colorize($s, $type);
	return $s;
}

protected function colorize($s, $type)
{
	$trans = array(
		'integer'=>'number','double'=>'number','boolean'=>'other',
		'NULL'=>'other','array'=>'keyword','object'=>'keyword','string'=>'string'
	);
	$color = $this->colors[$trans[$type]];
	return "<span style=\"color:$color\">$s</span>";
}

//SOURCE

/**
 * Return part of source code at line $line in file $filename.
 * You can enable showing source code in debugger messages by setting
 * $app->debugger->showsource = true;
 */
function getSource($fileName, $line, $width = 3)
{
	$s = '';
	$source = file($fileName);
	for ($i = $line - $width; $i <= $line + $width; $i++) {
		$s .= (($i == $line)? '->':'  ').$source[$i-1];
	}
	if ($this->useHtml) $s = "<pre class=\"debug-source\" style=\"background:#eee\">$s</pre>";
	return $s;
}

//TRACE

/**
 * Convert absolute filesystem path to relative from webroot
**/
private function relpath($path)
{
 $webroot = str_replace($_SERVER['SCRIPT_NAME'], '', $_SERVER['SCRIPT_FILENAME']);
 return strtr(substr((string)$path, strlen($webroot)), "\\", "/");
}


/**
 * Return stack-trace array of an exception or place where it is called.
 * @see gettrace()
 * @return array $strace
 */
protected function traceArray($e = null)
{
	$ret = array();
	if ($e) {
		$a = array(
			'line' => $e->getLine(),
			'file' => $e->getFile(),
			'args' => array($e->getMessage()),
			'function' => get_class($e),
		);
		$strace = array_merge(array($a), $e->getTrace());
	}
	else $strace = debug_backtrace();

	$maxlen = array(0,0);
	foreach(array_reverse($strace) as $call) {

		if (!isset($call['line'])) $call['line'] = null;
		if (!isset($call['file'])) $call['file'] = null;
		if (!isset($call['args'])) $call['args'] = [];

		if (isset($call['class'])) {
			if ($call['class'] == get_class($this)) break;
			$call['function'] = $call['class'].$call['type'].$call['function'];
		}
		$call['relpath'] = $this->relpath($call['file']);

		if($this->useHtml) {
			$call['function'] = '<b>'.$call['function'].'</b>';
			$call['line'] = $this->colorize($call['line'], 'integer');
		}

		$maxlen[0] = max($maxlen[0],strlen($call['relpath']));
		$maxlen[1] = max($maxlen[1],strlen($call['line']));

		$ret[] = $call;
	}

	$ret[0]['maxlen'] = $maxlen;
	return $ret;
}

/**
 * Return function arguments as string.
 * @see gettrace()
 */
protected function strArgs($args)
{
	if (!$args) return '';
	$ret = array();
	foreach($args as $arg) {
		$type = gettype($arg);
		$value = $this->stringify($arg);
		if ($this->useHtml and $this->showTips and ($type == 'array' or $type == 'object')) {
			$value = "<span title=\"".$this->simpleDump($arg)."\">$value</span>";
		}
		$ret[] = $value;
	}
	return implode(', ', $ret);
}


/**
 * Return stack-trace of an exception or place where it is called.
 * Switch text/html output with $app->debugger->usehtml = true/false;
 * @return string $html
 */
function getTrace($e = null)
{
	$nodes = $this->traceArray($e);
	$maxlen = $nodes[0]['maxlen'];

	$html = '';
	foreach($nodes as $node) {
		$html .= str_pad($node['relpath'], $maxlen[0]+1);
		$html .= str_pad($node['line'], $maxlen[1]+1);
		$html .= $node['function'].'('.$this->strArgs($node['args']).");\n";
	}

	return $this->useHtml? "<pre class=\"debug-trace\">$html</pre>" : $html;
}

/**
 * Return stack trace file:line information - ex: index.php:10 -> db.php:220.
 * Helper for errordump.
 */
function tracePath($levels = 100, $e = null)
{
	$path = array();
	foreach($this->traceArray($e) as $call) {
		$path[] = basename($call['file']).':'.$call['line'];
		if (!--$levels) break;
	}

	return implode(' -> ', $path);
}

/**
 * Print error message $message with stack trace and optionally part of source code
 * where error occured. Used in pclib error handlers and for function dump().
 */
function errorDump($message, $e = null)
{
	global $pclib;

	$s = $this->useHtml? '<meta charset="utf-8">':'';
	$s .= $message.' ';
	if ($this->useHtml) {
		$s .= $this->spanBox($this->uniqId(), $this->tracePath(2,$e),
			$this->getTrace($e), false, 'cursor:pointer;border-bottom:1px dotted black;'
		);
	}
	else {
		$s .= $this->tracePath(2,$e) ."\n\n".$this->getTrace($e)."\n";
	}

	if ($this->showSource) {
		$sav = $this->useHtml;
		$this->useHtml = false;
		$node = end($this->traceArray($e));
		$this->useHtml = $sav;
		$s .= $this->getSource($node['file'], $node['line']);
	}

	print $s;
}

function getHtmlErrorDump($e)
{
	global $pclib;

	return $this->spanBox($this->uniqId(), $this->tracePath(2,$e),
			$this->getTrace($e), false, 'cursor:pointer;border-bottom:1px dotted black;'
	);

}


/**
 * Do default php var_dump(). Helper for strargs().
 * @return string variable dump
 */
function simpleDump($var)
{
	$vs = '';

	if (is_object($var)) {
		$output = [];
		$reflection = new \ReflectionClass($var);
		foreach ($reflection->getProperties() as $property) {
			$property->setAccessible(true);
			$value = $property->getValue($var);
			$vs .=  $property->getName().': '.$this->stringify($value, false)."\n";
		}
	} elseif (is_array($var)) {
		$output = [];
		$i = 0;
		foreach ($var as $key => $value) {
			if ($i++ > 30) {
				$vs .= "...\n";
				break;
			}

			$vs .= $key.': '.$this->stringify($value, false)."\n";
		}
	}

	return htmlspecialchars($vs, ENT_QUOTES);
}

//PROFILING

/**
 * Log current time in ms with label $name.
 * Useful for profiling.
 * Parameter $in = 1/0 indicates beginning or end of time interval.
 * @see gettimelog()
 */
function timeLog($name, $in)
{
	$now = microtime(true)*1000;
	if ($in) {
		$this->profile['tmp'][$name] = $now;
		return;
	}
	$total = & $this->profile['total'][$name];
	if (!$total) $total = 0;
	$start = (float)$this->profile['tmp'][$name];
	$total += ($now - $start);
}

/**
 * Return profile table, collected by timelog() calls.
 */
function getTimeLog()
{
	print $this->getDump($this->profile['total']);
}


} //end class

?>