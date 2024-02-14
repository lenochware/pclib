<?php 

namespace pclib\extensions;
use pclib\system\AuthBase;

/**
 * Execute auth console commands, see execute() method.
 */
class AuthConsole extends AuthBase

{

/** var Db */
public $db;

/** var AuthManager */
protected $mng;

/* Helper for execute() */
private $masterCmd = array();

/** Array of AuthConsole messages */
public $messages = array();

function __construct(AuthManager $mng)
{
	parent::__construct();
	$this->mng = $mng;
	$this->db = $this->mng->db;
}

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
 * Execute ATERM script.
 * @param string $s ATERM script
 * @return bool $ok
 * See \ref aterm-cmds for description of aterm language.
 */
function executeScript($s)
{
	$batch = explode("\n", $s);
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
	if ($line[0] == ';' or $line == '') return true;
	if ($line[0] == '&') {
		if (!$this->masterCmd) {
			$this->setError('Runtime error.');
			return false;
		}
		$line = substr($line,1);
	}
	else $this->masterCmd = null;

	if ($pos = utf8_strpos($line, ';')) $line = utf8_substr($line, 0, $pos);

	$keywords = "user|role|right|active|passw|dpassw";
	$patt = '/([+-\? ])\s*('.$keywords.')\s(\s*([\w\/\*\.]+))?(\s*\"([^\"]+)\")?/i';
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
		if (!$ok) {
			$this->setError($this->mng->errors[0]);
			return false;
		}
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

/** Build filter for querying roles. */
protected function roleFilter(array $terms)
{
	if (!$terms) return array();
	$filter = array();
	foreach ($terms as $cmd) {
		$op    = trim($cmd[1]);
		$ty   = $cmd[2];
		$name  = $cmd[4];
		switch ($ty) {
			case 'right':
				$rid = $this->mng->sname($name, 'right');

				if (!$rid) {
					$this->setError('Right `%s` does not exist.', $name);
					break;
				}

				$filter['RIGHT'] = $rid;
				break;
		}
	}	

	return $filter;
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
				$rid = $this->mng->sname($name, 'right');

				if (!$rid) {
					$this->setError('Right `%s` does not exist.', $name);
					break;
				}

				if ($filter['RIGHT'] or $op != '+') {
					$this->setError('Runtime error in `%s`', $cmd[0]);
					break;
				}

				$roles = $this->db->selectOne(
					$this->mng->REGISTER_TAB.':ROLE_ID',
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
				$rid = $this->mng->sname($name, 'role');

				if (!$rid) {
					$this->setError('Role `%s` does not exist.', $name);
					break;
				}

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
	$annot = array_get($master, 6);
	
	$pgsql = (get_class($this->db->drv) == 'pgsql')? $this->db->drv : null;

	if ($op != '?') return false;
	switch ($ty) {
	case 'user':
		$user_n = $this->db->count($this->mng->USERS_TAB, "USERNAME like '{0}'", $name);
		if ($user_n > 1) {
			$filter = $this->userFilter($terms);
			if ($this->errors) break;
			$filter['USERNAME'] = $name;
			$users = $this->db->selectOne(
				"select distinct U.USERNAME from {$this->mng->USERS_TAB} U
				~ left join {$this->mng->REGISTER_TAB} REG on REG.USER_ID=U.ID
				~ left join {$this->mng->USERROLE_TAB} UR on UR.USER_ID=U.ID
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
			$user = $this->db->select($this->mng->USERS_TAB, "USERNAME like '{0}'", $name);
			if ($pgsql) $pgsql->ucase--;
			if ($user['PASSW']) $user['PASSW'] = '########';
			foreach($user as $k => $v) {
				$msg .= strtolower($k). ': '.$this->addHtmlClass($v, 'console-value').'<br>';
			}
			$this->messages[] = $msg;
			$roles = $this->db->selectOne(
				"select R.SNAME from {$this->mng->ROLES_TAB} R
				inner join {$this->mng->USERROLE_TAB} UR on R.ID=UR.ROLE_ID
				where UR.USER_ID='{0}'
				order by UR.R_PRIORITY desc", (int)$user['ID']
			);
			$this->messages[] = 'user roles: '
				.($roles? implode(', ', $roles) : '');

			$rights = $this->db->selectPair(
				"select SNAME,REG.RVAL from {$this->mng->RIGHTS_TAB} R
				inner join {$this->mng->REGISTER_TAB} REG on REG.RIGHT_ID=R.ID
				where REG.USER_ID='{0}'", (int)$user['ID']
			);

			if ($rights) {
				$r = null; foreach($rights as $k => $v) {$r[] = "$k ($v)";}
				$this->messages[] = 'user rights:<br>'.implode('<br>', $r);
			}
			else $this->messages[] = 'user rights: -';
		}
		else {
			$this->setError('User `%s` does not exist.', $name);
			return false;
		}
	break;
	case 'role':
		$role_n = $this->db->count($this->mng->ROLES_TAB, "SNAME like '{0}'", $name);
		if ($role_n > 1) {
			$filter = $this->roleFilter($terms);
			if ($this->errors) break;
			$filter['SNAME'] = $name;

			$roles = $this->db->selectOne(
				"select distinct R.SNAME from {$this->mng->ROLES_TAB} R
				~ left join {$this->mng->REGISTER_TAB} REG on REG.ROLE_ID=R.ID
				where R.SNAME like '{SNAME}'
				~ AND REG.RIGHT_ID='{RIGHT}' AND REG.RVAL<>'0'",
				$filter
			);

			$this->messages[] = wordwrap(implode(' ', $roles), 60, '<br>');
			$this->messages[] = "\nFound ".count($roles)." roles.";
		}
		elseif ($role_n == 1) {
			$msg = '';
			if ($pgsql) $pgsql->ucase++;
			$role = $this->db->select($this->mng->ROLES_TAB, "SNAME like '{0}'", $name);
			if ($pgsql) $pgsql->ucase--;
			foreach($role as $k => $v) {$msg .= strtolower($k). ": $v<br>";}
			$this->messages[] = $msg;

			$rights = $this->db->selectPair(
				"select SNAME,REG.RVAL from {$this->mng->RIGHTS_TAB} R
				inner join {$this->mng->REGISTER_TAB} REG on REG.RIGHT_ID=R.ID
				where REG.ROLE_ID='{#0}'",$role['ID']
			);

			$msg = '';
			foreach((array)$rights as $k => $v) {$msg .= "$k ($v)<br>";}
			$this->messages[] = 'Rights:<br>'.$msg;

			$role_n = $this->db->count(
				$this->mng->USERROLE_TAB, "ROLE_ID='{#0}'", $role['ID']);
			$this->messages[] = "Assigned to $role_n users.";
		}
		else {
			$this->setError('Role `%s` does not exist.', $name);
			return false;
		}
	break;

	case 'right':
		$right_n = $this->db->count($this->mng->RIGHTS_TAB, "SNAME like '{0}'", $name);
		if ($right_n > 1) {
			$rights = $this->db->selectOne(
				$this->mng->RIGHTS_TAB.':SNAME',"SNAME like '{0}'", $name);
			$this->messages[] = implode('<br>', $rights);
			$this->messages[] = "\nFound ".count($rights)." rights.";
		}
		elseif ($right_n == 1) {
			$msg = '';
			$right = $this->db->select($this->mng->RIGHTS_TAB, "SNAME like '{0}'", $name);
			foreach($right as $k => $v) { $msg .= "$k: $v<br>";}
			$this->messages[] = $msg;

			$roles = $this->db->selectOne(
				"select SNAME from {$this->mng->ROLES_TAB} RO
				inner join {$this->mng->REGISTER_TAB} REG on REG.ROLE_ID=RO.ID
				where REG.RIGHT_ID='{#0}'", $right['ID']
			);

			if ($roles)
				$this->messages[] = 'In roles: '
					.wordwrap(implode(' ', $roles), 60, '<br>');

		}
		else {
			$this->setError('Right `%s` does not exist.', $name);
			return false;
		}

	break;
	case 'dpassw':
	$uid = $this->mng->sname($name, 'user');
	if (!$uid) return false;
	$user = $this->db->select($this->mng->USERS_TAB, pri($uid));
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
	$annot = array_get($cmd, 6);

	if (!$op) return true;

	if (!$this->masterCmd) {
		switch ($opt) {
			case '+role':
				$ok = $this->mng->mkRole($name, $annot);
				if ($ok) $this->message("Role `%s` added.", $name);
			break;
			case '-role':
				$force = ($annot == 'force');
				$ok = $this->mng->rmRole($name, $force);
				if ($ok) $this->message("Role `%s` removed.", $name);
			break;
			case '+right':
				$ok = $this->mng->mkRight($name, $annot);
				if ($ok) $this->message("Right `%s` added.", $name);
			break;
			case '-right':
				$force = ($annot == 'force');
				$ok = $this->mng->rmRight($name, $force);
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

				$ok = $this->mng->mkUser($name, $fullname, null, $annot);
				if ($ok) {
					$password = $this->mng->genPassw();
					$this->mng->setPassw($name, $password);
					$this->message("User `%s` added with password: %s", [$name, $password]);
				}
				break;
			case '-user':
				$ok = $this->mng->rmUser($name);
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
			if ($m_ty == 'user') $ok = $this->mng->uRole($m_name, $name);
			else $this->setError('Runtime error in `%s`', $cmd[0]);
			if ($ok) $this->message("Granted role `%s` to user `%s`.", array($name, $m_name));
			break;

		case '-role':
			if ($m_ty == 'user') $ok = $this->mng->uRole($m_name, $name, false);
			else $this->setError('Runtime error in `%s`', $cmd[0]);
			if ($ok) $this->message("Revoked role `%s` from user `%s`.", array($name, $m_name));
		break;

		case '+right':
			$rval = isset($annot)? $annot : '1';
			if ($m_ty == 'user') $ok = $this->mng->uGrant($m_name, $name, $rval);
			elseif ($m_ty == 'role') $ok = $this->mng->rGrant($m_name, $name, $rval);
			else $this->setError('Runtime error in `%s`', $cmd[0]);
			if ($ok) $this->message("Granted right `%s` to %s `%s`.",
				array($name, $m_ty, $m_name));
		break;

		case '-right':
			if ($m_ty == 'user') $ok = $this->mng->uGrant($m_name, $name, null);
			elseif ($m_ty == 'role') $ok = $this->mng->rGrant($m_name, $name, null);
			else $this->setError('Runtime error in `%s`', $cmd[0]);
			if ($ok) $this->message("Revoked right `%s` from %s `%s`.",
				array($name, $m_ty, $m_name));
		break;

		case '+user':
			if ($m_ty == 'role') $ok = $this->mng->uRole($name, $m_name);
			else $this->setError('Runtime error in `%s`', $cmd[0]);
			if ($ok) $this->message("Granted role `%s` to user `%s`.", array($m_name, $name));
	 break;

		case '-user':
			if ($m_ty == 'role') $ok = $this->mng->uRole($name, $m_name, false);
			else $this->setError('Runtime error in `%s`', $cmd[0]);
			if ($ok) $this->message("Revoked role `%s` from user `%s`.", array($m_name, $name));
		break;

		case '+active':
			if ($m_ty == 'user') {
				$user = array();
				$uid = $this->mng->sname($m_name, 'user');
				$user['ACTIVE'] = 1;
				$ok = $this->mng->setUser('#'.$uid, $user);
				if ($ok) $this->message("User `%s` enabled.", $m_name);
			}
			else $this->setError('Runtime error in `%s`', $cmd[0]);
		break;

		case '-active':
			if ($m_ty == 'user') {
				$user = array();
				$uid = $this->mng->sname($m_name, 'user');
				$user['ACTIVE'] = 0;
				$ok = $this->mng->setUser('#'.$uid, $user);
				if ($ok) $this->message("User `%s` disabled.", $m_name);
			}
			else $this->setError('Runtime error in `%s`', $cmd[0]);
		break;

		case '+dpassw':
			if ($m_ty == 'user') {
				$passw = $name? $name : $annot;
				$user = array();

				$uid = $this->mng->sname($m_name, 'user');
				$password = $passw? $passw : $this->mng->genPassw();
				$ok = $this->mng->setPassw('#'.$uid, $password);
				if ($ok) $this->message("Default password set: %s", $password);
			}
			else $this->setError('Runtime error in `%s`', $cmd[0]);
		break;

		case '+passw':
			$passw = $name? $name : $annot;
			if ($m_ty == 'user') $ok = $this->mng->setPassw($m_name, $passw);
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
	$this->messages[] = $this->app->text($message, $params);
}

}

?>