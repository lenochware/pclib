<?php 
namespace pclib\system\storage;
use pclib\system\BaseObject;
use pclib\AuthUser;

/**
 * Default Auth storage.
 * Load user account, roles and rights from database table.
 */
class AuthDbStorage extends BaseObject
{

/** var Db */
public $db;

protected $USERS_TAB = 'AUTH_USERS',
	$REGISTER_TAB = 'AUTH_REGISTER',
	$ROLES_TAB    = 'AUTH_ROLES',
	$RIGHTS_TAB   = 'AUTH_RIGHTS',
	$USERROLE_TAB = 'AUTH_USER_ROLE';

/**
 * Load user data from database and return AuthUser object.
 * @param string $userName
 * @return AuthUser|null $user
 */
function getUser($userName)
{
	$this->service('db');
	$user = new AuthUser;
	$user->values = $this->getData($userName);
	$userId = array_get($user->values, 'ID');
	if (!$userId) return null;

	$user->values['roles'] = $this->getRoles($userId);
	$user->values['rights'] = $this->getRights($userId, $user->values['roles']);

	return $user;
}

/**
 * Update user values.
 * @param AuthUser $user
 */
function setUser(AuthUser $user)
{
	$this->service('db');
	
	$cols = $this->db->columns($this->USERS_TAB);
	$data = array_intersect_key($user->values, $cols);

	$this->db->update($this->USERS_TAB, $data, pri($user->values['ID']));
}

/**
 * Return userName, password and default password.
 * @param int $userId
 * @return array $credentials
 */
function getCredentials($userId)
{
	$this->service('db');
	$data = $this->db->select($this->USERS_TAB, pri($userId));	
	return array($data['USERNAME'], $data['PASSW'], $data['DPASSW']);
}

/** Get data from AUTH_USER, except password and default password. */
protected function getData($userName)
{
	$data = $this->db->select($this->USERS_TAB, "USERNAME='{0}'", $userName);
	$data['USES_DPASSW'] = ($data['PASSW'] == '');
	
	unset($data['PASSW'], $data['DPASSW']);
	return $data;
}

/** Get user roles. */
protected function getRoles($userId)
{
	$rids = $this->db->selectOne($this->USERROLE_TAB.':ROLE_ID',
		"USER_ID='{#0}' order by R_PRIORITY", $userId
	);

	if (!$rids) return array();

	$roles = $this->db->selectPair($this->ROLES_TAB.':ID,SNAME', "ID in ({0})", implode(',', $rids));
	return $roles;
}

/** Get user rights. */
protected function getRights($userId, array $roles)
{
	$rights = array();

	$pgsql = (get_class($this->db->drv) == 'pgsql')? $this->db->drv : null;

	$param['RIGHTS_TAB']   = $this->RIGHTS_TAB;
	$param['REGISTER_TAB'] = $this->REGISTER_TAB;
	$param['USERROLE_TAB'] = $this->USERROLE_TAB;
	$param['USER_ID'] = $userId;
	$param['USER_ROLES'] = $roles? implode(',', array_keys($roles)) : 0;

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
		if (!isset($rights[$rkey]))
			$rights[$rkey] = $r['RVAL'];
	}

	if ($pgsql) $pgsql->ucase--;

	return $rights;
}


}

?>