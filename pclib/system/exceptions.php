<?php 

namespace pclib;

/**
 * Base class for all pclib exceptions, allowing message with placeholders suitable for translation.
 * Example: $e = new Exception("File '%s' not found.", [$fileName]);
 */ 
class Exception extends \Exception
{
	public function __construct($message, $args = null, $code = 0, \Exception $previous = null)
	{
		if (is_array($args)) {
			$message = vsprintf($message, $args);
		}
		parent::__construct($message, $code, $previous);
	}
}

class SqlQueryException extends Exception {}
class RuntimeException extends Exception {}
class ApiException extends Exception {}
class DatabaseException extends Exception {}
class AuthException extends Exception {}
class NoValueException extends Exception {}
class IOException extends Exception {}
class MemberAccessException extends Exception {}
class FileNotFoundException extends IOException {}
class NoDatabaseException extends NoValueException {
	public function __construct($message = 'Database connection required.', 
		$code = 0, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}
class NotImplementedException extends Exception {
	public function __construct($message = 'Feature is not implemented.', 
		$code = 0, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}

//Application service
interface IService {}

?>