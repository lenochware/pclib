<?php
/**
 * @file
 * MVC Model class.
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
 *  Base class for any application model.
 */
class Model extends system\BaseObject
{

protected $app;	

/** var Db */
public $db; //must be public because of service()

/** Name of source database table. */
protected $tableName;

/** Primary key column name. */
protected $primary = 'id';

/** Is model stored in db storage? */
protected $inDb = false;

/** var Tpl */
protected $template;

/** var Validator */
protected $validator;

/** Array of model values. */
protected $values = array();

/** Array of modified column names. */
protected $modified = array();

/** Role used by model for data access. */
protected $accessRole;

protected static $columnsCache = array();

/**
 * Create empty model.
 * @param Db $db
 * @param string $tableName Database table
 */
function __construct(App $app, $tableName)
{
	parent::__construct();

	$this->app = $app;

	$this->service('db');

	if (!$tableName) {
		throw new Exception("Empty table name.");
	}

	$this->tableName = $tableName;
}

/**
 * Use an existing template object.
 * @param Tpl $template
 */
function setTemplate(Tpl $template)
{
	$this->template = $template;
}

/**
 * Return model template. 
 * If not exists - create one.
 * @return Tpl $template
 */
function getTemplate()
{
	if (!$this->template) {
		$this->template = $this->createTemplate();
	}
	return $this->template;
}

function getValidator()
{
	if (!$this->validator) {
		$this->validator = new Validator($this->getTemplate());
	}
	return $this->validator;
}

/**
 * Return path to template file.
 * Override for your own template names and locations.
 * Default location: 'models/templates/Tablename.tpl'
 * @return string $path
 */
protected function getTemplatePath()
{
	return 'models/templates/'.ucfirst(strtolower($this->tableName)).'.tpl';
}

/**
 * Create template object from template file, or use default template.
 * @return Tpl $template
 */
protected function createTemplate()
{  
	$fileName = $this->getTemplatePath();
	if (file_exists($fileName)) {
		$t = new Tpl($fileName);
	}
	else {
		$t = $this->createDefaultTemplate();
	}

	$this->prepareTemplate($t);
	return $t;
}

/**
 * Create default template object based on table columns.
 * @return Tpl $template
 */
protected function createDefaultTemplate()
{
	if (!$this->tableName) throw new Exception('Missing table name.');
	$t = new Tpl;
	return $t;
}

protected function prepareTemplate(Tpl $t)
{
	foreach ($t->elements as $id => $el) {
		if ($el['type'] != 'role') continue;
		foreach (array('rights', 'read', 'write') as $k) {
			$t->elements[$id][$k.'_array'] = $el[$k]? explode(',', $el[$k]) : array();
		}
	}
}

protected function getRelations()
{
	$rel = array();

	foreach ($this->getTemplate()->elements as $id => $el) {
		if ($el['type'] != 'relation') continue;
		$rel[$id] = $el;
	}

	return $rel;
}


//nastavi propojeni s recordem v databazi (pk se nesmi jinak menit)
function setPrimaryId($id)
{
	$this->values[$this->primary] = (int)$id;
	$this->inDb = true;
}

function getPrimaryId()
{
	return $this->values[$this->primary];
}

/**
 * Find record by primary key and load values from db.
 * @param int $id primary key
 * @return App_Model $this|null
 */
function find($id)
{
	$this->values = $this->db->select($this->tableName, array($this->primary => $id));
	if ($this->values) $this->inDb = true;
	return $this->values? $this : null;
}

function getColumns()
{
	$cols = self::$columnsCache[$this->tableName];
	if (!$cols) {
		$cols = $this->db->columns($this->tableName);
		self::$columnsCache[$this->tableName] = $cols;
	}
	return $cols;
}

/** 
 * Check if model has column $name. 
 * @return bool $yes
 */
function hasColumn($name)
{
	$cols = $this->getColumns();
	$elem = $this->getTemplate()->elements;
	return $cols[$name] ?: ($elem[$name]['type'] == 'column');
}

/**
 * PHP magic method.
 * Implements following features:
 * - Access to column value as $model->columnName
 * - Access to related model(s) as $model->relationName
 */
public function __get($name)
{
	if ($this->getTemplate()->elements[$name]['type'] == 'relation') {
		return $this->related($name);
	}

	return $this->getValue($name);
}

/**
 * PHP magic method.
 * Implements following features:
 * - Access to column value as $model->columnName
 */
public function __set($name, $value)
{
	$this->setValue($name, $value);
}

/**
 * Return related model or selection of models.
 * Relation $name must be defined in template elements
 */
function related($name) {
	$def = $this->getTemplate()->elements[$name];
	$table = $def['table'];
	$foreignKey = $def['fk'];

	if (!$table or !$foreignKey) {
		throw new Exception("Missing table or key name.");
	}

	//cache?
	$sel = new Selection($this->app);
	$sel->from($table)->where(array($foreignKey => $this->getPrimaryId()));

	if ($def['many']) {
		return $sel;
	}
	else {
		return $sel->first();
	}
}

/**
 * Save model to the database.
 * @return bool $ok
 */
function save()
{
	if (!$this->testRight('save')) return false;

	if (!$this->modified) return true;
	$ok = $this->inDb? $this->update() : $this->insert();
	if ($ok) {
		$this->inDb = true;
		$this->modified = array();
	}
	return $ok;
}

protected function getValuesForSave()
{
	$values = array();
	$elem = $this->getTemplate()->elements;

	foreach ($this->modified as $name) {
		if ($elem[$name]['nosave']) continue;
		$values[$name] = $this->values[$name];
	}
	
	return $values;
}

function setRole($role)
{
	$el = $this->getTemplate()->elements[$role];
	if ($el['type'] != 'role') {
		throw new Exception("Unknown role '%s'", array($role));
	}

	$this->accessRole = $role;
}

function hasRight($action)
{
	if (!$this->accessRole) return true;
	$el = $this->getTemplate()->elements[$this->accessRole];

	if (is_array($action)) {
		$cols = $el[$action[0].'_array'];
		return (in_array($action[1], $cols) or in_array('*', $cols));
	}
	else {
		return in_array($action, $el['rights_array']);
	}
}

protected function testRight($action)
{
	if (!$this->hasRight($action)) {
		if (is_array($action)) { $action = implode(' ', $action); }
		throw new Exception("Access denied while '%s'", array($action));
		//return false;
	}
	else return true;
}

/** Insert new row into table with model values. */
protected function insert()
{
	if (!$this->testRight('insert')) return false;

	if (!$this->validate('insert')) return false;

	$id = $this->db->insert($this->tableName, $this->getValuesForSave());

	$this->setPrimaryId($id);
	return $id;
}

/** Update model values in database with actual state. */
protected function update()
{
	if (!$this->testRight('update')) return false;

	$id = $this->getPrimaryId();
	if (!$id) throw new Exception('Missing primary key.');

	if (!$this->validate('update')) return false;

	return $this->db->update($this->tableName, $this->getValuesForSave(), array($this->primary => $id));
}

/** 
 * Delete model in database, set Model->inDb flag to false. 
 * @return bool $ok 
 */
function delete()
{
	if (!$this->testRight('delete')) return false;

	if (!$this->inDb) return false;

	$id = $this->getPrimaryId();
	if (!$id) throw new Exception('Missing primary key.');

	if (!$this->validate('delete')) return false;

	$this->deleteRelated();
	$ok = $this->db->delete($this->tableName, array($this->primary => $id));
	if ($ok) {
		$this->inDb = false;
		$this->modified = array_keys($this->values);
	}

	return $ok;
}

protected function deleteRelated()
{
	foreach ($this->getRelations() as $rel) {
		if (!$rel['cascade']) continue;

		$found = $this->related($rel['id']);
		if ($found instanceof Selection and $found->isEmpty()) continue;
		if (!$found) continue;
		
		switch($rel['cascade']) {
			case 'delete':
				$found->delete();
			break;
			case 'error':
			default:
				throw new Exception("Record cannot be deleted, related records found: '%s'", array($rel['id']));
			break;
		}
	}
}

/** Occurs on validation error. */
function onError($action) {}

/** 
 * Validate values of the model against template.
 * Set validation errors - see getErrors()
 * @param string $action insert|update|delete
 * @return bool $isValid
 */
function validate($action = '')
{
	$ok = $this->getValidator()->validate($this->values);
	if (!$ok) $this->onError($action);
	return $ok;
}

/** 
 * Return array of validator errors.
 * @return array $errors [fieldName1: errorMessage1, ...]
 */
function getErrors()
{
	return $this->getValidator()->getErrors();
}

/** 
 * Get model values.
 * @return array $values
 */
function getValues()
{
	$values = array();
	foreach ($this->values as $k => $v) {
		if (!$this->hasRight(array('read', $k))) continue;
		$values[$k] = $this->getValue($k);
	}

	return $values;
}

/** 
 * Set model values.
 * @param array $values
 */
function setValues(array $values)
{
	foreach ($values as $k => $v) {
		$this->setValue($k, $v);
	}
}

/** 
 * Get model values.
 * @return array $values
 */
function toArray()
{
	return $this->getValues();
}

/** 
 * Set column $name to $value.
 */
function setValue($name, $value)
{
	if (!$this->hasColumn($name)) {
		$class = get_class($this);
		throw new MemberAccessException("Cannot write to an undeclared property $class->$name.");    
	}

	if ($this->values[$name] === $value) return;
	if ( !$this->testRight(array('write', $name)) ) return false;

	$this->values[$name] = $value;
	if (!in_array($name, $this->modified)) $this->modified[] = $name;
}

/** 
 * Get $value of column $name.
 */
function getValue($name)
{
	if (!$this->hasColumn($name)) {
		$class = get_class($this);
		throw new MemberAccessException("Cannot read an undeclared property $class->$name.");    
	}

	if ( !$this->testRight(array('read', $name)) ) return false;

	return $this->values[$name];
}

/**
 * PHP magic method.
 * Return json of model values in string context.
 */
function __toString()
{
	try {
		return json_encode($this->getValues());
	} catch (Exception $e) {
		trigger_error($e->getMessage(), E_USER_ERROR);
	}	
}

}

?>