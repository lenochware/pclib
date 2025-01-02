<?php 
/**
 * @file
 * Class AuthManager - Auth entities (users,roles,rights) management.
 * @author -dk-
 * http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib\extensions;
use pclib\system\AuthBase;

/**
 * Auth entities (users,roles,rights) management.
 * Features:
 * - Manage auth entities: user, role and right (permission).
 * - You can create entity (mk), delete entity (rm), set entity, or 
 * assign one entity to another (grant).
 */
class AuthManager extends AuthBase
{

/** var Db */
public $db;

public $USERNAME_PATTERN = "/^[a-z0-9\._\-\@]+$/i";

public $defaultPasswordLength = 10;

public $USERS_TAB = 'AUTH_USERS',
	$REGISTER_TAB = 'AUTH_REGISTER',
	$ROLES_TAB    = 'AUTH_ROLES',
	$RIGHTS_TAB   = 'AUTH_RIGHTS',
	$USERROLE_TAB = 'AUTH_USER_ROLE';

function __construct($db = null)
{
	parent::__construct();
	$this->setOptions($this->app->config['service.auth'] ?? $this->app->config['pclib.auth']);
	$this->db = $db ?: $this->service('db');
}

/*
 * Setup this service from configuration file.
 */
public function setOptions(array $options)
{
	$this->passwordAlgo = $options['algo'];
	$this->secret = $options['secret'];	
	if (isset($options['dsn'])) $this->getStorage()->db = new \pclib\Db($options['dsn']);
}

/**
 * Translate "system name" of auth entity to numeric ID.
 * Entity can be role, right or user. For system name see column SNAME
 * in AUTH_* tables - ID is primary key from relevant db-table.
 *
 * @param string $sname "entity_name" or "#entity_id"
 * @param enum $type ("user", "role", "right")
 */
public function sname($sname, $type)
{
	if (substr($sname,0,1) == '#') return (int)substr($sname, 1);
	$sname = strtolower($sname);

	switch($type) {
		case 'user':
		list($id) = $this->db->select($this->USERS_TAB.':ID',
			"USERNAME='{0}'", $sname);
		break;

		case 'role':
		list($id) = $this->db->select($this->ROLES_TAB.':ID',
			"SNAME='{0}'", $sname);
		break;

		case 'right':
		list($id) = $this->db->select($this->RIGHTS_TAB.':ID',
			"SNAME='{0}'", $sname);
		break;
	}

	if (!$id) $this->setError("'%s' not found.", $sname);
	return (int)$id;
}

protected function modified($table, $id)
{
	$now = date('Y-m-d H:i:s');
	$this->db->update($table, "LASTMOD='$now'", ['ID' => $id]);
}

/**
 * Generate random password.
 * @return string $password
**/
function genPassw()
{
	return \pclib\Str::random($this->defaultPasswordLength);
}

/**
 * Make user $sname. If user exists, throw error.
 * @param string $sname username
 * @param string $fullName User's full name.
 * @param string $srole Role name or #id which will be assigned to user.
 * @param string $annot Annotation string.
 * @return int $id ID of new user
 */
function mkUser($sname, $fullName = null, $srole = null, $annot = '')
{
	if (!preg_match($this->USERNAME_PATTERN, $sname)) {
		$this->setError('Invalid username.');
		return false;
	}
	
	$sname = strtolower($sname);
	
	if ($this->db->exists($this->USERS_TAB, "USERNAME='{0}'", $sname)) {
		$this->setError('Error: User "%s" already exists.', $sname);
		return false;
	}

	if (!$fullName) $fullName = $sname;

	$user = array (
	'USERNAME' => $sname,
	'FULLNAME' => $fullName,
	//'DPASSW' => $this->genPassw(),
	'DT' => date("Y-m-d H:i:s"),
	'ANNOT' => $annot
	);

	$auth = $this->app->getService('auth');

	if (/*PCLIB_VERSION > '2.9.5' and*/ $auth and $auth->loggedUser) {
		$user['AUTHOR_ID'] = $auth->loggedUser->ID;
	}

	$id = $this->db->insert($this->USERS_TAB, $user);
	if ($srole) $this->uRole('#'.$id, $srole);
	return $id;
}

/**
 * Remove user $sname.
 * @param string $sname "entity_name" or "#entity_id"
 * @return bool $ok
 */
function rmUser($sname)
{
	$uid = $this->sname($sname, 'user');
	if (!$uid) return false;

	$this->db->delete( $this->REGISTER_TAB, "USER_ID='{0}'", $uid);
	$this->db->delete( $this->USERROLE_TAB, "USER_ID='{0}'", $uid);
	$this->db->delete( $this->USERS_TAB, ['ID' => $uid]);
	return $uid;
}

/**
 * Copy rights and roles from user $sname1 to user $sname2. Both must exists.
 * @param string $sname1 Source "user_name" or "#user_id"
 * @param string $sname2 Destination "user_name" or "#user_id"
 * @return int $n Number of copied entities.
 */
function cpUser($sname1, $sname2)
{
	$u1 = $this->sname($sname1, 'user');
	if (!$u1) return false;
	$u2 = $this->sname($sname2, 'user');
	if (!$u2) return false;

	$this->db->delete($this->REGISTER_TAB, "USER_ID='{0}'", $u2);
	$rights = $this->db->selectAll($this->REGISTER_TAB, "USER_ID='{0}'", $u1);

	$n = 0;
	foreach ($rights as $right) {
		$right['ROLE_ID'] = null;
		$right['USER_ID'] = $u2;
		$this->db->insert($this->REGISTER_TAB, $right);
		$n++;
	}

	$this->db->delete($this->USERROLE_TAB, "USER_ID='{0}'", $u2);
	$roles = $this->db->selectAll($this->USERROLE_TAB, "USER_ID='{0}'", $u1);

	foreach ($roles as $role) {
		$role['USER_ID'] = $u2;
		$this->db->insert($this->USERROLE_TAB, $role);
		$n++;
	}

	$this->modified($this->USERS_TAB, $u2);
	return $n;
}

/**
 * Make right $sname with annotation $annot. If right exists, throw error.
 * @param string $sname "entity_name"
 * @param string $annot annotation string
 * @return int $id ID of new right
 */
function mkRight($sname, $annot = '')
{
	if ($this->db->exists($this->RIGHTS_TAB, "SNAME='{0}'", $sname)) {
		$this->setError('Error: Permission "%s" already exists.', $sname);
		return false;
	}

	$right = array(
		'SNAME' => $sname,
		'ANNOT' => $annot,
		'DT' => date("Y-m-d H:i:s")
	);
	$id = $this->db->insert($this->RIGHTS_TAB, $right);
	return $id;
}

/**
 * Remove right $sname. If right is used in role or user, throw error.
 * @param string $sname "entity_name" or "#entity_id"
 * @param string $force Force remove even if right is in use.
 * @return bool $ok
 */
function rmRight($sname, $force = false)
{
	$rid = $this->sname($sname, 'right');
	if (!$rid) return false;
	if (!$force)
		if ($this->db->exists($this->REGISTER_TAB, "RIGHT_ID='{0}'", $rid)) {
			$this->setError('Cannot remove - %s is used.', $sname);
			return false;
		}
	$this->db->delete($this->RIGHTS_TAB, ['ID' => $rid]);
	if ($force) $this->db->delete($this->REGISTER_TAB, "RIGHT_ID='{0}'", $rid);
	return true;
}

/**
 * Change right values (SNAME, ANNOT, ...)
 * @param array $right
 * @return bool $ok
 */
function setRight($right)
{
	$id = $right['ID'];
	if (!$this->db->exists($this->RIGHTS_TAB, ['ID' => $id])) {
		$this->setError('Error: Right %s not exists.', '#'.$id);
		return false;
	}
	if ($this->db->exists($this->RIGHTS_TAB, "SNAME='{0}' AND ID<>'{1}'", $right['SNAME'], $id)) {
		$this->setError('Error: Right %s already exists.', $right['SNAME']);
		return false;
	}
	
	$this->db->update($this->RIGHTS_TAB, $right, ['ID' => $id]);
	if ($this->db->drv->error) {
		$this->setError($this->db->drv->error);
		return false;
	}

	return true;
}

/**
 * Make role $sname with annotation $annot. If role exists, throw error.
 * @param string $sname "entity_name"
 * @param string $annot annotation string
 * @return int $id ID of new role
 */
function mkRole($sname, $annot = '')
{
	if ($this->db->exists($this->ROLES_TAB, "SNAME='{0}'", $sname)) {
		$this->setError('Error: Role "%s" already exists.', $sname);
		return false;
	}

	$role = array(
		'SNAME' => $sname,
		'ANNOT' => $annot,
		'LASTMOD' => date("Y-m-d H:i:s"),
		'DT' => date("Y-m-d H:i:s")
	);

	$auth = $this->app->getService('auth');

	if (/*PCLIB_VERSION > '2.9.5' and*/ $auth and $auth->loggedUser) {
		$role['AUTHOR_ID'] = $auth->loggedUser->ID;
	}

	$id = $this->db->insert($this->ROLES_TAB, $role);
	return $id;
}


/**
 * Remove role $sname. If role is assigned to user, throw error.
 * @param string $sname "entity_name" or "#entity_id"
 * @param string $force Force remove even if role is in use.
 * @return bool $ok
 */
function rmRole($sname, $force = false)
{
	$rid = $this->sname($sname, 'role');
	if (!$rid) return false;
	if (!$force)
		if ($this->db->exists($this->USERROLE_TAB, "ROLE_ID='{0}'", $rid)) {
			$this->setError('Cannot remove - %s is used.', $sname);
			return false;
		}

	$this->db->delete($this->REGISTER_TAB, "ROLE_ID='{0}'", $rid);
	$this->db->delete($this->ROLES_TAB, ['ID' => $rid]);
	if ($force) $this->db->delete($this->USERROLE_TAB, "ROLE_ID='{0}'", $rid);
	return true;
}

/**
 * Copy rights from role $sname1 to role $sname2. Both must exists.
 * @param string $sname1 Source "role_name" or "#role_id"
 * @param string $sname2 Destination role_name" or "#role_id"
 * @return int $n Number of copied rights.
 */
function cpRole($sname1, $sname2)
{
	$r1 = $this->sname($sname1, 'role');
	if (!$r1) return false;
	$r2   = $this->sname($sname2, 'role');
	if (!$r2) return false;

	$this->db->delete($this->REGISTER_TAB, "ROLE_ID='{0}'", $r2);
	$rights = $this->db->selectAll($this->REGISTER_TAB, "ROLE_ID='{0}'", $r1);
	$n = 0;
	foreach ($rights as $right) {
		$right['ROLE_ID'] = $r2;
		$right['USER_ID'] = null;
		$this->db->insert($this->REGISTER_TAB, $right);
		$n++;
	}
	
	$this->modified($this->ROLES_TAB, $r2);
	return $n;
}

function setRole($role)
{
	$id = $role['ID'];
	if (!$this->db->exists($this->ROLES_TAB, ['ID' => $id])) {
		$this->setError('Error: Role %s not exists.', '#'.$id);
		return false;
	}
	if ($this->db->exists($this->ROLES_TAB, "SNAME='{0}' AND ID<>'{1}'", $role['SNAME'], $id)) {
		$this->setError('Error: Role %s already exists.', $role['SNAME']);
		return false;
	}

	$this->db->update($this->ROLES_TAB, $role, ['ID' => $id]);
	if ($this->db->drv->error) {
		$this->setError($this->db->drv->error);
		return false;
	}

	$this->modified($this->ROLES_TAB, $id);
	return true;
}

/**
 * Grant/revoke right $sright to role $srole. Both must exists.
 * @param string $srole "role_name" or "#role_id"
 * @param string $sright "right_name" or "#right_id"
 * @param string $rval Value of the right. If null, right is removed from role.
 * @param int $obj_id Resource object ID for which right is granted.
 * Value '0' means any object.
 * @return bool $ok
 */
function rGrant($srole, $sright, $rval = '1', $obj_id = 0)
{
	$role_id  = $this->sname($srole, 'role');
	if (!$role_id) return false;
	$right_id = $this->sname($sright, 'right');
	if (!$right_id) return false;

	$this->db->delete($this->REGISTER_TAB,
	"ROLE_ID='{0}' AND RIGHT_ID='{1}' AND OBJ_ID='{2}'",
	$role_id, $right_id, $obj_id);

	if (isset($rval)) {
		$right = array(
			'ROLE_ID' => $role_id,
			'RIGHT_ID' => $right_id,
			'OBJ_ID' => $obj_id,
			'RVAL' => $rval
		);

		$this->db->insert($this->REGISTER_TAB, $right);
	}
	$this->modified($this->ROLES_TAB, $role_id);
	return true;
}

/**
 * Grant/revoke right $sright to user $suser. Both must exists.
 * @param string $suser "user_name" or "#user_id"
 * @param string $sright "right_name" or "#right_id"
 * @param string $rval Value of the right. If null, right is removed from role.
 * @param int $obj_id Resource object ID for which right is granted.
 * Value '0' means any object.
 * @return bool $ok
 */
function uGrant($suser, $sright, $rval = '1', $obj_id = 0)
{
	$user_id  = $this->sname($suser, 'user');
	if (!$user_id) return false;
	$right_id = $this->sname($sright, 'right');
	if (!$right_id) return false;

	$this->db->delete($this->REGISTER_TAB,
	"USER_ID='{0}' AND RIGHT_ID='{1}' AND OBJ_ID='{2}'",
	$user_id, $right_id, $obj_id);
	if (isset($rval)) {
		$right = array(
			'USER_ID' => $user_id,
			'RIGHT_ID' => $right_id,
			'OBJ_ID' => $obj_id,
			'RVAL' => $rval
		);
		$this->db->insert($this->REGISTER_TAB, $right);
	}
	
	$this->modified($this->USERS_TAB, $user_id);
	return true;
}

/**
 * Assign/revoke role $srole to user $suser. Both must exists.
 * Last assigned role has highest priority.
 * See field R_PRIORITY in table AUTH_USER_ROLE - '1' means highest.
 * @param string $suser "user_name" or "#user_id"
 * @param string $srole "role_name" or "#role_id"
 * @param bool $assign assign/revoke
 * @param int $obj_id Resource object ID for which role is granted.
 * Value '0' means any object.
 * @return bool $ok
 */
function uRole($suser, $srole, $assign = true, $obj_id = 0)
{
	$uid = $this->sname($suser, 'user');
	if (!$uid) return false;
	$rid = $this->sname($srole, 'role');
	if (!$rid) return false;

	if ($assign) {
		if ($this->db->exists(
			$this->USERROLE_TAB, "USER_ID='{0}' AND ROLE_ID='{1}'", $uid, $rid)) {
				$this->setError('User already has this role.');
				return false;
			}

		$this->db->update($this->USERROLE_TAB, "R_PRIORITY = R_PRIORITY + 1",
			"USER_ID='{0}'", $uid);

		$role = array (
			'ROLE_ID' => $rid,
			'USER_ID' => $uid,
			'OBJ_ID' => $obj_id
		);
		$this->db->insert($this->USERROLE_TAB, $role);
	}
	else {
		$this->db->delete($this->USERROLE_TAB,
		 "USER_ID='{0}' AND ROLE_ID='{1}' AND OBJ_ID='{2}'", $uid, $rid, $obj_id
		);
	}
	$this->modified($this->USERS_TAB, $uid);
	return true;
}

/**
 * Return user account of user $sname (row from table AUTH_USERS)
 * @param string $sname User name or #id
 * @return array $user
 */
function getUser($sname)
{
	$uid = $this->sname($sname, 'user');
	if (!$uid) return false;
	$user = $this->db->select($this->USERS_TAB, ['ID' => $uid]);
	return $user;
}

/**
 * Set user account with array $user. Array $user must contain ID of user.
 * Throw error if user does not exists. Field PASSW is never set with this
 * function - use setpassw().
 * @param array $user User data - table AUTH_USERS will be updated with this.
 * @return bool $ok
 * @see setpassw()
 */
function setUser($sname, array $user)
{
	$uid = $this->sname($sname, 'user');
	if (!$this->db->exists($this->USERS_TAB, ['ID' => $uid])) {
		$this->setError('Error: User %s not exists.', '#'.$uid);
		return false;
	}
	unset($user['PASSW']);
	$user['LASTMOD'] = date("Y-m-d H:i:s");
	
	if (isset($user['USERNAME'])) {
		if (!preg_match($this->USERNAME_PATTERN, $user['USERNAME'])) {
			$this->setError('Invalid username.');
			return false;
		}

		$user['USERNAME'] = strtolower($user['USERNAME']);
	}

	$this->db->update($this->USERS_TAB, $user, ['ID' => $uid]);
	if ($this->db->drv->error) {
		$this->setError($this->db->drv->error);
		return false;
	}

	return true;
}

/**
 * Set password $passw for user $sname.
 * @param string $sname "user_name" or "#user_id"
 * @param string $passw Password
 * @return bool $ok
 */
function setPassw($sname, $passw)
{
	$uid = $this->sname($sname, 'user');
	if (!$uid) return false;
	if (strlen($passw) > 0) $passw = $this->passwordHash($passw);
	$this->db->update($this->USERS_TAB, "PASSW='$passw',DPASSW=''", ['ID' => $uid]);
	$this->modified($this->USERS_TAB, $uid);
	return true;
}

/**
 * Caution! Empty all AUTH tables!
 * @return bool $ok
 */
function deleteAllAuthData()
{
	//sqlite doesn't know TRUNCATE
	$this->db->query('DELETE FROM '.$this->REGISTER_TAB );
	$this->db->query('DELETE FROM '.$this->ROLES_TAB    );
	$this->db->query('DELETE FROM '.$this->RIGHTS_TAB   );
	$this->db->query('DELETE FROM '.$this->USERROLE_TAB );
	$this->db->query('DELETE FROM '.$this->USERS_TAB    );
	
	return true;
}

}

?>