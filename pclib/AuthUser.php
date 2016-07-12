<?php
/**
 * @file
 * Class AuthUser.
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
 * Provides access to user account, user roles and permissions.
 * When Auth->login() is successfull, Auth stores user object as Auth->loggedUser.
 * You can get user object for any $userName with Auth->getUser($userName).
 */
class AuthUser extends system\BaseObject
{

/** array User account values */
public $values;

/** var Auth */
public $auth;

/**
 * Check if user is logged in.
 * @return bool $yes
 */
function isLogged()
{
	return ($this->service('auth')->loggedUser === $this);
}

/**
 * Check if user exists and he is active.
 * @return bool $yes
 */
function isValid()
{
	return ($this->values['ID'] and $this->values['ACTIVE']);
}

/**
 * Check if user has permission $name.
 * @param string $name Permission
 * @param int $objectId Resource object id
 * @return bool $yes
 */
function hasRight($name, $objectId = 0)
{
	if ($objectId) {
		return $this->hasRight("$objectId:$name");
	}

	foreach($this->values['rights'] as $rkey => $rval) {
		if (fnmatch($rkey, $name)) return $rval;
	}

	return false;
}

/**
 * Check if user has role $role.
 * @param string $role Role
 * @return bool $yes
 */
function hasRole($role)
{
	return in_array($role, $this->values['roles']);
}

/**
 * Check if user uses default password.
 * @return bool $yes
 */
function hasDefaultPassword()
{
	return $this->values['USES_DPASSW'];
}

/**
 * Return user values.
 * @return array $values
 */
function getValues()
{
	return $this->values;
}

/**
 * Return array [userName, password, defaultPassword].
 * @return array $credentials
 */
function getCredentials()
{
	return $this->service('auth')->getStorage()->getCredentials($this->values['ID']);
}

/**
 * Verify user password.
 * @param string $password
 * @return bool $valid
 */
function passwordVerify($password)
{
	$cred = $this->getCredentials();

	if ($this->hasDefaultPassword()) {
		return ($cred[2] == $password);
	}
	else return $this->service('auth')->passwordHashVerify($password, $cred[1]);
}

}

?>