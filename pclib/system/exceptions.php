<?php 

/**
 * Base class for all pclib exceptions, allowing message with placeholders suitable for translation.
 * Example: $e = new BaseException("File '%s' not found.", $fileName);
 */ 
class BaseException extends Exception
{
	public function __construct($message, $args = null, $code = 0, Exception $previous = null)
	{
		if (is_array($args)) {
			$message = vsprintf($message, $args);
		}
		parent::__construct($message, $code, $previous);
	}
}

class NotImplementedException extends BaseException {}
class DatabaseException extends BaseException {}
class AuthException extends BaseException {}
class NoValueException extends BaseException {}
class IOException extends BaseException {}
class MemberAccessException extends BaseException {}
class FileNotFoundException extends IOException {}
class NoDatabaseException extends NoValueException {
	public function __construct($message = 'Database connection required.', 
		$code = 0, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}

?>