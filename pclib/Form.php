<?php

/**
 * @file
 * Form management class.
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
 * Create and manage forms.
 * Features:
 * - Uses template
 * - Validation of user input
 * - Store %form into database
 * - Can use generic layout with default template
 * - Html5, javascript validation, Ajax and more.
 *
 * See \ref form-tags for description of implemented tags. \n
 * In addition, you can use common template elements (see \ref tpl-tags)
 */
class Form extends Tpl
{

/**
 * When %form has been submitted, it contains name of pressed button.
 */
public $submitted;

/** Occurs before element validation. */
public $onValidate;

/** Occurs before uploading of files. */
public $onUpload;

/** Occurs before inserting or updating database. */
public $onSave;

/** Occurs before deleting from database. */
public $onDelete;

/**
 * Array of error messages filled by validate() function.
 * You can set it on your own too.
 * Error messages can be shown using {errors} or {fieldname.err} tag in template.
 */
public $invalid = array();

/** var FormValidator Form validator. */
protected $validator;

protected $hidden;

protected $prepared = false;

private $ajax_id;

/** Name of the 'class' element */
protected $className = 'form';

/** Files matching patterns cannot be uploaded by Form class. */
public $uploadBlackList = array('*.php','*.php?','*.phtml','*.exe','.htaccess');

/** Generate buttons with html button tag or as input type=button. */
public $useButtonTag = false;

protected function _init()
{
	parent::_init();

	if ($this->header['table']) {
		$this->dbSync($this->header['table']);
	}

	if ($this->config['pclib.security']['csrf']) {
		$this->header['csrf'] = true;
	}

	if ($_REQUEST['submitted'] != $this->name) return;

	if ($_REQUEST['ajax_id']) {
		$this->ajax_id = pcl_ident($_REQUEST['ajax_id']);
		$this->header['get'] = 1;
	}

	//get values
	$this->submitted = ifnot($_REQUEST['pcl_form_submit'], true);
	$this->values = $this->header['get']? $_GET['data'] : $_POST['data'];

	if ($this->header['csrf']
		and $_REQUEST['csrf_token'] != $this->getCsrfToken()
	) throw new AuthException("CSRF authorization failed.");

	//set input file names
	foreach ((array)$_FILES as $id => $aFile) {
		if($this->elements[$id]['file'] and $aFile['size']) {
			$this->values[$id] = $aFile['name'];
		}
	}
	//set empty checkbox values, call onsave, apply formating
	foreach ($this->elements as $id=>$elem) {
		$value = $this->values[$id];
		if ($elem['type'] == 'check' and !$value and !$elem['noprint']) $this->values[$id] = array();
		elseif($elem['type'] == 'input') $this->values[$id] = trim($value);
		if ($elem['onsave']) $this->values[$id] = $this->fireEventElem('onsave',$id,'',$value);

		if ($fmt = $this->getAttr($id, 'format')) $this->values[$id] = $this->formatStr($value, $fmt);
	}

	$this->saveSession();
}

protected function getValidator()
{
	if (!$this->validator) $this->validator = new FormValidator($this->config);
	return $this->validator;
}


/** Display %form.
 * @param string $block If set, only block $block will be shown.
 */
protected function _out($block = null)
{
	print $this->head();
	parent::_out($block);
	print $this->foot();
}

/**
 * Do %form validation - return true if %form pass validation rules.
 * Scan #$elements and check validation rules, which are set
 * in elements section of %form template. If user does not fill %form properly
 * validate() return false and template tags {errors} and {TAGNAME.err}
 * are set with error messages. You can use predefined validation rules
 * with attributes required,email,date,number,file (see \ref form-tags)
 * or even define your own rule (see \ref callback-func)
 * @return bool
 */
function validate()
{
	$this->invalid = array();

	foreach ($this->elements as $id => $elem) {
		if (!$this->isEditable($id)) continue;

		$elem['value'] = $this->values[$id];

		$errormsg = $this->validateElement($elem);
		if ($errormsg) $this->invalid[$id] = $errormsg;
	}

	$this->saveSession();

	return !(bool)$this->invalid;
}

/**
 * Load %form values from session.
 * Called when $sessname in constructor is set.  Do not call directly.
 * Useful, if you need keep %form values after page-reload.
**/
function loadSession()
{
	if (!$this->sessName) return;
	$s = $this->app->getSession($this->sessName);

	$this->values  = $s['values'];
	$this->invalid = $s['invalid'];
}

/**
 * Save %form values to session. Do not call directly.
 * @see loadsession()
**/
function saveSession()
{
	if (!$this->sessName) return;

	$s =array(
		'values'  => $this->values,
		'invalid' => $this->invalid,
	);
	$this->app->setSession($this->sessName, $s);
}

/**
 * Is element $id editable?
**/
function isEditable($id, $testAttr = true)
{
	$editables = array ('input', 'check', 'radio', 'text', 'select', 'listinput');
	$elem = $this->elements[$id];
	if (!$elem) return false;
	if (!in_array($elem['type'], $editables)) return false;
	if ($testAttr and ($this->getAttr($id, 'noprint') or $this->getAttr($id, 'noedit'))) return false;
	return true;
}

/**
 * Build default form template.
 * @see tpl::create()
 */
function create($dsstr, $fileName = null, $template = null)
{
	$this->service('db');
	$trans = array('<:' => '<', ':>' => '>', '{:' => '{', ':}' => '}');
	if (!$template) $template = PCLIB_DIR.'assets/def_form.tpl';

	$table = $this->db->tableName($dsstr);
	$columns = $this->db->columns($table);

	$fields = $this->getFields($dsstr);
	foreach($fields as $id) {
		if ($columns[$id]['autoinc']) continue;
		$elem .= $this->columnToElementStr($columns[$id]);
		$body[] = array(
		'LABEL' => '{'.$id.'.lb}',
		'FIELD' => '{'.$id.'}',
		'ERR' => '{'.$id.'.err}',
		);
	}

	$t = new Tpl($template);
	$t->values['NAME'] = $table;
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

/** Return element for table column. Helper for create(). */
private function columnToElementStr(array $col)
{
	$type = 'input';

	$size = ($col['size'] > 50)? '50/'.$col['size'] : $col['size'];
	if ($col['type'] == 'string' and $col['size'] > 255) { $type = 'text'; $size = null; }
	if ($col['type'] == 'bool') { $type = 'check'; $size = null; }

	if ($col['type'] == 'int' or $col['type'] == 'float') $size = '6/30';
	if ($col['type'] == 'date') $size = ($col['size']>1)? '20/30' : '10/30';

	$lb = ifnot($col['comment'], $col['name']);
	$s = $type.' '.$col['name'].' lb "'.$lb.'"';
	if ($size) $s .= " size \"$size\"";
	if ($col['type'] == 'date') $s .= ' date';
	if (stripos('-'.$col['name'], 'MAIL')) $s .= ' email';
	if (!$col['nullable']) $s .= ' required';
	return $s."\n";
}

/** 
 * Prepare form tag with common attributes.
 * @return array $tag
 **/
protected function getTag($id, $ignoreHtmlAttr = false)
{
	$elem = $this->elements[$id];
	if ( $ignoreHtmlAttr) $elem['html'] = null;
	$html5 = $this->getAttr($id, 'html5');

	$tag = array(
		'id' => $id, 'type' => null, 'name' => "data[$id]", 'class' => array()
	);

	if ($elem['html']) $tag += $elem['html'];

	$tag['size'] = $elem['size'];

	if ($this->getAttr($id, 'noedit')) $tag[] = 'disabled';
	if ($html5) {
		if ($elem['required']) $tag[] = 'required';
		if ($elem['pattern']) $tag['pattern'] = $elem['pattern'];
	}

	$tag['placeholder'] = $elem['hint'];

	$class = array();
	if ($elem['html']['class']) $class[] = $elem['html']['class'];
	if ($this->getAttr($id, 'noedit')) $class[] = 'disabled';
	if ($this->invalid[$id]) $class[] = 'err';
	if ($elem['required']) $class[] = 'required';
	$tag['class'] = $class;

	$tag['__attr'] = $elem['attr'];

	if ($ajaxget = $elem['ajaxget']) {
		$event = str_shift(':', $ajaxget);
		if (!$event) $event = $this->isEditable($id)? 'onchange' : 'onclick';
		$url = $this->header['action'];
		$url .= (strpos($url, '?') ? '&' : '?')."pcl_form_submit=ajax&ajax_id=$id";

		if ($ajaxget == '1' or $ajaxget == '*') {
			foreach($this->elements as $aid => $tmp) {
				if ($this->isEditable($aid, false) and $aid != $id)
					$paramsarray[] = $aid;
			}
			$params = implode(',', $paramsarray);
		}
		else $params = $ajaxget;
		$tag[$event] = "pclib.ajaxGet('$this->name','$id','$url','$params')";
	}
	return $tag;
}

/** Return form value. */
function getValue($id)
{
	$elem = $this->elements[$id];

	$value = parent::getValue($id);
	if ($elem['type'] == 'check') return $this->checkboxToArray($value);
	return $value;
}

/**
 * Convert checkbox $value (int|string) to array
 */
protected function checkboxToArray($value)
{
 if (empty($value)) return array();
 elseif (is_numeric($value)) {
	 $checkboxes = array();
	 for($i=0;$i<64;$i++) {
		 $bit = pow (2, $i);
		 if ($bit > $value) break;
		 if ($value & $bit) $checkboxes[] = $i + 1;
	 }
	 return $checkboxes;
 }
 elseif (is_string($value)) {
	 return explode (',', $value);
 }
 return $value;
}

/**
 * This function is called for each template tag when it is printed.
 * @copydoc tag-handler
**/
function print_Element($id, $sub, $value)
{
	if ($sub == 'lb')  {
		$this->print_Label($id);
		return;
	}

	if ($sub == 'value') {
		print $value;
		return;
	}

	if ($sub == 'err') {
		print '<span class="err">';
		print $this->invalid[$id];
		print '</span>';
		return;
	}

	if ($id == 'errors') {
		$this->print_Errors();
		return;
	}

	if ($this->header['ajax'] and $id != 'GET') print "<span id=\"x_$id\">";

	switch ($this->elements[$id]['type']) {
		case 'input':
			$this->print_Input($id,$sub,$value);
			break;
		case 'text':
			$this->print_Text($id,$sub,$value);
			break;
		case 'button':
			$this->print_Button($id,$sub,$value);
			break;
		case 'check':
		case 'radio':
			$this->print_Checkbox_Radio_Group($id, $sub, $value);
			break;
		case 'select':
			$this->print_Select($id,$sub,$value);
			break;
		case 'listinput':
			$this->print_ListInput($id,$sub,$value);
			break;
		case 'class':
			$this->print_Class($id,$sub,null);
			break;
		default:
			parent::print_Element($id,$sub,$value);
			break;
	}

	if ($this->header['ajax'] and $id != 'GET') print "</span>";
}


function print_Block($block)
{
	if ($this->header['ajax']) {
		print "<span id=\"x_$block\">";
		parent::print_Block($block);
		print "</span>";
	}
	else
		parent::print_Block($block);
}

function print_Empty($id, $sub = null)
{
	if (!$this->header['ajax']) return;
	if ($sub == 'lb')
		print "<label for=\"$id\" id=\"xl_$id\"></label>";
	else
		print "<span id=\"x_$id\"></span>";
}

/**
 * Print %form error messages.
 * Called for {errors} tag.
 * @see validate()
 */
function print_Errors()
{
	if (!$this->invalid) return;
	print "<div class=\"error-messages\">";
	foreach($this->invalid as $id => $message) {
		$lb = ifnot($this->elements[$id]['lb'], $id);
		print "<div>$lb $message</div>";
	}
	print "</div>";
}

/**
 * Print all form elements at once.
 * Uses simple table layout from presentation.
 * Implement {form.fields} placeholder.
 * @copydoc tag-handler
 */
function print_Class($id, $sub, $value)
{
	parent::print_Class($id, 'fields', $value);

	$fields = $this->getFields();
	print "<tr><td colspan=\"3\">";
	foreach($fields as $id) {
		$elem = $this->elements[$id];
		if ($elem['noprint'] or $elem['skip'] or $elem['type'] != 'button') continue;
		$this->print_Button($id, '', $this->getValue($id));
		print ' ';
	}
	print '</td></tr>';
}

/** Helper for print_class() */
protected function print_Class_Item($id, $sub)
{
	$elem = $this->elements[$id];
	if ($elem['hidden']) return;
	print "<tr><td class=\"$id\">";
	$this->print_Element($id, 'lb', null);
	print '</td><td>';
	$value = $this->getValue($id);
	if (!$this->fireEventElem('onprint',$id, '', $value))
		$this->print_Element($id, '', $value);
	print '</td><td>';
	$this->print_Element($id, 'err', null);
	print '</td></tr>';
}

/**
 * Print label for form field.
 * @copydoc tag-handler
 */
function print_Label($id)
{
	$elem = $this->elements[$id];
	if ($elem['required']) $class[] = 'required';
	if ($this->invalid[$id]) $class[] = 'err';
	if ($class) $attr = ' class="'.implode(' ',$class).'"';
	if ($this->header['ajax']) $attr .= " id=\"xl_$id\"";
	print "<label for=\"$id\"$attr>";
	print $elem['lb']? $elem['lb'] : $id;
	print "</label>";
}

/**
 * Print HTML input field.
 * @copydoc tag-handler
 */
function print_Input($id, $sub, $value)
{
	$elem = $this->elements[$id];
	$value = $this->escape($value);

	if ($elem['file'] and $sub == 'filename') { print $value; return; }

	$html5 = $this->getAttr($elem['id'],'html5');
	$tag = $this->getTag($id);
	$tag['maxlength'] = $elem['maxlength']? $elem['maxlength'] : $elem['size'];

	//convert database date...
	if ($elem['date'] and $value) {
		$value = $this->formatDate($value, $elem['date']);
	}

	if ($elem['password']) $type = 'password';
	elseif($elem['file'])  {
		$type = 'file';
		$tag['name'] = $tag['id'];
	}
	elseif($elem['hidden']) {
		$type = 'hidden';
		$tag['value'] = $value;
		unset($this->hidden[$id]);
	}
	else {
		$type = 'text';
		$tag['value'] = $value;

		if ($html5) {
			if ($elem['date']) $type = 'date';
			else if ($elem['number']) $type = 'number';
			else if ($elem['email'])  $type = 'email';
			else if ($elem['tel'])  $type = 'tel';
			else if ($elem['website'])  $type = 'website';
			else if ($elem['color'])  $type = 'color';
			else if ($elem['time']) {
				$type = 'time';
				$tag['step'] = 1800;
			}
			else if ($range = $elem['range']) {
				$type = 'range';
				sscanf($range, "%d-%d+%f", $tag['min'], $tag['max'], $tag['step']);
			}
		}
	}
	$tag['type'] = $type;

	print $this->htmlTag('input', $tag) . $this->ieFix($id,$tag['name'],$value);
}

/**
 * Print HTML TEXTAREA field.
 * @copydoc tag-handler
 */
function print_Text($id, $sub, $value)
{
	$elem = $this->elements[$id];
	$value = $this->escape($value);

	$tag = $this->getTag($id);

	unset($tag['size']);
	if (!$size = $elem['size']) $size = '60x6';
	list($tag['cols'], $tag['rows']) = explode("x", $size);
	$tag['maxlength'] = $elem['maxlength'];

	print $this->htmlTag('textarea', $tag, $value).$this->ieFix($id,$tag['name'],$value);
}

/**
 * Print form button.
 * @copydoc tag-handler
 */
function print_Button($id, $sub, $value)
{
	$elem = $this->elements[$id];
	$url = $this->getUrl($elem);
	$onclick = ifnot($elem['onclick'], $elem['html']['onclick']);
	$tagname = $this->useButtonTag? 'button':'input';
	if ($elem['tag']) $tagname = $elem['tag'];

	$tag = $this->getTag($id);
	$tag['name'] = $id;
	if ($elem['submit']) $tag['type'] = 'submit';
	else $tag['type'] = ($url or $onclick)? 'button':'submit';
	if ($onclick == '1') $onclick = null;

	if ($tagname == 'button') {
		$content = '';
		if ($elem['img']) $content .= $this->htmlTag('img',array('src'=>$elem['img']));
		if ($elem['glyph']) $content .= $this->htmlTag('span',array('class'=>$elem['glyph']),'');
		if ($tag['type'] == 'submit') $tag['name'] = "pcl_form_submit";
		$tag['value'] = $id;
		$content .= isset($elem['lb'])? $elem['lb'] : $id;
	} else {
		 if ($tag['type'] == 'submit') $tag['name'] = "pcl_form_submit[$id]";
		 $content = null;
		 $tag['value'] = isset($elem['lb'])? $this->escape($elem['lb']) : $id;
	}

	if ($url and $elem['popup'])
		$onclick = $this->getPopup($id, $elem['popup'], $url);
	elseif ($url)
		$onclick = "window.location='$url';";

	$tag['onclick'] = $onclick;
	print $this->htmlTag($tagname, $tag, $content);
}

/**
 *  Print group of radiobuttons or checkboxes.
 *  \copydoc tag-handler
 */
function print_Checkbox_Radio_Group($id, $sub, $value)
{
	$elem = $this->elements[$id];
	$is_radio = ($elem['type'] == 'radio');

	$items = $this->getItems($id);
	if ($sub or !$items) {
		if ($is_radio) $this->print_Radio($id, $sub, $value);
		else $this->print_Checkbox($id, $sub, $value);
		return;
	}

	$class = $elem['html']['class'];
	$style = $elem['html']['style'];
	$class = trim(($is_radio?'radio':'checkbox').'-group '.$class);
	if ($c = $elem['columns'])
		$style = "-moz-columns:$c;-webkit-columns:$c;columns:$c;".$style;

	print '<div id="'.$id.'-group" class="'.$class.'"'.($style? ' style="'.$style.'"':'').'>';
	$i = 0;
	foreach($items as $sub => $item) {
		print '<div>';
			if ($is_radio) $this->print_Radio($id, $sub, $value, $i++);
			else $this->print_Checkbox($id, $sub, $value, $i++);
		print '</div>';
	}
	print "</div>";
}

/**
 *  Print INPUT TYPE=CHECKBOX field.
 *  \copydoc tag-handler
 */
function print_Checkbox($id, $sub, $value, $i = null)
{
	$elem  = $this->elements[$id];
	$cbid = $sub? $sub : 1;
	$tag = $this->getTag($id, isset($elem['items']));
	$tag['type'] = 'checkbox';
	$tag['checked'] = in_array($cbid, $value)? 'checked' : null;
	$tag['value'] = $cbid;
	$tag['id'] = $id.(isset($i)? '_'.$i : '');
	if ($sub) $tag['name'] .= "[$i]";

	$html = $this->htmlTag('input', $tag);
	if ($sub) {
		$label = $elem['items'][$sub];
		$html = "<label>$html$label</label>";
	}
	if ($tag['checked']) $html .= $this->ieFix($id,$tag['name'], $cbid);
	print $html;
}

/**
 * Print INPUT TYPE=RADIO field.
 * @copydoc tag-handler
 */
function print_Radio($id, $sub, $value, $i = null)
{
	$elem  = $this->elements[$id];
	$cbid = $sub? $sub : 1;
	$tag = $this->getTag($id, isset($elem['items']));
	$tag['type'] = 'radio';
	$tag['checked'] = ($cbid == $value)? 'checked' : null;
	$tag['value'] = $cbid;
	$tag['id'] = $id.(isset($i)? '_'.$i : '');

	$html = $this->htmlTag('input', $tag);
	if ($sub) {
		$label = $elem['items'][$sub];
		$html = "<label>$html$label</label>";
	}
	if ($tag['checked']) $html .= $this->ieFix($id,$tag['name'], $cbid);
	print $html;
}

/**
 * Print HTML5 input with datalist.
 * @copydoc tag-handler
 */
function print_ListInput($id, $sub, $value)
{
	$elem  = $this->elements[$id];
	$items = $this->getItems($id);
	$tag   = $this->getTag($id);
	$tag['type'] = 'text';
	$tag['class'][] = 'listinput';
	$tag['maxlength'] = $elem['maxlength']? $elem['maxlength'] : $elem['size'];
	$tag['value'] = $this->escape($value);
	$tag['list'] = 'dl_'.$tag['id'];

	$html = $this->htmlTag('input', $tag);

	$html .= "<datalist id=\"dl_$tag[id]\">";
	foreach ($items as $i => $item) {
		$html .= "<option label=\"$i\" value=\"$item\">";
	}
	$html .= "</datalist>";

	print $html;
}

/**
 *  Print HTML SELECT field.
 *  \copydoc tag-handler
 */
function print_Select($id, $sub, $value)
{
	$elem  = $this->elements[$id];
	$items = $this->getItems($id);
	$tag   = $this->getTag($id);

	$emptylb = ifnot($elem['emptylb'], ' - Choose - ');

	$options = array();
	$html = $elem['noemptylb']? '':'<option value="">'.$this->t($emptylb).'</option>';

	$group = '_nogroup_';
	foreach ($items as $i => $item) {
		if (is_array($item)) list($label,$group) = $item;
		else $label = $item;

		$ch = ((string)$i == (string)$value)? ' selected="selected"' : '';
		$i = $this->escape($i);
		$label = $this->escape($label);

		$options[$group] .= "<option value=\"$i\"$ch>$label</option>";
	}
	if ($options['_nogroup_']) $html .= $options['_nogroup_'];
	else {
		foreach($options as $group => $content) {
			$html .= "<optgroup label=\"$group\">$content</optgroup>";
		}
	}
	$html = $this->htmlTag('select', $tag, $html).$this->ieFix($id,$tag['name'],$value);
	print $html;
}

/**
 * Validate $element - return error message if invalid.
 * You can redefine this in descendant and add your own validation rules.
 * @param array $elem
 * @return string error message or empty string if ok.
 * @see validate()
 */
protected function validateElement(array $elem)
{
	$event = $this->onValidate($elem);
	if ($event and !$event->propagate) return $event->result;

	$id = $elem['id'];
	$value = $this->toString($this->values[$id]);

	$validate = $this->getValidator();
	if (!$elem['required'] and $validate->isEmpty($value)) return '';

	foreach($validate->rules as $rule) {
		if ($elem[$rule] and !$validate->$rule($value, $elem[$rule])) {
			return $this->t($validate->getMessage($rule));
		}
	}

	if ($elem['onvalidate'])
		return $this->fireEventElem('onvalidate',$id,'',$value);

	return '';
}

/**
 * Prepare form values for storing into database.
 * Convert date, number, remove unwanted fields etc.
 * @param bool $skipEmpty Remove empty fields?
 */
function prepare($skipEmpty = false)
{
	if (!$this->values) return;
	foreach ($this->values as $id=>$value) {
		$elem = $this->elements[$id];
		if ($id == '' or ($value == '' and $skipEmpty)
		or $this->getAttr($id, 'nosave')
		/*or $elem['noprint']*/) {
			unset($this->values[$id]);
			continue;
		}

		if ($this->elements[$id]['file'] and !$this->values[$id]) {
			unset($this->values[$id]);
			continue;
		}

		if ($elem['date'])
			$value = $this->toSqlDate($value, $elem['date']);

		if ($elem['number'] and $elem['number'] != 'strict')
			$value = $this->toNumber($value);

		if (is_array($value)) $value = $this->toBitField($value);
		$this->values[$id] = $value;
	}
	$this->prepared = true;
}

private function getTableName($tab)
{
	$tableName = $this->header['table'] ?: $tab;
	if ($tab and $tab != $tableName) {
		throw new Exception('Database table name mismatch.');
	}
	return $tableName;
}

/**
 * Upload form files.
 * @param array $old List of previous versions of files - will be deleted
 */
function upload($old = array())
{
	$event = $this->onUpload($_FILES, $old);
	if ($event and !$event->propagate) return;

	foreach ($_FILES as $id => $aFile) {
		$elem = $this->elements[$id];
		if ($aFile['error']) $this->invalid[$id] = 'Upload error ('.$aFile['error'].')';
		if ($elem['nosave'] or !$aFile['size'] or $aFile['error']) continue;
		if ($this->fileInBlackList($this->values[$id])) throw new RuntimeException("Illegal file type.");
		if (!$elem['into']) throw new NoValueException("Missing 'into \"directory\"' attribute for input file.");
		$path = realpath($elem['into']);
		if (!is_dir($path)) throw new IOException("Path '$path' not found.");
		if ($old[$id]) @unlink($path.'/'.$old[$id]);
		$filename = $this->fileName($id);
		$tmpname = $aFile['tmp_name'];
		$ok = @move_uploaded_file ($tmpname, "$path/$filename");
		if (!$ok) throw new IOException("Cannot upload file $path/$filename (permissions problem?)");
		@chmod($path.'/'.$filename, 0666);
		$this->values[$id] = $filename;
	}
}

/**
 * Insert %form values into db-table $tab and save uploaded files into specified directory.
 * Columns of the table must have same names like keys in #$values array.
 * Convert numbers, dates and checkboxes to database format automatically.
 * @param string $tab database table name
 * @return int $inserted_id
 */
function insert($tab)
{
	$tab = $this->getTableName($tab);
	$event = $this->onSave('insert', $tab);
	if ($event and !$event->propagate) return;

	$this->service('db');

	if (count($_FILES)) $this->upload();
	if (!$this->prepared) $this->prepare(1);
	$id = $this->db->insert($tab, $this->values);
	return $id;
}

/**
 * Update %form record in database table $tab, using where-condition $cond.
 * @param string $tab database table name
 * @param string $cond where-condition of query
 * @see insert()
 */
function update($tab, $cond)
{
	$tab = $this->getTableName($tab);
	$event = $this->onSave('update', $tab, $cond);
	if ($event and !$event->propagate) return;

	$this->service('db');

	$params = (func_num_args() > 2)? array_slice(func_get_args(),2) : null;
	if ($params and is_array($params[0])) $params = $params[0];

	if (count($_FILES)) {
		$old = $this->db->select($tab, $cond, $params);
		$this->upload($old);
	}

	if (!$this->prepared) $this->prepare();
	$this->db->update($tab, $this->values, $cond, $params);
}

/**
 * Delete %form record and remove attached files (if any).
 * @param string $tab database table name
 * @param string $cond where-condition of the query
 * @see insert()
 */
function delete($tab, $cond)
{
	$tab = $this->getTableName($tab);
	$event = $this->onDelete('delete', $tab, $cond);
	if ($event and !$event->propagate) return;

	$this->service('db');

	$params = (func_num_args() > 2)? array_slice(func_get_args(),2) : null;
	if ($params and is_array($params[0])) $params = $params[0];

	$data = $this->db->select($tab, $cond, $params);

	foreach ($this->elements as $id=>$elem)
		if ($elem['file'] and $elem['into']) {
			 $path = realpath($this->elements[$id]['into']);
			 if (!$data[$id]) continue;
			 @unlink($path.'/'.$data[$id]);
		}

	$this->db->delete($tab, $cond, $params);
}

/**
 * Return array of label => value(s) pairs for all elements of the %form.
 * Helper for showing %form content to the end user. Used in mailTo().
 * @return array $values
 */
function content()
{
	if (!$this->values) return;
	$content = array();

	foreach ($this->values as $id=>$value) {
		$elem = $this->elements[$id];
		if ($elem['lb'] == '') continue;
		if (is_array($value)) $value = implode(',', $value);
		if ($elem['noprint']) continue;
		if ($value == '') {
			$content[$elem['lb']] = $elem['emptylb'];
			continue;
		}

		if ($elem['type'] == 'radio' or $elem['type'] == 'select') {
			$items = $this->getItems($id, true);
			$value = $items[$value];
		}
		elseif ($elem['type'] == 'check') {
			$value = $this->getValue($id);
			$items = $this->getItems($id, true);
			foreach($items as $i => $label) {
				if (in_array($i,$value)) $labels[] = $label;
			}
			$value = implode(', ', $labels);
		}

		$content[$elem['lb']] = $value;
	}

	return $content;
}

/**
 * Send %form by mail.
 * @param string $to - destination mail address
 * @param string $subj - mail subject
 * @param string $intro - text of the mail before list of %form values
 * @param string $hdr - headers
 */
function mailTo($to, $subj, $intro='', $hdr='')
{
	$msg = "$intro\n";
	foreach ($this->content() as $lb => $value) {
		$msg .= str_pad($lb, 20) . $value .EOL;
	}

	mail($to, $subj, $msg, $hdr);
}

/**
 * Synchronize %form elements on the client with server, without reloading page.
 * @param string $elemList Colon separated list of elements to synchronize
 * @return string json-data
 */
function ajaxSync($elemList = null)
{
	if (!isset($elemList)) {
		$elemList = $this->elements[$this->ajax_id]['ajaxget'];
		if (strpos($elemList, ':')) str_shift(':', $elemList);
	}
	if ($elemList != '1')
		$elemarray = explode(',', $elemList);
	else
		$elemarray = array_keys($this->values);

	$result = array();
	$this->header['ajax'] = false;
	foreach((array)$elemarray as $id) {
		$h_id = $this->elements[$id]? "x_$id" : $id;
		if ($this->getAttr($id, 'noprint')) {
			$result[$h_id] = '';
			$result['xl_'.$id] = '';
			continue;
		}
		ob_start();
		if ( $this->elements[$id]['type'] == 'block')
			$this->print_Block($id);
		else
			$this->print_Element($id, null, $this->getValue($id));
		$result[$h_id] = ob_get_contents();
		/*if ($this->config['pclib.encoding'])
			$result[$h_id] = utf8_string($result[$h_id]);*/
		ob_end_clean();
	}

	return json_encode($result);
}

/**
 * Set maxlength and size of the %form fields, according underlying database table.
 * @param $tab Table name
 */
function dbSync($tab)
{
	$columns = $this->service('db')->columns($tab);
	if (!$columns) throw new Exception("Database table '$tab' not found.");
	foreach($this->elements as $id => $el) {
		if (!$this->isEditable($id)) continue;
		if (!$columns[$id]) {
			$this->elements[$id]['nosave'] = 1;
			continue;
		}
		if ($columns[$id]['type'] != 'string') continue;
		if (!$el['maxlength'])
			$this->elements[$id]['maxlength'] = $columns[$id]['size'];
		if ($el['type'] != 'input') continue;
		if (!$el['size'] or $el['size'] > $columns[$id]['size'])
			$this->elements[$id]['size'] = $columns[$id]['size'];
	}
}

protected function parseLine($line)
{
	$id = parent::parseLine($line);
	$elem = $this->elements[$id];
	if ($elem['hidden']) $this->hidden[$id] = $id;
	if ($elem['file'] and $elem['type'] == 'input')
		$this->elements[$this->className]['fileupload'] = 1;
	if ($elem['ajaxget']) $this->elements[$this->className]['ajax'] = 1;
	if (strpos($elem['size'],'/')) {
		list($sz,$ml) = explode('/',$elem['size']);
		$this->elements[$id]['size'] = $sz;
		$this->elements[$id]['maxlength'] = $ml;
	}
	if ($elem['type'] == 'check' and $elem['default']) {
		$this->elements[$id]['default'] = explode(',', $elem['default']);
	}

	return $id;
}

//submit disabled elements too (add hidden field for disabled element)
protected function ieFix($id, $name, $value)
{
	if (!$this->getAttr($id, 'noedit') or $this->elements[$id]['hidden']) return '';

	$tag = array(
		'type' => 'hidden',
		'id' => $id.'_hid',
		'name' => $name,
		'value' => $value,
	);
	return $this->htmlTag('input', $tag);
}

/**
 * Create file name based on element attributes.
 * @internal
 */
protected function fileName($id)
{
	//$format = $this->elements[$id]['rename'];
	$elem = $this->elements[$id];

	$fileName = $this->values[$id];
	$baseName = extractPath($fileName, '%f');
	$ext = extractPath($fileName, '.%e');

	if (utf8_strlen($ext) > 6) {
		$baseName .= $ext; 
		$ext = '';
	}

	$baseName = substr(mkident($baseName, '-'), 0, 80);

	while (true) {
		$fileName = $baseName.'-'.randomstr(8).$ext;
		if (!file_exists(realpath($elem['path']).'/'.$fileName)) break;
	}

	return $fileName;
}


/** HUMAN DATE => DATABASE DATE */
protected function toSqlDate($dtstr, $fmtstr = '')
{
	if (!$dtstr) return null;
	$fmtspec = array('d','m','H','M','S','y','Y');

	$dt = preg_split("/[^0-9]+/", $dtstr);
	if (!$fmtstr or $fmtstr == '1') $fmtstr = $this->config['pclib.locale']['datetime'];
	preg_match_all("/%(.)/", $fmtstr, $fmt, PREG_PATTERN_ORDER);
	$fmt = $fmt[1];

	$oDT = new \stdClass;
	$oDT->d = $oDT->m = $oDT->H = $oDT->M = $oDT->S = '00'; $oDT->Y = '0000';

	while ($fmtpart = array_shift($fmt)) {
		if (!in_array($fmtpart, $fmtspec)) continue;
		$dtpart = str_pad(array_shift($dt), 2, "0", STR_PAD_LEFT);
		if ($fmtpart == 'y') { $dtpart = "20$dtpart"; $fmtpart = 'Y'; }
		$oDT->$fmtpart = $dtpart;
	}
	return "$oDT->Y-$oDT->m-$oDT->d $oDT->H:$oDT->M:$oDT->S";
}

/**
 * Bypass non-numerical characters.
 */
protected function toNumber($value)
{
	$number = preg_replace('/[^\d\.]+/','', $value);
	return (strlen($number) > 0)? $number : null;
}

/** Convert array of bits to integer. */
protected function toBitField(array $bitArray)
{
	$value = 0;
	foreach ($bitArray as $bit) {
		$bit = (integer) $bit;
		if ($bit > 64 or $bit < 1) throw new \OutOfBoundsException('Checkbox index out of bounds.');
		$value += pow (2, $bit - 1);
	}
	return $value;
}

/** escape value for using in form input */
protected function escape($s)
{
	return utf8_htmlspecialchars($s);
}

/** return <form> header */
protected function head()
{
	if ($this->header['noformtag']) return '';

	$ha = $this->header['html'];

	$tag = array(
		'id' => $this->name,
		'name' => null,
		'class' => $ha['class']? array($ha['class']) : null,
		'__attr' => $ha['attr'],
	);

	if ($ha) $tag += $ha;

	if (!$this->useXhtml) $tag['name'] = $this->name;

	$action = $this->getUrl($this->header);

	if ($this->header['get']) {
		$tag['method'] = 'get';

		if (strpos($action, '?')) {
			list($url, $params) = explode('?', $action);
			$action = $url;
			parse_str ($params, $hidden);
		}
	}
	else
		$tag['method'] = 'post';

	if ($this->header['csrf']) $hidden['csrf_token'] = $this->getCsrfToken();
	$hidden['submitted'] = $this->name;
	if ($action) $tag['action'] = $this->header['action'] = $action;

	if ($jsvalid = $this->header['jsvalid']) {
		 $tag['onsubmit'] = "return pclib.validate(this);";
		 $hidden['pclib_jsvalid'] = $this->getValidationString();
	}

	if ($this->header['fileupload']) $tag['enctype'] = 'multipart/form-data';

	$html = $this->htmlTag('form', $tag, null, true).EOL;

	foreach ((array)$hidden as $k => $v) {
		$html .= "<input type=\"hidden\" name=\"$k\" value=\"$v\"".($this->useXhtml? ' />' : '>').EOL;
	}

	return $html;
}

/** return </form> footer html \internal */
protected function foot()
{
	$html = '';
	if ($this->hidden) {
		ob_start();
		foreach($this->hidden as $hid) {
			$this->print_Input($hid, '', $this->values[$hid]);
		}
		$html = ob_get_contents();
		ob_end_clean();
	}
	if (!$this->header['noformtag']) $html .= '</form>';

	return $html;
}

private function getCsrfToken()
{
	if (!session_id()) throw new RuntimeException('Session is required.');
	$token = $this->app->getSession('pclib.csrf_token');
	if (!$token) {
		$token = randomstr(10);
		$this->app->setSession('pclib.csrf_token', $token);
	}
	return $token;
}

/** Test if uploaded filename mask is on the blacklist. */
private function fileInBlackList($fileName)
{
	foreach ($this->uploadBlackList as $pattern) {
		if (fnmatch($pattern, $fileName)) return true;
	}
	return false;
}


/**
 * Generate string containing data for javascript validator.
 * @internal
**/
private function getValidationString()
{
	$rules = array('date','email','password','number','file','pattern');

	$defaults = array(
	'password' => '8',
	'date' => $this->config['pclib.locale']['date'],
	'file' => '*',
	);

	$output = array();
	foreach ($this->elements as $id => $elem) {
		if (!$this->isEditable($id)) continue;
		$rule = $options = '';
		$required = $elem['required'];
		foreach($rules as $testrule) {
			if ($elem[$testrule]) {
				$rule = $testrule;
				break;
			}
		}
		if (!$rule and !$required) continue;

		if ($rule) {
			if ($elem[$rule] === 1 and $defaults[$rule]) $options = $defaults[$rule];
			else $options = $elem[$rule];
		}

		switch($rule) {
			case 'number': if($options != 'strict') continue; break;
			case 'date': $options = preg_replace("/[^dmyhms]/i","", $options); break;
			case 'file': $options = strtr($options, array('.' => '\.', '*' => '.*', '?' => '.')); break;
		}

		$lb = ifnot(strip_tags(strtr($elem['lb'],'"|',"'/")), $id);

		$output[] = "$id|$lb|$required|$rule|$options";
	}

	return implode('|',$output);
}

} // end class

/**
 * Form validation rules.
 */
class FormValidator
{
public $rules = array('required','date','email','number','password','file','pattern');

public $messages = array(
	'required' => 'Field is required!',
	'date'     => 'Bad date format!',
	'email'    => 'Bad email address!',
	'number'   => 'Not a number!',
	'password' => 'Invalid password!',
	'file'     => 'Bad file type!',
	'pattern'  => 'Invalid value!',
);

public $config;

const PATTERN_EMAIL = '/^[_\w\.\-]+@[\w\.-]+\.[a-z]{2,6}$/';

function __construct(array $config)
{
	$this->config = $config;
}

function getMessage($rule)
{
	return $this->messages[$rule];
}

/**
 * Validate required field.
 * @copydoc valid-rule
 */
function required($value)
{
	return !$this->isEmpty($value);
}

function isEmpty($value)
{
	return is_array($value)? (count($value)==0) : (strlen($value)==0);
}

/**
 * Validate proper email address.
 * @copydoc valid-rule
 */
function email($value)
{
	return preg_match(self::PATTERN_EMAIL, $value);
}

/**
 * Validate regexp.
 * @copydoc valid-rule
 */
function pattern($value, $pattern)
{
	return preg_match('/^'.$pattern.'$/', $value);
}

/**
 * Validate number.
 * @copydoc valid-rule
 */
function number($value, $options)
{
	if ($options != 'strict') return true;
	return is_numeric($value);
}

/**
 * Validate password. You can set minlength and characters required in password
 * with extcharset attribute.
 * @copydoc valid-rule
 */
function password($value, $options)
{
	if ($options == 1) $options = '8,0';
	list($minlen,$xchars) = explode(',', $options);
	if ($minlen and (utf8_strlen($value) < $minlen)) return false;
	if ($xchars and ctype_alnum($value)) return false;
	return true;
}

function parseDate($datestr, $format)
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

/**
 * Validate date against fmt. Default format is '%d.%m.%Y'.
 * @copydoc valid-rule
 */
function date($value, $options)
{
	if ($options == 1) $options = $this->config['pclib.locale']['date'];
	list($d,$m,$y,$h,$i,$s) = $this->parseDate($value, $options);
	if (!checkdate($m,$d,$y)) return false;
	if (!$this->checkTime($h,$i,$s)) return false;
	return true;
}

function checkTime($h, $i, $s)
{
	return ($h<24 and $i<60 and $s<60);
}

/**
 * Validate filename.
 * @copydoc valid-rule
 */
function file($value, $options)
{
	if ($options == 1) $options = '*';
	$wildcards = explode(';', $options);
	foreach ($wildcards as $wildcard) {
		if (fnmatch($wildcard, $value)) return true;
	}
	return false;
}

} //class FormValidator

?>