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

namespace pclib\orm;
use pclib\system\BaseObject;
use pclib\Tpl;
use pclib\Validator;
use pclib\MemberAccessException;
use pclib\Exception;

/**
 *  Base class for any application model.
 */
class Model extends BaseObject
{

/** var Db */
public $db; //must be public because of service()

/** Name of source database table. */
protected $tableName;

/** Is model stored in database? */
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

protected $relationsCache = array();

protected static $columnsCache = array();

protected static $options = array(
	'primaryKey' => 'ID',
	'defaultClassName' => '\pclib\orm\Model',
	'dir' => 'models',
);

/**
 * Create new model.
 * @param string $tableName Database table
 * @param array $values Model values
 */
function __construct($tableName, array $values = array())
{
	parent::__construct();

	$this->service('db');

	if (!preg_match("/^[a-z0-9_.]+$/i", $tableName)) {
		throw new Exception("Invalid table name.");
	}

	$this->tableName = $tableName;
	$this->setValues($values);
}

/**
 * Create new model and store it to database.
 * @param string $tableName Database table
 * @param array $values Model values
 * @param bool $doSave Save to db?
 */
static function create($tableName, array $values = array(), $doSave = true)
{
	$className = self::className($tableName);
	$model = new $className($tableName, $values);

	if ($doSave) $model->save();

	return $model;
}

static function setOptions(array $options)
{
	self::$options = $options + self::$options;
}

/**
 * Return model class name for $tableName and include model source file.
 * @param string $tableName Database table
 */
public static function className($tableName)
{
	$className = self::canonicalName($tableName).'Model';
	$path = self::getFilePath($className, '.php');

	if (file_exists($path)) {
		require_once($path);
		return $className;
	}

	//if (class_exists($className)) return $className;

	return self::$options['defaultClassName'];
}

protected static function canonicalName($tableName)
{
	$s = '';
	if (strpos($tableName, '_') === false) {
		return ucfirst($tableName);
	}

	$a = explode('_', $tableName);
	foreach ($a as $part) {
		$s .= ucfirst($part);
	}

	return $s;
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

protected function getValidator()
{
	if (!$this->validator) {
		$this->validator = new Validator;
		$this->validator->skipUndefined = true;
		$this->validator->ignoredElements = array('relation', 'role', 'event');
	}
	return $this->validator;
}

/**
 * Return model database columns.
 * @return array $columns
 */
function getColumns()
{
	$cols = array_get(self::$columnsCache, $this->tableName);
	if (!$cols) {
		$cols = $this->db->columns($this->tableName);
		self::$columnsCache[$this->tableName] = $cols;
	}
	return $cols;
}

/**
 * Return underlying database table name.
 */
function getTableName()
{
	return $this->tableName;
}

/**
 * Return path to model/template file.
 * Override for your own model/template names and locations.
 * Default location: 'models/*'
 * @return string $path
 */
protected static function getFilePath($name, $ext)
{
	switch ($ext) {
		case '.tpl': return self::$options['dir'].'/templates/'.$name.'.tpl';
		case '.php': return self::$options['dir'].'/'.$name.'.php';
		default: throw new Exception('Unknown file extension: %s', $ext);
	}
}

/**
 * Create template object from template file, or use default template.
 * @return Tpl $template
 */
protected function createTemplate()
{  
	$fileName = self::getFilePath(self::canonicalName($this->tableName), '.tpl');
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
 * Create default template object (empty).
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
	$prepare = array(
		'role' => array('rights', 'read', 'write'),
		'event' => array('cancel_when', 'delete'),
	);
	$calculated = array();

	foreach ($t->elements as $id => $el) {
		if ($el['type'] == 'column' and ($el['get'] or $el['set'])) {
			$calculated[$id] = $id;
		}

		if (!$prepare[$el['type']]) continue;
		foreach ($prepare[$el['type']] as $k) {
			$t->elements[$id][$k.'_array'] = $el[$k]? explode(',', $el[$k]) : array();
		}
	}

	$t->elements['model']['calculated'] = $calculated;
}

function setPrimaryId($id)
{
	$this->values[self::$options['primaryKey']] = (int)$id;
}

function getPrimaryId()
{
	return $this->values[self::$options['primaryKey']];
}

/**
 * Find record by primary key and load values from db.
 * @param int $id primary key
 * @return Model $this|null
 */
function find($id)
{
	$this->values = $this->db->select($this->tableName, array(self::$options['primaryKey'] => $id)) ?: [];
	$this->isInDb((bool)$this->values);
	return $this->values? $this : null;
}

/** 
 * Return model for table $tableName.
 **/
protected function getModel($tableName, $id = null)
{
	$model = self::create($tableName, array(), false);
	if ($id) {
		$model->find($id);
	}

	return $model;
}

/**
 * Return orm\Selection class.
 **/
protected function selection($from = null)
{
	$sel = new \pclib\orm\Selection;
	if ($from) $sel->from($from);
	return $sel;
}

/** 
 * Check if model has column $name. 
 * @return bool $yes
 */
function hasColumn($name)
{
	$cols = $this->getColumns();
	$el = $this->getElement($name);
	return (bool)$cols[$name] ?: ($el['type'] == 'column');
}

protected function getElement($name)
{
	return array_get($this->getTemplate()->elements, $name);
}

protected function isCalculated($name)
{
	$calculated = $this->getTemplate()->elements['model']['calculated'];
	return isset($calculated[$name]);
}

/**
 * PHP magic method.
 * Implements following features:
 * - Access to column value as $model->columnName
 * - Access to related model(s) as $model->relationName
 */
public function __get($name)
{
	$el = $this->getElement($name);
	if ($el and $el['type'] == 'relation') {
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
function related($name)
{
	if ($this->relationsCache[$name]) {
		return $this->relationsCache[$name];
	}

	$rel = new Relation($this, $name);

	if ($rel->params['owner'] or $rel->params['one']) {
		$this->relationsCache[$name] = $rel->first();
		return $this->relationsCache[$name];
	}
	else return $rel;
}

/**
 * Get or set flag indicating if model has database representation.
 * However, actual values can differ from database - see $this->modified.
 * @return bool $inDb
 */
function isInDb($value = null)
{
	if (!isset($value)) return $this->inDb;

	if ($value) {
		$this->inDb = true;
		$this->modified = array();
	}
	if (!$value) {
		$this->inDb = false;
	}

	return $this->inDb;
}

/**
 * Save model to the database.
 * @return bool $ok
 */
function save()
{
	if (!$this->testRight('save')) return false;

	if (!$this->modified) return true;

	$this->trigger('model.before-save');

	$ok = $this->isInDb()? $this->update() : $this->insert();
	if ($ok) {
		$this->isInDb(true);
	}

	$this->trigger('model.after-save', ['ok' => $ok]);

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

/**
 * Choose access role.
 * Role permissions for the model can be defined in template.
 * @param string $role
 */
function setRole($role)
{
	$el = $this->getTemplate()->elements[$role];
	if ($el['type'] != 'role') {
		throw new Exception("Unknown role '%s'", array($role));
	}

	$this->accessRole = $role;
}

/**
 * Test if current role has right do $action.
 * @param string|array $action
 * @return bool $allowed
 */
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

	return $this->db->update($this->tableName, $this->getValuesForSave(), array(self::$options['primaryKey'] => $id));
}

/** 
 * Delete model in database, set Model->inDb flag to false. 
 * @return bool $ok 
 */
function delete()
{
	if (!$this->testRight('delete')) return false;

	if (!$this->isInDb()) return false;

	$id = $this->getPrimaryId();
	if (!$id) throw new Exception('Missing primary key.');

	if (!$this->validate('delete')) return false;

	$this->validateRelated();

	$this->trigger('model.before-delete');

	//startTransaction
	$ok = $this->db->delete($this->tableName, array(self::$options['primaryKey'] => $id));
	$this->deleteRelated();
	//commitTransaction

	if ($ok) {
		$this->isInDb(false);
		$this->modified = array_keys($this->values);
	}

	$this->trigger('model.after-delete', ['ok' => $ok]);
	return $ok;
}

/**
 * Check if rules in "event ondelete" are passed.
 */
protected function validateRelated()
{
	$el = $this->getTemplate()->elements['ondelete'];
	if ($el['type'] != 'event') return;

	foreach ($el['cancel_when_array'] as $relationId) {
		$found = $this->related($relationId);
		if ($found instanceof Selection and $found->isEmpty()) continue;
		if ($found) {
			throw new Exception("Record cannot be deleted, related records found: '%s'", array($relationId));
		}
	}
}

/**
 * Delete related models as defined in "event ondelete".
 */
protected function deleteRelated()
{
	$el = $this->getTemplate()->elements['ondelete'];
	if ($el['type'] != 'event') return;

	foreach ($el['delete_array']  as $relationId) {
		$found = $this->related($relationId);
		if ($found) $found->delete();
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
	$ok = $this->getValidator()->validateArray($this->values, $this->getTemplate()->elements);
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

	$el = $this->getElement('model');
	foreach ($el['calculated'] as $k) {
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
	if ($this->isCalculated($name)) {
		return $this->setCalculated($name, $value);
	}

	if (!$this->hasColumn($name)) {
		$class = get_class($this);
		throw new MemberAccessException("Cannot write to an undeclared property $class->$name.");    
	}

	if (array_get($this->values, $name) === $value) return;
	if ( !$this->testRight(array('write', $name)) ) return false;

	$this->values[$name] = $value;
	if (!in_array($name, $this->modified)) $this->modified[] = $name;
}

/** 
 * Get $value of column $name.
 */
function getValue($name)
{
	if ($this->isCalculated($name)) {
		return $this->getCalculated($name);
	}

	if (!$this->hasColumn($name)) {
		$class = get_class($this);
		throw new MemberAccessException("Cannot read an undeclared property $class->$name.");
	}

	if ( !$this->testRight(array('read', $name)) ) return false;
	return $this->values[$name];
}

protected function getCalculated($name)
{
	$el = $this->getElement($name);

	if (!method_exists($this, $el['get'])) {
		$class = get_class($this);
		throw new MemberAccessException("Cannot read calculated field $class->$name.");
	}

	return call_user_func(array($this, $el['get']));
}

protected function setCalculated($name, $value)
{
	$el = $this->getElement($name);

	if (!method_exists($this, $el['set'])) {
		$class = get_class($this);
		throw new MemberAccessException("Cannot write calculated field $class->$name.");
	}

	return call_user_func(array($this, $el['set']), $value);
}

/**
 * PHP magic method.
 * Return json of model values in string context.
 */
function __toString()
{
	try {
		return json_encode($this->getValues(), JSON_UNESCAPED_UNICODE);
	} catch (\Exception $e) {
		trigger_error($e->getMessage(), E_USER_ERROR);
	}	
}

}

?>