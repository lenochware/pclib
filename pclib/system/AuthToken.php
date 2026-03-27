<?php

/**
 * @file
 * PHP authentification token.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link https://pclib.brambor.net/
 * @license MIT (https://opensource.org/licenses/MIT)
 */

namespace pclib\system;

/**
 * Create, remove or validate token, which can be used for API autentification.
 * Tokens are stored in database table PCLIB_TOKENS.
 */
class AuthToken extends BaseObject
{
	/** var Db */
	public $db;

	public $table = 'PCLIB_TOKENS';
	protected $expireSeconds;

	/**
	 * Create AuthToken object.
	 * @param int $expireSeconds Token time to live
	 */
	function __construct($expireSeconds = 1800)
	{
		parent::__construct();
		$this->expireSeconds = $expireSeconds;
	}

	/**
	 * Validate token.
	 * @param string $token
	 * @param bool $refresh If true, token time to live will be updated
	 * @param bool $isValid
	 */
	function validate($token, $refresh = true)
	{
		$this->service('db');

		$data = $this->db->select($this->table, ['TOKEN' => hash('sha256', $token)]);
		if (!$data or ($data['EXPIRE'] ?? '') < date('Y-m-d H:i:s')) return false;

		if ($refresh) {
  		$this->db->update($this->table, ['EXPIRE' => date('Y-m-d H:i:s', time() + $this->expireSeconds)], ['TOKEN' => $token]);
		}

		return true;
	}

	/**
	 * Remove token.
	 * @param string $token
	 */
	function remove($token)
	{
		$this->service('db');

		$this->db->delete($this->table, ['TOKEN' => hash('sha256', $token)]);
	}

	/**
	 * Create token for user $userId.
	 * @param int $userId
	 * @return string $token
	 */
	function create($userId)
	{
		$this->service('db');

		$this->clear();
		$token = bin2hex(random_bytes(32));

		$this->db->insert($this->table, [
			'TOKEN' => hash('sha256', $token),
			'USER_ID' => $userId,
			'EXPIRE' => date('Y-m-d H:i:s', time() + $this->expireSeconds)
		]);

		return $token;
	}

	/**
	 * Clear expired session from database.
	 */
	function clear()
	{
		$this->service('db');

		$this->db->delete($this->table, "EXPIRE<'{0}'", date('Y-m-d H:i:s'));		
	}
}

 ?>