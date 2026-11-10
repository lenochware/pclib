<?php
/**
 * @file
 * PClib database driver.
 * Database drivers are stored in pclib/system/database/ directory and loaded by class db automatically.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link https://pclib.brambor.net/
 * @license MIT (https://opensource.org/licenses/MIT)
 */

namespace pclib\system\database;
use pclib\DatabaseException;
use pclib\NotImplementedException;

/**
 * SQLite database driver.
 * Implements support of %sqlite database engine.
 */
class PdoSqliteDriver extends PdoDriver
{

public $extension = 'pdo_sqlite';

function connect(
	#[\SensitiveParameter]
	$ds
)
{
	/*if (!file_exists($ds['path']))
		throw new FileNotFoundException('File '.$ds['path'].' not found.');*/
	return $this->pdoConnect('sqlite:'.$ds['path']);
}

function query($sql)
{
	$this->error = '';

	if ($this->limit) {
		$sql .= " LIMIT ".$this->limit[0]." OFFSET ".$this->limit[1];
		$this->limit = null;
	}

	$stmt = $this->connection->query($sql);

	if (!$stmt) {
		$this->error = $this->lastError().' Query: '.$sql;
		$msg = $this->verboseErrors? ' '.$this->error : '';
		throw new DatabaseException('Query error.'.$msg);
	}

	$this->lastQuery = $sql;
	$this->res = $stmt;
	return $stmt;
}

//Not supported by SQLite
function codePage($cp)
{
	throw new NotImplementedException;
}

function dbName()
{
	//Path of the main database file
	$q = $this->query("PRAGMA database_list");
	$row = $this->fetch($q);
	return $row['file'] ?? null;
}

function columns($table)
{
	if (strpos($table, '.')) list($dbname, $table) = explode('.', $table);
	//if (!$dbname) $dbname = $this->dbname();

	$q = $this->query("PRAGMA TABLE_INFO ('$table')");

	$columns = array();
	while ($row = $this->fetch($q, 'o')) {
		$type = $this->u_type($row->type);

		$columns[$row->name] = array(
		'name' => $row->name,
		'type' => $type[0],
		'size' => $type[1],
		'nativetype' => $row->type,
		'nullable' => (!$row->notnull),
		'default' => $row->dflt_value,
		);
	}
	return $columns;
}

private function u_type($type)
{
	$size = null;
	
	//e.g. 'varchar(255)'
	if (preg_match('/^(.+?)\s*\(\s*(\d+)/', $type, $m)) {
		$type = $m[1];
		$size = (int)$m[2];
	}

	$type = strtolower(trim($type));

	$native = array(
	'integer' => 'int:8','bit' => 'bool',
	'nvarchar' => 'string','nchar' => 'string','date' => 'date:1',
	'datetime' => 'date:2',
	'money' => 'float:8','integer' => 'int:4','int' => 'int:4',
	'smallint' => 'int:2','ntext' => 'string:65535','image' => 'binary',
	);
	$type = $native[$type] ?? null;
	if (!$type) return array(null, $size);
	$type = explode(':', $type);
	if (empty($type[1])) $type[1] = $size;
	return $type;
}


function indexes($table)
{
	throw new NotImplementedException;
}

function version()
{
	$q = $this->query('select sqlite_version()');
	$row = $this->fetch($q, 'r');
	return $row[0];
}

function now()
{
	return date("Y-m-d H:i:s");
}

function extendEngine()
{
	$this->connection->sqliteCreateFunction('now', array($this, 'now'), 0);
}

} //class

?>