<?php

/**
 * \file
 * Grid with form capabilities.
 *
 * \author -dk- <lenochware@gmail.com>
 * \warning Experimental! Use on your own risk.
 * http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

require_once PCLIB_DIR . 'Grid.php';
require_once PCLIB_DIR . 'Form.php';

/**
 * \class GridForm
 * Just like grid, but you can use form tags: input, select, check, etc.
 * You can submit this and store to database with insert() and update() functions.
 * Each row will be updated using primary key, which must be defined. 
 * Use "primary FIELDNAME" in elements. Whole page is updated at once and only
 * one (active) page is submitted.
 * Note that lot of form capabilities are not supported at now. No validation,
 * file uploads etc. This is just alpha-version.
 * See http://pclib.brambor.net/demo/gridform/ for some example.
 */
class GridForm extends Grid
{

/**
 * This variable is set when formgrid has been submitted, in which case
 * it contains name of pressed button.
 */
public $submitted = false;

public $form = null;
private $pk = null;

/** Name of the 'class' element */
protected $className = 'gridform';

protected $inputCount = 0;

/**
 * Constructor - load formgrid template
 *
 * \param string $tpl_file Filename of template file
 * \param string $sessname When set, object is stored in session as $sessname
 */
function __construct($path = '', $sessName = '')
{
	parent::__construct($path, $sessName);
	$this->form = new GridForm_Form();
	$this->form->elements = $this->elements;
	$this->form->_init();
	
	if ($_REQUEST['submitted'] == $this->name) {
		$this->submitted = ifnot($_REQUEST['pcl_form_submit'], true);
		$this->values = $_REQUEST['data']; //TODO GET/POST instead REQUEST

		/*if (count($_FILES)) foreach ($_FILES as $id => $aFile)
			if($this->elements[$id]['file'])
				$this->values[$id] = $aFile['name'];*/
	}
}

protected function getValues()
{
	$rows = parent::getValues();
	if ($this->submitted) {
		foreach ($rows as $i => $row) {
			$rows[$i] = array_merge($rows[$i],$_REQUEST['rowdata'][$i]);
		}
	}
	return $rows;
}

/**
 * This function is called for each template tag when it is printed.
 * \copydoc tag-handler
**/
function print_Element($id, $sub, $value)
{
	$elem = $this->elements[$id];
	
	if ($elem['type'] == 'primary') {
		$this->print_Primary($id, $sub, $value);
		return;
	}
	
	if (!$elem['type'] or parent::hasType($elem['type'])) {
		parent::print_Element($id, $sub, $value);
	}
	else {
		$this->inputCount++;
		if ($elem['type'] == 'check') $value = $this->form->checkboxToArray($value);
		$this->form->print_Element($id, $sub, $value);
	}
}

/**
 * Print hidden input field with primary key value. Used for update.
 * \copydoc tag-handler
 */
protected function print_Primary($id, $sub, $value)
{
	$rowno = $this->form->rowno;

	if ($sub == 'value') print $value;
	elseif($sub == 'rowno') print $rowno;
	else {
		$this->inputCount++;
		print $this->htmlTag('input', array(
		'type' => 'hidden',
		'id' => $id.'_'.$rowno,
		'name' => "rowdata[$rowno][$id]",
		'value' => $value,
		));
	}
}

function print_BlockRow($block_id, $rowno = null)
{
	if ($block_id == 'items') {
		$this->form->rowno = $rowno;
	}
	parent::print_BlockRow($block_id, $rowno);
}

protected function parseLine($line)
{
	$id = parent::parseLine($line);
	if ($this->elements[$id]['type'] == 'primary') $this->pk = $id;
	return $id;
}

function out($block = null)
{
	$this->inputCount = 0;
	print $this->form->head();
	parent::out($block);
	print $this->form->foot();

	$maxInputs = ini_get('max_input_vars');
	if ($maxInputs and $this->inputCount > $maxInputs) {
		throw new Exception(sprintf(
			"Php INI directive 'max_input_vars' exceeds. %s inputs used.", $this->inputCount
		));
	}
}

/**
 * Insert form values into dbtable $tab.
 * \param string $tab database table name
 * \see form::insert()
 */
function insert($tab)
{
	if (!$tab) return false;
	if (!$this->form->db) throw new NoDatabaseException;

	foreach ($_REQUEST['rowdata'] as $frow) {
		$this->form->values = $frow;
		$this->form->insert($tab);
	}
}

/**
 * Update records in database table $tab, using primary key (pk).
 * \param string $tab database table name
 * \see form::update()
 */
function update($tab)
{
	if (!$tab) return false;
	if (!$this->form->db) throw new NoDatabaseException;
	if (!$this->pk) throw new NoValueException('Primary key not found.');

	foreach ($_REQUEST['rowdata'] as $frow) {
		$this->form->values = $frow;
		$this->form->update($tab, $this->pk . "='".$frow[$this->pk]."'");
	}
}

} //class GridForm

/** \privatesection */

//Helper class for gridform. Do not use directly.
class GridForm_Form extends Form
{
	public $rowno;

/** Name of the 'class' element */
protected $className = 'gridform';

function getTag($id, $ignore_html_attr = false)
{
	$tag = parent::getTag($id, $ignore_html_attr);
	if ($this->isInBlock($id, 'items')) {
		$tag['id'] = $tag['id'].'_'.$this->rowno;
		$tag['name'] = "rowdata[$this->rowno][$id]";
	}
	return $tag;
}

public function head() { return parent::head(); }
public function foot() { return parent::foot(); }
public function checkboxToArray($value) { return parent::checkboxToArray($value); }

} //class GridForm_Form

?>