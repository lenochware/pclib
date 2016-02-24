<?php
/**
 * @file
 * Class Auth and Auth_User - Authentication and authorization.
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
 * Provides authentication and authorization support.
 * Features:
 * - Unlimited number of users.
 * - Each user has assigned roles, each role has permissions.
 * - Passwords are ancrypted and stored in database.
 * - Exceptions for single users, default passwords, logging security issues and more.
 * @see AuthManager User account management.
 * @note
 * You must set config parameter `pclib.auth.secret` before using of this class!
 * This class uses database and sessions.
 */
class Auth extends system\AuthBase implements IService
{

/** var Auth_User User which is logged in. */
public $activeUser;

/*
 * var Auth_User Cache for function auth->user()
 */
protected $stranger = null;

/**
 * Initialisation usually comes as `$app->auth = new %Auth;`
 * @param Db $db Auth database (optional)
 */
function __construct(Db $db = null)
{
	parent::__construct($db);

	if (!session_id()) throw new RuntimeException('Session is required.');
	$this->activeUser = new Auth_User($this);
	$this->activeUser->loadSession();
	$this->activeUser->getRights();
}

/**
 * Take \b $user and log him in. See also #$activeUser.
 *
 * @param Auth_User User object
 */
function setActive(Auth_User $user)
{
	unset($user->data['PASSW'], $user->data['DPASSW']);
	$this->app->setSession('pclib.user', $user->data, $user->realm);
	$this->app->setSession('pclib.notlogged', null, $user->realm);
	$this->activeUser = $user;
}

/**
 * Authenticate user \b $uname with password \b $password. If user passed,
 * log him in. See also #$activeUser.
 *
 * @param string $uname User User name
 * @param string $password Password
 * @return bool $success
 */
function login($uname, $password)
{
	$user = new Auth_User($this);
	$user->getUser($uname);

	if (!$user->data or !$user->data['ACTIVE']) {
		$this->activeUser = null;
		$this->setError('User does not exists!');
		return false;
	}
	$passw_db = ifnot($user->data['PASSW'], $this->hashFunction($user->data['DPASSW'], $this->secret));

	if ($passw_db != $this->hashFunction($password, $this->secret)) {
		$this->activeUser = null;
		$this->setError('Invalid password!');
		$this->db->update($this->USERS_TAB, "LOGINFAIL=LOGINFAIL+1", pri($user->id));
		$this->app->log('AUTH_WARNING', 'PASSWORD_FAIL');
		return false;
	}

	$ip = ip2long($this->app->request->getClientIp());

	//O.K. - login!

	$user->data['LOGINTYPE']  = 'phpdb';
	$this->setActive($user);

	if ($ip != $user->data['IP']) $this->app->log('AUTH_NOTICE', 'IP_CHANGE');

	$now = date('Y-m-d H:i:s');
	$this->db->update(
		$this->USERS_TAB, "LAST_LOGIN = '$now',LOGINFAIL=0,IP='$ip'",
		pri($user->id)
	);

	return true;
}

/**
 * Logout active user.
 * @param bool $httpRedir Reload page, if login-type is http.
 */
function logout($httpRedir = true)
{
	$user = $this->app->getSession('pclib.user', $this->realm);
	$this->app->setSession('pclib.user', null, $this->realm);
	$this->activeUser = null;
	$this->app->deleteSession();

	//HTTP-logout hack
	if ($user['LOGINTYPE'] == 'httpbasic') {
		$this->app->setSession('pclib.http_auth_prompt', null, $this->realm);
		//header('HTTP/1.1 401 Unauthorized');
		if ($httpRedir) header("Location: " . $_SERVER['PHP_SELF']);
	}

	return true;
}

/**
 * Authenticate user with http-authentication method. If user passed,
 * log him in.
 */
function loginHttp()
{
	if ($this->isLogged()) return;

	//show login prompt
	if (!isset($_SERVER['PHP_AUTH_USER']) or !$this->app->getSession('pclib.http_auth_prompt', $this->realm)) {
		header("WWW-Authenticate: Basic realm=\"$this->realm\"");
		header('HTTP/1.1 401 Unauthorized');
		$this->app->setSession('pclib.http_auth_prompt', true, $this->realm);
		die($this->t('This page requires authentication.'));
	}

	$user = new Auth_User($this);
	$user->getUser($_SERVER['PHP_AUTH_USER']);

	if (!$user->data or !$user->data['ACTIVE']) {
		$this->activeUser = null;
		$this->setError('User does not exists!');
		$this->app->setSession('pclib.http_auth_prompt', null, $this->realm);
		header("Location: " . $_SERVER['PHP_SELF']);
		exit();
	}

	$passw_db = ifnot($user->data['PASSW'], $this->hashFunction($user->data['DPASSW'], $this->secret));

	if ($passw_db != $this->hashFunction($_SERVER['PHP_AUTH_PW'], $this->secret)) {
		$this->activeUser = null;
		$this->setError('Invalid password!');
		$this->db->update($this->USERS_TAB, "LOGINFAIL=LOGINFAIL+1", pri($user->id));
		$this->app->log('AUTH_WARNING', 'PASSWORD_FAIL');
		$this->app->setSession('pclib.http_auth_prompt', null, $this->realm);
		header("Location: " . $_SERVER['PHP_SELF']);
		exit();
	}

	//O.K. - login!

	$user->data['LOGINTYPE']  = 'httpbasic';
	$this->setActive($user);

	$ip = ip2long($this->app->request->getClientIp());
	if ($ip != $user->data['IP']) $this->app->log('AUTH_NOTICE', 'IP_CHANGE');

	$now = date('Y-m-d H:i:s');
	$this->db->update(
		$this->USERS_TAB, "LAST_LOGIN = '$now',LOGINFAIL=0,IP='$ip'",
		pri($user->id)
	);

	return true;
}

//for compatibility
function login_Http()
{
	return $this->loginHttp();
}

/**
 * Return data of logged in user (row from table AUTH_USER).
 * @return array $activeUser
 */
function getUser()
{
	if (!$this->activeUser) return false;
	return $this->activeUser->data;
}

/**
 * Take username and return Auth_User object.
 * @param string $sname "username" or "#user_id"
 * @return Auth_User $user
 */
function user($sname)
{
	$uid = $this->sname($sname, 'user');
	if (!$this->stranger or $this->stranger->id != $uid) {
		$this->stranger = new Auth_User($this);
		$this->stranger->getUser('#'.$uid);
	}

	return $this->stranger;
}

/* Return array of user IDs with role $sname */
function getUsers($sname, $filter = 'role')
{
	$roleid = $this->sname($sname, 'role');
	return $this->db->selectOne("select U.ID from AUTH_USERS U join AUTH_USER_ROLE UR on UR.USER_ID=U.ID and UR.ROLE_ID='{0}'", $roleid);
}

/**
 * Is user logged in?
 * @return bool
 */
function isLogged()
{
	if (!$this->activeUser) return false;
	return $this->activeUser->isLogged();
}

/**
 * Does use active user default password?
 * @return bool
 */
function hasDPassw()
{
	if (!$this->activeUser) return false;
	return $this->activeUser->hasDPassw();
}

/**
 * Enable default password for active user. Current password is deleted.
 * @param bool $gener Generate new password string?
 * @return string $dpassw Default password.
 */
function dPassw($gener = false)
{
	if (!$this->activeUser) return false;
	return $this->activeUser->dPassw($gener);
}

/**
 * Check if active user has assigned permission $sname
 * @param string $sname Permission. Example: 'shop/products/delete'
 * @param int $obj_id resource object id
 * @return bool
**/
function hasRight($sname, $obj_id = 0)
{
	if (!$this->activeUser) return false;
	return $this->activeUser->hasRight($sname, $obj_id);
}

/**
 * Exit application and write security issue into LOG 
 * if user has not permission $sname.
 * @param string $sname "right_name" or "#right_id"
 * @param int $obj_id resource object id
 * @see hasRight()
 */
function testRight($sname, $obj_id = 0)
{
	if (!$this->activeUser or !$this->activeUser->hasRight($sname, $obj_id)) {
		$r_id = $this->sname($sname, 'right');

		$this->app->log('AUTH_ERROR', 'TESTRIGHT', null, $r_id);
		throw new AuthException("Required permission $sname. Access denied.");
	}
}

/**
 * Return value of configuration key $sname assigned to active user.
 * @param string $sname "cfkey_name" or "#cfkey_id"
 * @param int $obj_id resource object id
 * @return string $value
 */
function getCfKey($sname, $obj_id = 0)
{
	if (!$this->activeUser) return false;
	return $this->activeUser->getCfKey($sname, $obj_id);
}

} //class Auth

/**
 * Provides access to user account, user roles and permissions.
 * When Auth::login() is successfull, Auth creates this 
 * object and store it as Auth::$activeUser.
 * You can get user object for any `username` with Auth::user() method.
 */
class Auth_User extends system\AuthBase
{

/** user ID (primary key in table AUTH_USERS) */
public $id = 0;

/** User account data. Example: `$user->data["FULLNAME"]` */
public $data = array();

/** User rights. */
protected $rights = array();

function __construct(Auth $auth) {
	parent::__construct($auth->db);
	$this->secret = $auth->secret;
}

/**
 * Is logged in?
 * @return bool
 */
function isLogged()
{
	$user = $this->app->getSession('pclib.user', $this->realm);
	return ($this->id and $this->id == $user['ID']);
}

/**
 * Has default password?
 * @return bool
 */
function hasDPassw()
{
	list($passw) = $this->db->select($this->USERS_TAB.':passw', pri($this->id));
	if ($this->db->count() == 0) return 0;
	return $passw? 0:1;
}

/**
 * Enable default password. Current password is deleted.
 * @param bool $gener Generate new password?
 * @return string $dpassw Default password.
 */
function dPassw($gener = false)
{
	if ($gener) {
		$dpassw = $this->genPassw();
		$this->db->update($this->USERS_TAB, "DPASSW='$dpassw'", pri($this->id));
		return $dpassw;
	}

	list($dpassw) = $this->db->select($this->USERS_TAB.':DPASSW', pri($this->id));
	return $dpassw;
}

/**
 * Set user password.
 * @param string $passw Password.
 */
function setPassw($passw)
{
	if (strlen($passw) < 6)
		trigger_error($this->t('Password is too short!'), E_USER_WARNING);

	$hpassw = $this->hashFunction($passw, $this->secret);
	$this->db->update($this->USERS_TAB, "PASSW='$hpassw'", pri($this->id));
}

/**
 * Check if user has permission $sname.
 * @param string $sname Permission.
 * @param int $obj_id resource object id
 * @return bool
**/
function hasRight($sname, $obj_id = 0)
{
	return $this->getCfKey($sname, $obj_id);
}

/**
 * Return value of configuration key $sname assigned to the user.
 * @param string $sname "cfkey_name" or "#cfkey_id"
 * @param int $obj_id resource object id
 * @return string $value
 */
function getCfKey($sname, $obj_id = 0)
{
	 if ($obj_id) {
		 $rval = $this->getCfKey("$obj_id:$sname");
		 if ($rval !== false) return $rval;
	 }

	 foreach($this->rights as $rkey => $rval){
		 if (fnmatch($rkey, $sname)) return $rval;
	 }
	 return false;
}

/**
 * Read user data from database. Fill #$data and #$rights.
 * @param string $sname "username" or "#user_id"
 * @return array user data
 */
function getUser($sname = null)
{
	if (!isset($sname)) $uid = $this->data['ID'];
	else $uid = $this->sname($sname, 'user');

	$pgsql = (get_class($this->db->drv) == 'pgsql')? $this->db->drv : null;

	if ($pgsql) $pgsql->ucase++;
	$this->data = (array)$this->db->select($this->USERS_TAB, pri($uid));
	if ($pgsql) $pgsql->ucase--;
	$this->id = (int)$this->data['ID'];
	if (!$this->data) return false;

	$roles_a = $this->db->selectOne(
		"select ROLE_ID from {0} where USER_ID='{#1}' order by R_PRIORITY",
		$this->USERROLE_TAB, $this->id
	);
	$this->data['ROLES'] = $roles_a? implode(',', $roles_a) : '0';
	$this->data['SECURESTRING'] = $this->getSecureString($this->data);
	$this->data['LOGINTYPE']  = 'none';
	$this->getRights();
	return $this->data;
}

/**
 * Read user rights from database. Fill #$rights.
 */
function getRights()
{
	$this->rights = array();
	if (!$this->id) return false;

	$pgsql = (get_class($this->db->drv) == 'pgsql')? $this->db->drv : null;

	$param['RIGHTS_TAB']   = $this->RIGHTS_TAB;
	$param['REGISTER_TAB'] = $this->REGISTER_TAB;
	$param['USERROLE_TAB'] = $this->USERROLE_TAB;
	$param['USER_ID'] = $this->id;
	$param['USER_ROLES'] = ifnot($this->data['ROLES'], '0');

	//replace *->zzzz - konkretnejsi pravo ma prednost
	//coalesce(R_PRIORITY) - individualni pravo ma prednost, role podle priorit

	$q = $this->db->query(
	"select RI.SNAME as RKEY,RVAL, CASE WHEN (R.OBJ_ID = 0) THEN R.OBJ_ID ELSE RO.OBJ_ID END as ROBJ
	from {REGISTER_TAB} R
	left join {USERROLE_TAB} RO on RO.ROLE_ID = R.ROLE_ID and RO.USER_ID='{USER_ID}'
	left join {RIGHTS_TAB} RI on RI.ID = R.RIGHT_ID
	where R.ROLE_ID in ({USER_ROLES}) or R.USER_ID='{USER_ID}'
	order by COALESCE(RO.R_PRIORITY,0),REPLACE(RI.SNAME,'*','zzzz'),RVAL", $param
	);

	$rkey = '';
	if ($pgsql) $pgsql->ucase++;

	while ($r = $this->db->fetch($q)) { //pgsql lowercase issue
		$rkey = $r['ROBJ']? $r['ROBJ'].':'.$r['RKEY'] : $r['RKEY'];
		if (!isset($this->rights[$rkey]))
			$this->rights[$rkey] = $r['RVAL'];
	}

	if ($pgsql) $pgsql->ucase--;

	return true;
}

/**
 * Read user data from session.
 */
function loadSession()
{
	$this->data = $this->app->getSession('pclib.user', $this->realm);
	$this->id = (int)$this->data['ID'];
	if (!$this->data) return false;

	if (!$this->validate()) {
		$this->app->log('AUTH_ERROR', 'SESSION_INVALID');
		throw new AuthException("Authentication failed. Access denied.");
	}
	return true;
}

/**
 * Validate session.
 */
function validate()
{
	if (!$this->data) return false;
	return ($this->data['SECURESTRING'] == $this->getSecureString($this->data));
}

} //class Auth_User

?>