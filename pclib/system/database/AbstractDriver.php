<?php
/**
 * @file
 * PClib database driver.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link https://pclib.brambor.net/
 * @license MIT (https://opensource.org/licenses/MIT)
 */

namespace pclib\system\database;
use pclib\DatabaseException;

/**
 * Base class for database driver.
 * Any database driver must implement this class.
 * Database drivers are stored in pclib/system/database/ directory and loaded by class Db automatically.
 */
abstract class AbstractDriver
{

/** Active database connection link.*/
public $connection;

/** Contains last error message - test it after calling any db-function
 * to check if any error occurs */
public $error;

public $lastQuery;

/** Store portable limit clausule */
protected $limit;

/** Store last query result resource. Type: resource */
public $res;

/** Create new connection even if connection with same params exists */
public $forceReconnect = true;

/** Required PHP extension - such as 'mysql'. */
public $extension;

/** Include internal details (such as SQL code) into error messages. */
public $verboseErrors = false;

/**
 * The portable way to perform limit.
 * Next query result will be limited according setLimit parameters.
 * @param int $numRows Number of rows returned
 * @param int $offset Offset from start
**/
function setLimit($numRows, $offset = 0)
{
	$this->limit = array((int)$numRows, (int)$offset);
}

function __construct()
{
	if ($this->extension and !extension_loaded($this->extension))
		 throw new DatabaseException("PHP extension '$this->extension' not loaded.");
}

/** Connect to database.
 * @param array $ds datasource: array(
		 'driver' => $driver,
		 'host'   => $host,
		 'dbname' => $dbname,
		 'user'   => $user,
		 'passw'  => $passw,
		 'codepage' => $codepage
		);
 **/
abstract function connect($ds);

/** Close database connection. */
abstract function close();

/** Seek resource $res to position $rowno. */
abstract function seek($res, $rowno);

/** Return last insert id */
abstract function getInsertId();

/** Return number of rows in result $res */
abstract function numRows($res = null);

/** Return number of rows affected. */
abstract function affectedRows($res = null);

/** Execute query $sql */
abstract function query($sql);

/**
 * Fetch one row from query result. If result $res is ommited,
 * it uses last query result.
 * @param resource $res Query result resource
 * @param string $fmt format of result a - assoc, o - object, r - row, ar - array, f - field
 * @return array|object $row result row
**/
abstract function fetch($res = null, $fmt = 'a');

/** Return last driver error message */
abstract function lastError();

/**
 * Set connection character set.
 * @param string $cp codepage - e.g. 'cp1250'
**/
abstract function codePage($cp);

/** Return current database name */
abstract function dbName();

/**
 * Return columns metadata (name,size,type,nullable,default) of table $table as associative array.
 * @return array $columns
 */
abstract function columns($table);

/**
 * Return indexes of table $table as associative array.
 */
abstract function indexes($table);

abstract function version();

/** Quote identifier. */
abstract function quote($str);

/**
 * Escape parameters for usage in database query.
 * @param string $str String (parameter) to be escaped
 * @param string $type Can be 'string' or 'ident'
 * @return string $str escaped string
**/
abstract function escape($str, $type = 'string');

}