<?php
/**
 * @file
 * Http authentication.
 * @author -dk-
 * http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib\extensions;
use pclib;

/**
 * Http authentication.
 * Usage: Call $auth->loginHttp(); before any protected page of the web.
 */
class AuthHttp extends pclib\Auth implements pclib\IService
{


protected function authPrompt()
{
	header("WWW-Authenticate: Basic realm=\"$this->realm\"");
	header('HTTP/1.1 401 Unauthorized');
	die($this->app->text('This page requires authentication.'));
}

protected function verifyCredentials(array $credentials)
{
	return (
		$this->loggedUser->values['USERNAME'] == $credentials[0]
		and $this->loggedUser->passwordVerify($credentials[1])
	);
}

protected function getCredentials()
{
	$userName = $_SERVER['PHP_AUTH_USER'];
	$password = $_SERVER['PHP_AUTH_PW'];
	if ($userName and $password) return array($userName, $password);
	return false;
}

protected function reload()
{
	header("Location: " . $_SERVER['PHP_SELF']);
	die();	
}

/**
 * Show http-login dialog, or verify client credentials.
**/
function loginHttp()
{
	$cred = $this->getCredentials();

	if ($cred) {
		if ($this->isLogged()) {
			if (!$this->verifyCredentials($cred)) die('Authentication failed.');
		}
		else {
			$ok = $this->login($cred[0], $cred[1]);
			if (!$ok) {
				$this->authPrompt();
				$this->reload();
			}
		}

	}
	else {
		$this->authPrompt();
	}

}

// function login($userName, $password, array $options = array()/*http*/)
// {
// }

function logout()
{
	die($this->app->text('Please close your internet browser for logout.'));
}

}

?>