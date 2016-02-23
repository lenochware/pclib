<?php
/**
 * @file
 * PClib database driver.
 * Database drivers are stored in pclib/system/database/ directory and loaded by class db automatically.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link http://pclib.brambor.net/
 */

namespace pclib\system\database;
use pclib\system\DatabaseException;
use pclib\system\NotImplementedException;

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

/**
 * Abstract PDO database driver.
 * PDO driver for each database engine is derived from this class.
 */
abstract class PdoDriver extends AbstractDriver
{

function pdoConnect($dsn, $user = null, $password = null)
{
	try {
		$pdo = new \PDO($dsn, $user, $password);
		$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION /*SILENT?*/);
		$this->connection = $pdo;
		return $this->connection;
	} catch (\PDOException $e) {
		$this->error = $e->getMessage();
		$msg = $this->verboseErrors? ' '.$this->error : '';
		throw new DatabaseException('Connection error.'.$msg, 0/*, $e*/);
	}
}


function close()
{
	$this->connection = null;
}

function seek($res, $rowno)
{
	throw new NotImplementedException('Not supported by PDO.');
}

function getInsertId()
{
	return $this->connection->lastInsertId();
}

function fetch($res = null, $fmt = 'a')
{
	if (!$res) return array();
	switch ($fmt) {
		case 'f' : $row = $res->fetch(\PDO::FETCH_NUM);
							 return $row[0];
		case 'o' : return $res->fetch(\PDO::FETCH_OBJ);
		case 'r' : return $res->fetch(\PDO::FETCH_NUM);
		case 'ar': return $res->fetch(\PDO::FETCH_BOTH);
		case 'a' :
		default  : return $res->fetch(\PDO::FETCH_ASSOC);
	}
}

function numRows($res = null)
{
	if (!$res) $res = $this->res;
	if (!$res) return 0;
	$q = $this->query('select count(*) from ('.$res->queryString.') as Q');
	return $this->fetch($q, 'f');
}

function affectedRows($res = null)
{
	return $res?  $res->rowCount() : 0;
}

function lastError()
{
	return $this->connection->errorInfo();
}

function quote($str)
{
	return $this->connection->quote($str);
}

function escape($str, $type = 'string')
{
	if ($type == 'ident') return $this->quote(pcl_ident($str));
	if (!$str or is_numeric($str)) return $str;
	return substr($this->connection->quote($str),1,-1);
}

function version()
{
	$q = $this->query('select version()');
	$row = $this->fetch($q, 'r');
	return $row[0];
}

} //class

?>