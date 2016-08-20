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

/** var Db */
protected $db;

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
protected $values;

/**
 * Create empty model.
 * @param Db $db
 * @param string $tableName Database table
 */
function __construct(Db $db, $tableName) {
	$this->db = $db;
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
		return new Tpl($fileName);
	}
	else {
		return $this->createDefaultTemplate();
	}
}

/**
 * Create default template object based on table columns.
 * @return Tpl $template
 */
protected function createDefaultTemplate()
{
	if (!$this->tableName) throw new Exception('Missing table name.');
	$columns = array_keys($this->db->columns($this->tableName));
	$t = new Tpl;
	foreach ($columns as $id) {
		$t->elements[$id]['type'] = 'column';
		$t->elements[$id]['id'] = $key;
	}
	return $t;
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
 * @return App_Model $this
 */
function find($id) {
	$this->values = $this->db->select($this->tableName, array($this->primary => $id));
	if ($this->values) $this->inDb = true;
	return $this;
}

/** 
 * Check if model has column $name. 
 * @return bool $yes
 */
function hasColumn($name) {
	return ($this->getTemplate()->elements[$name]['type'] == 'column');
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

	//$prim = strtoupper($this->primary); //hack

	//cache?
	$sel = new Selection($this->db);
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
	$ok = $this->inDb? $this->update() : $this->insert();
	if ($ok) $this->inDb = true;
	return $ok;
}

/** Insert new row into table with model values. */
protected function insert()
{
	if (!$this->validate('insert')) return false;

	$id = $this->db->insert($this->tableName, $this->values);
	$this->setPrimaryId($id);
	return $id;
}

/** Update model values in database with actual state. */
protected function update()
{
	$id = $this->getPrimaryId();
	if (!$id) throw new Exception('Missing primary key.');

	if (!$this->validate('update')) return false;

	return $this->db->update($this->tableName, $this->values, array($this->primary => $id));
}

/** 
 * Delete model in database, set Model->inDb flag to false. 
 * @return bool $ok 
 */
function delete()
{
	if (!$this->inDb) return false;

	$id = $this->getPrimaryId();
	if (!$id) throw new Exception('Missing primary key.');

	if (!$this->validate('delete')) return false;

	$ok = $this->db->delete($this->tableName, array($this->primary => $id));
	if ($ok) $this->inDb = false;
	return $ok;
}

/** Occurs on validation error. */
function onError() {}

/** 
 * Validate values of the model against template.
 * Set validation errors - see getErrors()
 * @param string $action insert|update|delete
 * @return bool $isValid
 */
function validate($action = '')
{
	$ok = $this->getValidator()->validate($this->values);
	if (!$ok) $this->onError();
	return $ok;
}

/** 
 * Return array of validator errors.
 * @return array $errors [fieldName1: errorMessage1, ...]
 */
function getErrors()
{
	return $this->getValidator()->errors;
}


/** 
 * Get model values.
 * @return array $values
 */
function getValues()
{
	return $this->values;
}

/** 
 * Set model values.
 * @param array $values
 */
function setValues(array $values)
{
	$this->values = $values;
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

	$this->values[$name] = $value;
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

	return $this->values[$name];
}

/**
 * PHP magic method.
 * Return json of model values in string context.
 */
function __toString()
{
	return json_encode($this->getValues());
}

}

?>