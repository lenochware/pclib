<?php
/**
 * @file
 * PClib template engine.
 *
 * @author -dk- <lenochware@gmail.com>
 * http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib;
use pclib;

/**
 * Template engine. Load template, populate it with values and display it.
 * Template is usually html file with template tags {TAGNAME}.
 * Features:
 * - blocks and conditions
 * - formatting of numbers, strings, dates etc.
 * - events (callback hooks)
 * - Some tag types such as links, lookup fields etc.
 * - Form and Grid implements a lot of new tag types (handlers) for their templates
 *
 * See \ref tpl-tags for description of implemented tags.
 */
class Tpl extends system\BaseObject
{

/** Occurs on template initialization. */
public $onInit;

/** Occurs after template is loaded and parsed. */
public $onLoad;

/** Occurs before output. */
public $onBeforeOut;

/** Occurs after output. */
public $onAfterOut;

/** Name of the template. */
public $name;

/** Array of elements loaded from <?elements ? > section */
public $elements = array();

/** Array of template values. */
public $values = array();

/* List of fields written into template if {tpl.fields} or {tpl.control} tag is used. */
protected $fields = array();

/**
 *  Name of the session variable where template values are stored.
 *  @see loadSession(), saveSession()
 */
protected $sessName;

/** var App Link to application object. */
protected $app;

/** var Db */
public $db;

/** var Translator */
public $translator;

/** var Router */
public $router;

/** Generate XHTML code. */
public $useXhtml = false;

/** Link to array of configuration parameters. */
protected $config = null;

/* Name of the 'class' element */
protected $className = 'tpl';

/* 'class' element of the template */
protected $header = array();

/** Document array - It contains parsed template. */
protected $document;

private $inBlock = array();

/** Function for escaping html in template values. */
public $escapeHtmlFunction;

/**
 * Load and parse template file.
 *
 * @param string $path Filename of template file
 * @param string $sessName When set, object is stored in session as $sessName
 */
function __construct($path = '', $sessName = '')
{
	global $pclib;

	parent::__construct();

	if (!$pclib->app) throw new RuntimeException('No instance of application (class app) found.');

	$this->app = $pclib->app;
	$this->config = $this->app->config;
	$this->escapeHtmlFunction = array($this, 'escapeHtml');

	$this->sessName = $sessName;
	$this->loadSession();

	if ($path) {
		$this->name = extractpath($path, '%f');
		$this->load($path);
		$this->init();
	}
}

protected function _init()
{
	$this->header =& $this->elements[$this->className];
	if($this->header['name']) $this->name = $this->header['name'];
	if (!$this->name) $this->name = $this->className;	
}

/**
 * Initialization - must be called after load()
 */
function init()
{
	$this->_init();
	$this->onInit();
}

private function getAccessor($name) {
	return new Tpl_Accessor($this, $name);
}

function __get($name)
{
	if ($name{0} == '_')
		return $this->getAccessor(substr($name,1));
	else
		return parent::__get($name);
}

function __set($name, $value)
{
	if ($name{0} == '_')
		$this->values[substr($name,1)] = $value;
	else
		parent::__set($name, $value);
}

protected function hasType($name)
{
	return method_exists($this, 'print_'.$name);
}

/**
 * Load template file.
 * @param string $path filename of template file
 * @see loadString()
 */
function load($path)
{
	if (!file_exists($path)) throw new FileNotFoundException("File '$path' not found.");
	$tpl_string = file_get_contents ($path);
	$this->loadString ($tpl_string);
	$this->onLoad($path);
}

/**
 * Load string $s as template.
 * Useful, if you need read template from another source than filesystem.
 * @param string $s string containing template source
 */
function loadString($s)
{
	$tag_start = '<?elements';
	$tag_end = '?'.'>';

	$start = strpos($s, $tag_start);
	$end = strpos($s, $tag_end);
	if ($start === false or $end === false or $end < $start) {
		$template_def = $s;
	}
	else {
		$elements_def = substr($s, $start + strlen($tag_start), $end-$start-strlen($tag_start));
		$this->parseElements($elements_def);
		$template_def = trim(substr($s, $end-$start+strlen($tag_end)),"\r\n");
	}

	$this->parseTemplate($template_def);

	return true;
}

protected function _out($block = null)
{
	ob_start();
	$this->print_BlockRow($block?$block:'pcl_document');
	ob_end_flush();
}

/**
 * Display template populated with content.
 * Replace all tags in template with #$values, perform any formatting
 * and callback functions for the #$elements and write output.
 * @param string $block If set, only block $block will be printed.
 */
function out($block = null)
{
	$this->onBeforeOut();
	$this->_out();
	$this->onAfterOut();
}

/**
 * Return html output of the template populated with content.
 * @param string $block If set, only html of the block $block will be returned.
**/
function html($block = null)
{
	ob_start();
	$this->out($block);
	$html_code = ob_get_contents ();
	ob_end_clean();
	return $html_code;
}

function __toString()
{
	try {
		return $this->html();
	} catch (Exception $e) {
		trigger_error($e->getMessage(), E_USER_ERROR);
	}
}

/**
 * Enable (show) or disable (hide) tag or block $name.
 * $tpl->enable('tag');
 * $tpl->enable('tag1','tag2');
 * $tpl->enable('tag', false);
 *
 * @param array|list of tag names
 * @param bool $val Enable? true|false
 */
function enable()
{
	$args = func_get_args();
	$val = (end($args) === false)? 1:0;
	if (is_array($args[0])) $args = $args[0];
	foreach($args as $name) {
		if ($name) $this->elements[$name]['noprint'] = $val;
	}
}

/**
 * Disable (hide) tag or block $name.
 *
 * @param array|list of tag names
 */
function disable()
{
	$args = func_get_args();
	if (is_array($args[0])) $args = $args[0];
	$this->enable($args, false);
}

/**
 * Set attributes for template elements.
 * $keystr can be 'element/attribute' or 'block/element/attribute'. If you
 * need set all elements in block, you can use wildcard: 'block/ * /attribute'.
 * Also you can select elements with some attr. value: 'attr=value/attribute'
 *
 * @param string $keystr Attribute specification (with wildcards)
 * @param string $value Value assigned to attribute.
 */
function set($keystr, $value)
{
	$key = explode('/',$keystr);
	if (count($key) == 3) $block = array_shift($key);
	$id = $key[0];
	$attr = $key[1];
	$sattr = false;
	if (strpos($id,'=')) list($sattr,$svalue) = explode('=', $id);

	if (!$sattr and $id != '*') {
		$this->elements[$id][$attr] = $value;
		return;
	}

	foreach($this->elements as $id => $tmp) {
		if ($block and !$this->isInBlock($id,$block)) continue;
		if ($sattr and $this->elements[$id][$sattr] != $svalue) continue;
		$this->elements[$id][$attr] = $value;
	}
}

/* Check if element $id is in $block */
protected function isInBlock($id, $block)
{
	while ($id = $this->elements[$id]['block']) {
		if ($id == $block) return true;
	}
	return false;
}

/**
 * Return attribute of element $id.
 * Perform cascading search of attribute value - if attr is not found in element
 * it will look in element's block and template header.
 * @param string $id Element Id
 * @return string $val Element value
**/
protected function getAttr($id, $attr)
{
	$e = $this->elements[$id];
	if (isset($e[$attr])) return $e[$attr];

	while ($id = $this->elements[$id]['block']) {
		if (isset($this->elements[$id][$attr]))
			return $this->elements[$id][$attr];
	}
	if (isset($this->header[$attr])) return $this->header[$attr];
	return null;
}

function getBlock($block)
{
	$values = array();
	foreach($this->elements as $id => $tmp) {
		if (!$this->isInBlock($id, $block) or $tmp['type'] == 'block') continue;
		$bid = $id;
		while ($bid = $this->elements[$bid]['block']) {
			$value = $this->values[$bid][$id];
			if (isset($value)) break;
		}
		$values[$id] = isset($value)? $value : $this->values[$id];
	}
	return $values;
}

/**
 * Return value of element $id.
 * @param string $id Element Id
 * @return string $val Element value
**/
function getValue($id)
{
	$elem = $this->elements[$id];
	if ($elem['loop']) return $this->compute($id);
	if ($elem['field']) $id = $elem['field'];
	foreach ($this->inBlock as &$block) {
		$rowno = $this->elements[$block]['rowno'];
		$value = isset($rowno)? $this->values[$block][$rowno][$id] : $this->values[$block][$id];
		if (isset($value)) break;
	}
	if (!isset($value)) $value = $this->values[$id];
	return (is_numeric($value) or $value)? $value : $elem['default'];
}

/** Return row number of the current block. */
protected function getRowNo()
{
	foreach ($this->inBlock as &$block) {
		$rowno = $this->elements[$block]['rowno'];
		if (isset($rowno)) return (int)$rowno;
	}
	return 0;
}

/**
 * Load object from session.
**/
function loadSession() {}

/**
 * Save object to session.
**/
function saveSession() {}

/**
 * Remove object from session.
**/
function deleteSession()
{
	if (!$this->sessName) return;
	$this->app->deleteSession($this->sessName);
}

/** Try return array of fieldnames from query/table/elements */
protected function getFields($dsstr = null)
{
	if ($this->fields) return $this->fields;
	if ($dsstr) return array_filter(array_keys((array)$this->service('db')->select($dsstr)),'is_string');
	return array_keys($this->elements);
}

/**
 * Build default template from file assets/def_*.tpl and uses it.
 * Show values in simple table layout. Generated template is based on database table.
 * Ex: $form->create('select * from Orders');
 * @param string $dsstr Datasource string ( see db->select() )
 * @param string $fileName If present, it will save generated template as $filename for future use
 * @param string $template If present, used instead of 'assets/def_*.tpl' as template generator
**/
function create($dsstr, $fileName = null, $template = null)
{
	$trans = array('<:' => '<', ':>' => '>', '{:' => '{', ':}' => '}');
	if (!$template) $template = PCLIB_DIR.'assets/def_tpl.tpl';

	$fields = $this->getFields($dsstr);
	foreach($fields as $id) {
		$elem .= "string $id lb \"$id\"\n";
		$body[] = array(
		'LABEL' => '{'.$id.'.lb}',
		'FIELD' => '{'.$id.'}',
		);
	}

	$t = new Tpl($template);
	$t->values['NAME'] = $this->service('db')->tableName($dsstr);
	$t->values['BODY'] = $body;
	$t->values['ELEMENTS'] = trim($elem);
	$html = strtr($t->html(), $trans);

	if ($fileName) {
		$ok = file_put_contents($fileName, $html);
		if (!$ok) throw new IOException("Cannot write file $fileName.");
		else @chmod($fileName, 0666);
	}
	else {
		$this->loadString($html);
		$this->init();
	}
}

/** Return computed value of element $id. */
function compute($id)
{
	$items = $this->elements[$id]['items'];
	if (!$items) {
		$items = explode(',', $this->elements[$id]['loop']);
		if (count($items)) $this->elements[$id]['items'] = $items;
	}
	return $items[$this->getRowNo() % count($items)];
}

/**
 * This function is called for each template tag when it is printed.
 * You can redefine this function in descendant and add your own element types.
 *
 * @copydoc tag-handler
 */
function print_Element($id, $sub, $value)
{
	$elem = $this->elements[$id];
	if ($sub == 'lb') {
		print $elem['lb']? $elem['lb'] : $id;
		return;
	}
	elseif ($sub == 'value') {
		print $value;
		return;
	}

	if ($this->config['pclib.security']['tpl-escape'] and !$elem["noescape"] and is_string($value)) {
		$value = $this->escapeHtmlFunction($value);
	}

	switch ($elem["type"]) {
		case "number":
			$this->print_Number($id,$sub,$value);
			break;
		case "string":
			$this->print_String($id,$sub,$value);
			break;
		case "bind":
			$this->print_Bind($id,$sub,$value);
			break;
		case "link":
			$this->print_Link($id,$sub,$value);
			break;
		case 'env':
			$this->print_Env($id,$sub,$value);
			break;
		case 'class':
			$this->print_Class($id,$sub,null);
			break;
		case 'include':
		case 'action':
			$this->print_Action($id,$sub,null);
			break;

		default:
			print $value;
			break;
	}
}

function print_Empty($id, $sub = null) {}

/**
 * Print numeric $value. Perform numeric formatting.
 * @copydoc tag-handler
 */
function print_Number($id, $sub, $value)
{
	$f = $this->elements[$id]["format"];
	if ($f and is_numeric($value))
		$value = number_format($value, $f[0], $f[1], $f[2]);
	print $value;
}

/**
 * Print string $value. It supports string formatting, string crop,
 * date formating, tooltip etc. See \ref tpl-tags for details.
 * @copydoc tag-handler
 */
function print_String($id, $sub, $s)
{
	$elem = $this->elements[$id];

	//format database date...
	if (isset($elem['date']))
		$s = $this->formatDate($s, $elem['date']);

	if (isset($elem['size'])) {
		if (!isset($elem['endian'])) $elem['endian'] = '...';
		if(utf8_strlen($s) > $elem['size'] + 2/*add length of endian*/) {
			if ($elem['tooltip'])
				$title = utf8_htmlspecialchars($s);
			$s = utf8_substr($s, 0, $elem['size']) . $elem['endian'];
		}
	}

	if ($elem['format'])
		$s = $this->formatStr($s, $elem['format']);

	if ($title)
		$s = "<span title=\"$title\">$s</span>";

	print $s;
}

/**
 * Bind $value to LABEL coming from datasource and print LABEL. Datasource can
 * be specified using attributes list, query or lookup. See \ref common-attrs
 * for details.
 * @copydoc tag-handler
 */
function print_Bind($id, $sub, $value)
{
	$items = $this->getItems($id);
	$elem = $this->elements[$id];

	if ($elem['bitfield']) {
		$i = 0;
		$checked = array();
		while (true) {
			$bit = pow(2,$i++);
			if ($value & $bit) $checked[] = $items[$i];
			if ($bit > $value or $i > 64) break;
		}
		$value = implode (', ',$checked);
	}
	else {
		if(isset($items[$value])) $value = $items[$value];
		elseif(isset($value,$items['*'])) $value = $items['*'];
		else $value = $items[$elem['default']];
	}

	if (!isset($value)) $value = $elem['emptylb'];
	$this->print_String($id,$sub,$value);
}

/**
 * Create html link. You can use 'popup' for popup window.
 * @copydoc tag-handler
 */
function print_Link($id, $sub, $value)
{
	$elem = $this->elements[$id];
	$url = $this->getUrl($elem);

	if ($sub == 'url') {
		print $url;
		return;
	}
	if ($sub == 'js') {
		if ($elem['popup'])
			$js = $this->getPopup($id, $elem['popup'], $url);
		else
			$js = "window.location='$url';";
		print $js;
		return;
	}

	if ($elem['img']) {
		$lb = "<img src=\"{$elem['img']}\" class=\"link\" alt=\"{$elem['lb']}\" title=\"{$elem['lb']}\"".($this->useXhtml? ' />' : '>');
	}
	else {
		$lb = $elem['lb'];
	}

	if (!$url) {print $lb; return; }

	if ($elem['popup'])
		$url = $this->getPopup($id, $elem['popup'], $url);

	if ($elem['field'] and !$lb) {
		ob_start();
		$this->print_Element($elem['field'],$sub,$value);
		$lb = ob_get_contents();
		ob_end_clean();
	}
	elseif (!$lb)
		$lb = (string)$value;

	$tag = array('href' => $url, '__attr' => $elem['attr']);
	if ($elem['html']) $tag += $elem['html'];
	print $this->htmlTag('a', $tag, $lb);
}

/**
 * Print value from url (from _GET array).
 * Use in template like this: {GET.variable}.
 * @copydoc tag-handler
 */
function print_Env($id, $sub, $value)
{
	if (!$sub) print $_SERVER['QUERY_STRING'];
	else print $_GET[$sub];
}

/**
 * Print all fields into template.
 * It uses simple table layout.
 * Type {tpl.fields} or {tpl.control} into template.
 * @copydoc tag-handler
 */
function print_Class($id, $sub, $value)
{
	$ignore_list = array('class','block','pager','sort','button');

	if ($id != $this->className) return;

	$fields = $this->getFields();
	foreach($fields as $id) {
		$elem = $this->elements[$id];
		if ($elem['noprint'] or $elem['skip'] or in_array($elem['type'], $ignore_list)) continue;
		$this->print_Class_Item($id, $sub);
	}
}

/**
 * Call controller's method and include result into template.
 * Example: action comments route "comments/list/id:{id}" will call method
 * CommentsController::listAction($id)
 * @copydoc tag-handler
 */
function print_Action($id, $sub, $value)
{
	$rs = $this->replaceParams($this->elements[$id]['route']);
	$action = new Action($rs);
	$ct = $this->app->newController($action->controller);
	if (!$ct) {
		printf($this->t('Page not found: "%s"'), $action->controller);
	}
	else print $ct->run($action);
}

/**
	* Implements {tpl.fields} placeholder.
	* @see print_class();
	* @copydoc tag-handler
	*/
protected function print_Class_Item($id, $sub)
{
	print "<tr><td class=\"$id\">";
	$this->print_Element($id, 'lb', null);
	print '</td><td>';
	$value = $this->getValue($id);
	if (!$this->fireEventElem('onprint', $id, '', $value))
		$this->print_Element($id, '', $value);
	print '</td></tr>';
}

/**
 * Print template block. Block is template section marked with:
 * @code
 * {block name}
 * html code...
 * {/block}
 * @endcode
 * Block is treated like normal template element. It's posible hide whole block
 * (noprint) or repeat block n-times (repeat "n"). \b Warning! It's sharing
 * namespace with another elements, so his name must be unique.
 * @copydoc tag-handler
 */
function print_Block($block)
{
	$b = $this->elements[$block];
	if (!$b) return;

	if ($b['if'] and !$this->getValue($b['if'])) return;
	if ($b['ifnot'] and $this->getValue($b['ifnot'])) return;

	array_unshift($this->inBlock, $block);

	if ($b['repeat']) $count = $b['repeat'];
	else $count = $this->values[$block][0]? count($this->values[$block]):0;

	if ($count) {
		for ($rowno = 0; $rowno < $count; $rowno++) {
			$this->print_BlockRow($block, $rowno);
		}
	}
	else {
		$this->print_BlockRow($block, null);
	}

	array_shift($this->inBlock);
}

protected function print_BlockRow($block, $rowno = null)
{
	$b = $this->elements[$block];

	if (is_scalar($this->values[$block])) {
		print $this->values[$block];
		return;
	}

	if ($this->values[$block]) {
		$begin = $b['begin'];
		$end = $b['else']? $b['else'] : $b['end'];
	}
	else {
		$begin = $b['else']? $b['else'] : $b['begin'];
		$end = $b['end'];
	}

	$this->elements[$block]['rowno'] = $rowno;

	for ($i = $begin; $i < $end; $i++) {
		$strip = $this->document[$i];

		if ($strip == TPL_ELEM) {
			 $strip = $this->document[++$i];
			 list($id,$sub) = explode('.', $strip);

			 if ($this->elements[$id]['noprint']) { $this->print_Empty($id, $sub); continue; }

			 $value = $this->getValue($id);

			 /*if ($func = $this->elements[$id]['print']) {
				 $go = $this->callback($func, $id, $sub, $value);
			 }*/
			 if (!$this->fireEventElem('onprint',$id,$sub,$value))
				 $this->print_Element($id, $sub, $value);
		}
		elseif ($strip == TPL_BLOCK) {
			$subblock = $this->document[++$i];
			if (!$this->elements[$subblock]) throw new \pclib\Exception('Template broken.');
			if (!$this->elements[$subblock]['noprint']) $this->print_Block($subblock);
			$i = $this->elements[$subblock]['end'] + 1;
		}
		else print $strip;
	}
}

/**
 * Generate javascript code for popup window
 *
 * @param string $attr popup attribute of element (Example: popup "600x400+100+100")
 * @param string $url url of page to open
 * @return string $js javascript window code
**/
function getPopup($id, $attr, $url)
{
		if ($attr == '1') $attr = '800x600';
		list($size,$attr) = explode(' ', $attr);
		switch ($attr) {
		case 'full': $poppar='toolbar=1,location=1,menubar=1,scrollbars=1,resizable=1';
		break;
		case 'min': $poppar = 'resizable=1';
		break;
		case 'none': $poppar = '';
		break;
		case 'max': default: $poppar = 'scrollbars=1,resizable=1';
		break;
		}

		if ($size) {
			list($w, $h, $l, $t) = sscanf($size, "%dx%d+%d+%d");
			if (!$l) $l = "'+((window.screen.width-$w)/2)+'";
			if (!$t) $t = "'+((window.screen.height-$h)/2)+'";
			$poppar .= ($poppar?',':'') . "left=$l,top=$t,width=$w,height=$h";
		}

		return "javascript:void(win_$id=window.open('$url','win_$id','$poppar'));win_$id.focus()";
}

protected function replaceParams($s)
{
	if (strpos($s,'{')) $s = preg_replace_callback (
		"/{([a-z0-9_.]+)}/i", array($this, 'callback_getvalue'), $s
	);
	return $s;
}

/** Return url for the element (button, link) with completed parameters. */
protected function getUrl($elem)
{
	$url = $elem['href']? $elem['href'] : $elem['action'];
	if ($url) return $this->replaceParams($url);

	if ($elem['route']) {
		$rs = $this->replaceParams($elem['route']);
		return $this->service('router')->createUrl($rs);
	}

	return false;
}

// add element definition
// $line has same format as line in section "elements" in common template
function addTag($line)
{
	$this->parseLine($line);
}

private function getDocument($def)
{
	$pat[0] = "/{([a-z0-9_.]+)}/i";
	$pat[1] = "/{(BLOCK|IF|IF NOT)\s+([a-z0-9_]+)}/i";
	$pat[2] = "%{/(BLOCK|IF)}%i";

	$rep[0] = TPL_SEPAR . TPL_ELEM  . TPL_SEPAR . '\\1' . TPL_SEPAR;
	$rep[1] = TPL_SEPAR . TPL_BLOCK . TPL_SEPAR . '\\2:\\1' . TPL_SEPAR;
	$rep[2] = TPL_SEPAR . TPL_BLOCK . TPL_SEPAR . 'END:\\1' . TPL_SEPAR;

	if ($this->config['pclib.compatibility']['tpl_syntax']) {
		$pat[3] = "/<!--\s*BLOCK\s+([a-z0-9_]+)\s*-->/i";
		$rep[3] = TPL_SEPAR . TPL_BLOCK . TPL_SEPAR . '\\1' . TPL_SEPAR;
	}

	return explode(TPL_SEPAR, preg_replace($pat, $rep, $def));
}

private function initBlocks()
{
	$this->elements['pcl_document'] = array(
		'type' => 'block',
		'begin'=> 0, 'end' => count($this->document)
	);

	$bstack = array(); $block = null;
	foreach ($this->document as $key=>$strip) {
		if ($strip == TPL_ELEM) {
			list($id,$sub) = explode('.',$this->document[$key+1]);
			if ($this->elements[$id] and !$this->elements[$id]['block'])
				$this->elements[$id]['block'] = $block;
		}

		if ($strip != TPL_BLOCK) continue;
		list($name,$type) = explode(':',$this->document[$key+1]);

		$type = strtoupper($type);
		$section = strtoupper($name);

		if ($section == 'END') {
			if ($block) $this->elements[$block]['end'] = $key;
			$block = array_pop($bstack);
		}
		elseif ($section == 'ELSE') {
			if ($block) $this->elements[$block]['else'] = $key + 2;
			$this->document[$key]   = null;
			$this->document[$key+1] = null;
		}
		else {
			array_push($bstack, $block);
			$block = $name;

			if ($type == 'IF') {
				$block = '__if'.$key;
				$this->elements[$block]['if'] = $name;
			}
			elseif($type == 'IF NOT') {
				$block = '__if'.$key;
				$this->elements[$block]['ifnot'] = $name;
			}

			$this->document[$key+1] = $block;
			if ($this->elements[$block]['begin']) {
				throw new Exception("Block name '%s' is already used.", array($block));
			}
			$this->elements[$block]['id']    = $block;
			$this->elements[$block]['type']  = 'block';
			$this->elements[$block]['block'] = end($bstack);
			$this->elements[$block]['begin'] = $key + 2;
			$this->elements[$block]['end'] = $this->elements['pcl_document']['end'];

		}
	}
}

/**
 * Parse template html-code and fill $document array
 *
 * @param string $def template html-code
 */
protected function parseTemplate($def)
{
	if ($this->translator and strpos('-'.$def,'<M>')) {
		$def = $this->translator->translateTags($def);
	}

	$this->document = $this->getDocument($def);
	$this->initBlocks();
}

/**
 * Parse template elements definition block and fill $elements array
 *
 * @param string $def elements definition block
 */
protected function parseElements($def)
{
	if (trim($def) == '') return;
	$this->elements = array();

	$def = str_replace('\"', '&quot;', $def);
	$lines = explode(EOL, $def);
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line == '') continue;
		$this->parseLine($line);
	}
}

/**
 * Parse element definition line and save element to $elements array.
 */
protected function parseLine($line)
{
	$terms = preg_split("/[\s]+/", $line);
	$type = array_shift($terms);
	$id = array_shift($terms);
	$this->elements[$id]['type'] = $type;
	$this->elements[$id]['id'] = $id;

	while ($term = array_shift($terms)) {
		$value = ($terms and $terms[0][0] == "\"")? $this->readQTerm($terms) : 1;
		if (strpos($term,'html_')===0) {
			if ($value === 1) $this->elements[$id]['html'][] = substr($term,5);
			else $this->elements[$id]['html'][substr($term,5)] = $value;
		}
		else
			$this->elements[$id][$term] = $value;
	}

	if ($this->elements[$id]['lb']) {
		$this->elements[$id]['lb'] = $this->t($this->elements[$id]['lb']);
	}

	return $id;
}

/** Read quoted value of attribute */
private function readQTerm(&$terms)
{
	$term = array_shift($terms);
	while ((substr($term, -1) != "\"" and count($terms)) or strlen($term) == 1) {
		$term .= " " . array_shift($terms);
	}
	return str_replace('&quot;', '"', substr($term, 1, -1));
}

function htmlTag($name, $attr = array(), $content = null, $startTagOnly = false)
{
	$html = '<'.$name;
	if($attr['__attr']) {
		$html .= ' '.$attr['__attr'];
		unset($attr['__attr']);
	}
	foreach($attr as $k => $v) {
		if (is_array($v)) $v = implode(' ', $v);
		if (is_numeric($k)) $html .= $this->useXhtml? " $v=\"$v\"" : " $v";
		elseif(strlen($v)) $html .= " $k=\"$v\"";
	}

	if ($startTagOnly) {
		return $html.'>';
	}
	elseif (!isset($content)) {
		return $html.($this->useXhtml? ' />' : '>');
	}
	else {
		return $html .'>'.$content."</$name>";
	}
}

function escapeHtml($s)
{
	return htmlspecialchars($s);
}

/**
 * DATABASE DATE => HUMAN DATE (in strftime() format)
 * @see strftime()
 */
protected function formatDate($dtstr, $fmt = '')
{
	if (!$fmt or $fmt == '1') $fmt = $this->config['pclib.locale']['date'];
	if (!$dtstr or substr($dtstr,0,10) == '0000-00-00') return '';
	list($y,$m,$d,$h,$i,$s) = sscanf($dtstr, "%d-%d-%d %d:%d:%d");
	if (checkdate ($m, $d, $y)) {
		if ($y < 1970) { //fix problem with unix epoch
			$trans = array("%Y" => $y, "%y" => substr($y,-2));
			$fmt = strtr($fmt, $trans);
			$y = "1980";
		}
		return strftime($fmt, mktime ($h, $i, $s, $m, $d, $y));
	}
	elseif (substr($dtstr,0,5) == 'today') {
		$tm = strtotime($dtstr);
		return strftime($fmt, $tm);
	}
	else return $dtstr;
}


/**
 * Format string $s according format $fmt
 */
protected function formatStr($s, $fmt)
{
	$len = strlen($fmt);
	for($i = 0; $i < $len; $i++) {
		switch ($fmt{$i}) {
		case "n": $s = nl2br($s, $this->useXhtml); break;
		case "h": $s = utf8_htmlspecialchars($s); break;
		case "H": $s = strip_tags($s); break;
		case "u": $s = utf8_strtoupper($s); break;
		case "l": $s = utf8_strtolower($s); break;
		case "s": $s = addslashes($s); break;
		case "f": $s = pcl_ident($s); break;
		}
	}
	return $s;
}

protected function toString($value) {
	if (is_object($value)) return '[object '.get_class($value).']';
	return is_array($value)? implode(',',$value) : (string)$value;
}

/**
 * Load lookup table for elements such as bind, select, check or radio.
 * Element must contains exactly one of following attributes:
 * list, query, lookup. See \ref common-attrs.
 * You can set array of items directly:
 * $t->_element->items = $items;
 * @param int $id identificator of element
 * @return array $items
 */
function getItems($id)
{
	$elem = $this->elements[$id];
	$items = $elem['items'];
	if (!isset($items)) {
		$items = array();
		if ($elem['list']) $items = $this->getLkpList($elem['list']);
		elseif ($elem['query'])  $items = $this->getLkpQuery($elem['query']);
		elseif ($elem['lookup']) $items = $this->getLkpLookup($elem['lookup']);
		elseif ($elem['datasource']) $items = $this->getDataSource($elem['datasource']);

		if ($this->translator) {
			$items = $this->translator->translateArray($items);
		}

		$this->elements[$id]['items'] = $items;
	}
	return $items;
}

protected function getLkpQuery($sql)
{
	if (strpos($sql,'{')) $sql = preg_replace_callback (
		"/{([a-z0-9_.]+)}/i", array($this, 'callback_getvalue_db'), $sql
	);
	return $this->service('db')->selectPair($sql);
}

protected function getLkpLookup($lookup)
{
	$sql = sprintf(
		"select id, label from %s
		where cname='%s' and (app='%s' or app is null)
		order by position,label",
		$this->service('db')->LOOKUP_TAB, $lookup, $this->app->name
	);
	return $this->service('db')->selectPair($sql);
}

protected function getDataSource($rs)
{
	$action = new Action($this->replaceParams($rs));
	$ct = $this->app->newController($action->controller);
	$args = $ct->getArgs($action->method, $action->params);
	return call_user_func_array(array($ct, $action->method), $args);
}

protected function getLkpList($list)
{
	$list = explode(',', $list);
	$items = array();
	$length = count($list);
	for ($i=0;$i<$length;$i+=2) {
		$items[$list[$i]] = $list[$i+1];
	}
	return $items;
}

// for function get_items()
private function callback_getvalue_db($param)
{
	return $this->service('db')->escape($this->callback_getvalue($param));
}

private function callback_getvalue($param)
{
	list($id,$sub) = explode('.', $param[1]);
	if ($id == 'GET') {
		if ($sub) return $_GET[$sub];
		else return '__GET__';
	}
	else return $this->getValue($param[1]);
}

protected function fireEventElem()
{
	$args = func_get_args();
	$name = $args[0]; $args[0] = $this;
	$id = $args[1];
	$func = $this->elements[$id][$name];
	if (!$func and $this->config['pclib.compatibility']['tpl_syntax']) {
		$func = $this->elements[$id][substr($name,2)];
		if ($func) $func = 'callback_'.$func;
	}
	if (!$func) return false;
	if (!is_callable($func)) {
		trigger_error(
			vsprintf($this->t("Function %s of event %s not found."), array($func, $name)),
			E_USER_WARNING
		);
		return false;
	}
	$ret = call_user_func_array($func, $args);
	if ($ret === null) $ret = true; //no return - stop propagation
	return $ret;
}

protected function t($s)
{
	return $this->service('translator', false)? $this->translator->translate($s) : $s;
}

} //class Tpl

/** @internal */
class Tpl_Accessor
{

private $name, $source;

function __construct(Tpl $source, $name)
{
	$this->source = $source;
	$this->name = $name;
}

function __toString()
{
	return $this->source->values[$this->name];
}

function __get($name)
{
	if (strpos($name,'html_') === 0)
		return $this->source->elements[$this->name]['html'][substr($name,5)];

	return $this->source->elements[$this->name][$name];
}

function __set($name, $value)
{
	if (strpos($name,'html_') === 0)
		$this->source->elements[$this->name]['html'][substr($name,5)] = $value;
	else
		$this->source->elements[$this->name][$name] = $value;
}

} //class Tpl_Accessor

?>