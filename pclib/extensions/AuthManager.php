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
 * - Support of auth console. See \ref aterm-cmds for more info.
 */
class AuthManager extends AuthBase
{

/* Helper for execute() */
private $masterCmd = array();

/** Array of AuthManager messages */
public $messages = array();

public $USERNAME_PATTERN = "/^[a-z0-9\._-]+$/i";

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
	'DPASSW' => $this->genPassw(),
	'DT' => date("Y-m-d H:i:s"),
	'ANNOT' => $annot
	);

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
	$this->db->delete( $this->USERS_TAB, pri($uid));
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
	$this->db->delete($this->RIGHTS_TAB, pri($rid));
	if ($force) $this->db->delete($this->REGISTER_TAB, "RIGHT_ID='{0}'", $rid);
	return true;
}

function setRight($right)
{
	$id = $right['ID'];
	if (!$this->db->exists($this->RIGHTS_TAB, pri($id))) {
		$this->setError('Error: Right %s not exists.', '#'.$id);
		return false;
	}
	if ($this->db->exists($this->RIGHTS_TAB, "SNAME='{0}' AND ID<>'{1}'", $right['SNAME'], $id)) {
		$this->setError('Error: Right %s already exists.', $right['SNAME']);
		return false;
	}
	
	$this->db->update($this->RIGHTS_TAB, $right, pri($id));
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
	$this->db->delete($this->ROLES_TAB, pri($rid));
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
	if (!$this->db->exists($this->ROLES_TAB, pri($id))) {
		$this->setError('Error: Role %s not exists.', '#'.$id);
		return false;
	}
	if ($this->db->exists($this->ROLES_TAB, "SNAME='{0}' AND ID<>'{1}'", $role['SNAME'], $id)) {
		$this->setError('Error: Role %s already exists.', $role['SNAME']);
		return false;
	}

	$this->db->update($this->ROLES_TAB, $role, pri($id));
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
	"ROLE_ID={0} AND RIGHT_ID={1} AND OBJ_ID='{2}'",
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
	"USER_ID={0} AND RIGHT_ID={1} AND OBJ_ID='{2}'",
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
			$this->USERROLE_TAB, "USER_ID={0} AND ROLE_ID={1}", $uid, $rid)) {
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
	$user = $this->db->select($this->USERS_TAB, pri($uid));
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
	if (!$this->db->exists($this->USERS_TAB, pri($uid))) {
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

	$this->db->update($this->USERS_TAB, $user, pri($uid));
	if ($this->db->drv->error) {
		$this->setError($this->db->drv->error);
		return false;
	}

	return true;
}

/**
 * Set password $passw for user $sname. Password with length < 6
 * generates warning. Empty password will enable default password.
 * @param string $sname "user_name" or "#user_id"
 * @param string $passw Password
 * @return bool $ok
 */
function setPassw($sname, $passw)
{
	/*if (strlen($passw) > 0 and strlen($passw) < 6) {
		$this->seterror(0, 'Password is too short!');
		return false;
	}*/
	$uid = $this->sname($sname, 'user');
	if (!$uid) return false;
	if (strlen($passw) > 0) $passw = $this->hashFunction($passw, $this->secret);
	$this->db->update($this->USERS_TAB, "PASSW='$passw'", pri($uid));
	$this->modified($this->USERS_TAB, $uid);
	return true;
}
/**
 * Caution! Empty all AUTH tables! Cannot be reversed!
 * @param string $confirm Confirm delete
 * @return bool $ok
 */
function removeAll($confirm = false)
{
	if (!$confirm) return false;
	
	$this->db->query('DELETE FROM '.$this->REGISTER_TAB );
	$this->db->query('DELETE FROM '.$this->ROLES_TAB    );
	$this->db->query('DELETE FROM '.$this->RIGHTS_TAB   );
	$this->db->query('DELETE FROM '.$this->USERROLE_TAB );
	$this->db->query('DELETE FROM '.$this->USERS_TAB    );
	
	return true;
}

/*function mkobject($ext_id, $sname = '', $annot = '') {}
function rmobject ($id, $force = false) {}*/

/**
 * Execute ATERM batch file. Generated messages are stored in $this->messages.
 * @param string $fileName Filename of batch file
 * @return bool $ok
 * See \ref aterm-cmds for description of aterm language.
 */
function executeFile($fileName)
{
	$batch = file($fileName);
	if (!$batch) return false;
	$ok = true;
	foreach($batch as $line) {
		if (!$this->execute($line)) $ok = false;
	}
	return $ok;
}

/**
 * Execute ATERM commands. Generated messages are stored in $this->messages.
 * @param string $line ATERM commands
 * @return bool $ok
 * See \ref aterm-cmds for description of aterm language.
 */
function execute($line)
{
	$line = trim($line);
	if ($line{0} == ';' or $line == '') return true;
	if ($line{0} == '&') {
		if (!$this->masterCmd) {
			$this->setError('Runtime error.');
			return false;
		}
		$line = substr($line,1);
	}
	else $this->masterCmd = null;

	if ($pos = utf8_strpos($line, ';')) $line = utf8_substr($line, 0, $pos);

	$keywords = "user|role|right|active|passw|dpassw";
	$patt = '/([+-\? ])\s*('.$keywords.')\s(\s*([\w\/\*]+))?(\s*\"([^\"]+)\")?/i';
	//utf8_preg_match_all()?
	$terms_n = preg_match_all($patt, ' '.$line.' ', $terms, PREG_SET_ORDER);
	if (!$terms_n) {
			$this->setError('Syntax error in `%s`', $line);
			return false;
	}
	if ($terms[0][1] == '?') {
		$this->query($terms);
		return true;
	}

	if (!$this->masterCmd) {
		$master = array_shift($terms);
		$ok = $this->executeCmd($master);
		if (!$ok) return false;
		$this->masterCmd = $master;
	}
	if ($terms)
		foreach($terms as $term) {
			$ok = $this->executeCmd($term);
			if (!$ok) return false;
		}
}

/** Helper for query() */
private function addHtmlClass($value, $clsid)
{
	return "<span class=\"$clsid\">$value</span>";
}

/** Build filter for querying users. Return filter array. Used in query(). */
protected function userFilter(array $terms)
{
	if (!$terms) return array();

	$filter = array();
	foreach ($terms as $cmd) {
		$op    = trim($cmd[1]);
		$ty   = $cmd[2];
		$name  = $cmd[4];
		switch ($ty) {
			case 'active':  $filter['ACTIVE'] = ($op == '+')? 1:0; break;
			case 'dpassw':
				if ($op == '+') $filter['DPASSW'] = 1;
				else $filter['PASSW'] = 1;
			break;
			case 'right':
				$rid = $this->sname($name, 'right');
				if (!$rid) break;
				if ($filter['RIGHT'] or $op != '+') {
					$this->setError('Runtime error in `%s`', $cmd[0]);
					break;
				}

				$roles = $this->db->selectOne(
					$this->REGISTER_TAB.':ROLE_ID',
					"RIGHT_ID='{0}' and RVAL<>'0' and ROLE_ID is not NULL", $rid
				);
				$filter['RIGHT'] = $rid;
				if ($roles) {
					$filter['RROLES'] = implode(',', $roles);
					$filter['RRIGHT'] = $filter['RIGHT'];
					unset($filter['RIGHT']);
				}
			break;
			case 'role':
				$rid = $this->sname($name, 'role');
				if (!$rid) break;
				if ($filter['ROLE'] or $op != '+') {
					$this->setError('Runtime error in `%s`', $cmd[0]);
					break;
				}
				$filter['ROLE'] = $rid;
			break;
			default: $this->setError('Runtime error in `%s`', $cmd[0]); break;
		}

	}
	return $filter;
}

/**
 * Perform '?entity' commands. Store result in $this->messages.
 * Used in function execute().
 */
protected function query(array $terms)
{
	$master = array_shift($terms);
	$op    = trim($master[1]);
	$ty   = $master[2];
	$name  = strtr($master[4],'*','%');
	$annot = $master[6];
	
	$pgsql = (get_class($this->db->drv) == 'pgsql')? $this->db->drv : null;

	if ($op != '?') return false;
	switch ($ty) {
	case 'user':
		$user_n = $this->db->count($this->USERS_TAB, "USERNAME like '{0}'", $name);
		if ($user_n > 1) {
			$filter = $this->userFilter($terms);
			if ($this->errors) break;
			$filter['USERNAME'] = $name;
			$users = $this->db->selectOne(
				"select distinct U.USERNAME from $this->USERS_TAB U
				~ left join $this->REGISTER_TAB REG on REG.USER_ID=U.ID
				~ left join $this->USERROLE_TAB UR on UR.USER_ID=U.ID
				where U.USERNAME like '{USERNAME}'
				~ AND U.ACTIVE='{ACTIVE}'
				~ AND (U.PASSW='' OR U.PASSW is NULL) {?DPASSW}
				~ AND LENGTH(U.PASSW)>0 {?PASSW}
				~ AND REG.RIGHT_ID='{RIGHT}' AND REG.RVAL<>'0'
				~ AND ((REG.RIGHT_ID='{RRIGHT}' AND REG.RVAL<>'0') OR UR.ROLE_ID in ({RROLES}))
				~ AND UR.ROLE_ID='{ROLE}'", $filter
			);
			if ($users) $this->messages[] = wordwrap(implode(' ', $users), 60, '<br>');
			$this->messages[] = "\nFound ".count($users)." users.";
		}
		elseif ($user_n == 1) {
			if ($pgsql) $pgsql->ucase++;
			$user = $this->db->select($this->USERS_TAB, "USERNAME like '{0}'", $name);
			if ($pgsql) $pgsql->ucase--;
			if ($user['PASSW']) $user['PASSW'] = '########';
			foreach($user as $k => $v) {
				$msg .= strtolower($k). ': '.$this->addHtmlClass($v, 'console-value').'<br>';
			}
			$this->messages[] = $msg;
			$roles = $this->db->selectOne(
				"select R.SNAME from $this->ROLES_TAB R
				inner join $this->USERROLE_TAB UR on R.ID=UR.ROLE_ID
				where UR.USER_ID='{0}'
				order by UR.R_PRIORITY desc", (int)$user['ID']
			);
			$this->messages[] = 'user roles: '
				.($roles? implode(', ', $roles) : '');

			$rights = $this->db->selectPair(
				"select SNAME,REG.RVAL from $this->RIGHTS_TAB R
				inner join $this->REGISTER_TAB REG on REG.RIGHT_ID=R.ID
				where REG.USER_ID='{0}'", (int)$user['ID']
			);

			if ($rights) {
				$r = null; foreach($rights as $k => $v) {$r[] = "$k ($v)";}
				$this->messages[] = 'user rights:<br>'.implode('<br>', $r);
			}
			else $this->messages[] = 'user rights: -';
		}
		else {
			$this->setError('User %s not exists.', $name);
			return false;
		}
	break;
	case 'role':
		$role_n = $this->db->count($this->ROLES_TAB, "SNAME like '{0}'", $name);
		if ($role_n > 1) {
			$roles = $this->db->selectOne(
				$this->ROLES_TAB.':SNAME',"SNAME like '{0}'", $name);
			$this->messages[] = wordwrap(implode(' ', $roles), 60, '<br>');
			$this->messages[] = "\nFound ".count($roles)." roles.";
		}
		elseif ($role_n == 1) {
			$msg = '';
			if ($pgsql) $pgsql->ucase++;
			$role = $this->db->select($this->ROLES_TAB, "SNAME like '{0}'", $name);
			if ($pgsql) $pgsql->ucase--;
			foreach($role as $k => $v) {$msg .= strtolower($k). ": $v<br>";}
			$this->messages[] = $msg;

			$rights = $this->db->selectPair(
				"select SNAME,REG.RVAL from $this->RIGHTS_TAB R
				inner join $this->REGISTER_TAB REG on REG.RIGHT_ID=R.ID
				where REG.ROLE_ID='{#0}'",$role['ID']
			);

			$msg = '';
			foreach((array)$rights as $k => $v) {$msg .= "$k ($v)<br>";}
			$this->messages[] = 'Rights:<br>'.$msg;

			$role_n = $this->db->count(
				$this->USERROLE_TAB, "ROLE_ID='{#0}'", $role['ID']);
			$this->messages[] = "Assigned to $role_n users.";
		}
		else {
			$this->setError('Role %s not exists.', $name);
			return false;
		}
	break;

	case 'right':
		$right_n = $this->db->count($this->RIGHTS_TAB, "SNAME like '{0}'", $name);
		if ($right_n > 1) {
			$rights = $this->db->selectOne(
				$this->RIGHTS_TAB.':SNAME',"SNAME like '{0}'", $name);
			$this->messages[] = implode('<br>', $rights);
			$this->messages[] = "\nFound ".count($rights)." rights.";
		}
		elseif ($right_n == 1) {
			$msg = '';
			$right = $this->db->select($this->RIGHTS_TAB, "SNAME like '{0}'", $name);
			foreach($right as $k => $v) { $msg .= "$k: $v<br>";}
			$this->messages[] = $msg;

			$roles = $this->db->selectOne(
				"select SNAME from $this->ROLES_TAB RO
				inner join $this->REGISTER_TAB REG on REG.ROLE_ID=RO.ID
				where REG.RIGHT_ID='{#0}'", $right['ID']
			);

			if ($roles)
				$this->messages[] = 'In roles: '
					.wordwrap(implode(' ', $roles), 60, '<br>');

		}
		else {
			$this->setError('Right %s not exists.', $name);
			return false;
		}

	break;
	case 'dpassw':
	$uid = $this->sname($name, 'user');
	if (!$uid) return false;
	$user = $this->db->select($this->USERS_TAB, pri($uid));
	$msg = "dpassw: ".$this->addHtmlClass($user['DPASSW'],'console-value')." enabled: ";
	$msg .= $user['PASSW']? 'no.':'yes.';
	$this->message($msg);
	break;

	default: return false;
	}
	return true;
}

/** Execute one ATERM command. */
protected function executeCmd($cmd)
{
	$op    = trim($cmd[1]);
	$opt   = $cmd[1].$cmd[2];
	$name  = $cmd[4];
	$annot = $cmd[6];

	if (!$op) return true;

	if (!$this->masterCmd) {
		switch ($opt) {
			case '+role':
				$ok = $this->mkRole($name, $annot);
				if ($ok) $this->message("Role `%s` added.", $name);
			break;
			case '-role':
				$force = ($annot == 'force');
				$ok = $this->rmRole($name, $force);
				if ($ok) $this->message("Role `%s` removed.", $name);
			break;
			case '+right':
				$ok = $this->mkRight($name, $annot);
				if ($ok) $this->message("Right `%s` added.", $name);
			break;
			case '-right':
				$force = ($annot == 'force');
				$ok = $this->rmRight($name, $force);
				if ($ok) $this->message("Right `%s` removed.", $name);
			break;
			case '+user':
				if (strpos($annot, '|')) {
					list ($fullname,$annot) = explode('|', $annot);
					$fullname = trim($fullname);
					$annot = trim($annot);
				}
				else {
					$fullname = $annot;
					$annot = null;
				}

				$ok = $this->mkUser($name, $fullname, null, $annot);
				if ($ok) $this->message("User `%s` added.", $name);
				break;
			case '-user':
				$ok = $this->rmUser($name);
				if ($ok) $this->message("User `%s` removed.", $name);
			break;

			default:
				$ok = false;
				$this->setError('Syntax error in `%s`', $cmd[0]);
				break;
		}
		return $ok;
	}

	$m_ty = $this->masterCmd[2];
	$m_name = $this->masterCmd[4];

	$ok = false;
	switch ($opt) {
		case '+role':
			if ($m_ty == 'user') $ok = $this->uRole($m_name, $name);
			else $this->setError('Runtime error in `%s`', $cmd[0]);
			if ($ok) $this->message("Granted role `%s` to user `%s`.", array($name, $m_name));
			break;

		case '-role':
			if ($m_ty == 'user') $ok = $this->uRole($m_name, $name, false);
			else $this->setError('Runtime error in `%s`', $cmd[0]);
			if ($ok) $this->message("Revoked role `%s` from user `%s`.", array($name, $m_name));
		break;

		case '+right':
			$rval = isset($annot)? $annot : '1';
			if ($m_ty == 'user') $ok = $this->uGrant($m_name, $name, $rval);
			elseif ($m_ty == 'role') $ok = $this->rGrant($m_name, $name, $rval);
			else $this->setError('Runtime error in `%s`', $cmd[0]);
			if ($ok) $this->message("Granted right `%s` to %s `%s`.",
				array($name, $m_ty, $m_name));
		break;

		case '-right':
			if ($m_ty == 'user') $ok = $this->uGrant($m_name, $name, null);
			elseif ($m_ty == 'role') $ok = $this->rGrant($m_name, $name, null);
			else $this->setError('Runtime error in `%s`', $cmd[0]);
			if ($ok) $this->message("Revoked right `%s` from %s `%s`.",
				array($name, $m_ty, $m_name));
		break;

		case '+user':
			if ($m_ty == 'role') $ok = $this->uRole($name, $m_name);
			else $this->setError('Runtime error in `%s`', $cmd[0]);
			if ($ok) $this->message("Granted role `%s` to user `%s`.", array($m_name, $name));
	 break;

		case '-user':
			if ($m_ty == 'role') $ok = $this->uRole($name, $m_name, false);
			else $this->setError('Runtime error in `%s`', $cmd[0]);
			if ($ok) $this->message("Revoked role `%s` from user `%s`.", array($m_name, $name));
		break;

		case '+active':
			if ($m_ty == 'user') {
				$user = array();
				$uid = $this->sname($m_name, 'user');
				$user['ACTIVE'] = 1;
				$ok = $this->setUser('#'.$uid, $user);
				if ($ok) $this->message("User `%s` enabled.", $m_name);
			}
			else $this->setError('Runtime error in `%s`', $cmd[0]);
		break;

		case '-active':
			if ($m_ty == 'user') {
				$user = array();
				$uid = $this->sname($m_name, 'user');
				$user['ACTIVE'] = 0;
				$ok = $this->setUser('#'.$uid, $user);
				if ($ok) $this->message("User `%s` disabled.", $m_name);
			}
			else $this->setError('Runtime error in `%s`', $cmd[0]);
		break;

		case '+dpassw':
			if ($m_ty == 'user') {
				$passw = $name? $name : $annot;
				$user = array();

				$uid = $this->sname($m_name, 'user');
				$user['DPASSW'] = $passw? $passw : $this->genPassw();
				$ok = $this->setUser('#'.$uid, $user);
				if ($ok) $this->setPassw($m_name, '');
				if ($ok) $this->message("Default password enabled: %s", $user['DPASSW']);
			}
			else $this->setError('Runtime error in `%s`', $cmd[0]);
		break;

		case '+passw':
			$passw = $name? $name : $annot;
			if ($m_ty == 'user') $ok = $this->setPassw($m_name, $passw);
			else $this->setError('Runtime error in `%s`', $cmd[0]);
			if ($ok) $this->message("Password set for user `%s`.", $m_name);
		break;

		default:
			$this->setError('Syntax error in `%s`', $cmd[0]);
		break;
	}
	return $ok;
}

/**Set message to $this->messages. */
protected function message($message, $params)
{
	$this->messages[] = vsprintf($this->t($message), $params);
}


//sqlite doesn't know NOW()
protected function modified($table, $id)
{
	$now = date('Y-m-d H:i:s');
	$this->db->update($table, "LASTMOD='$now'", pri($id));
}

}
