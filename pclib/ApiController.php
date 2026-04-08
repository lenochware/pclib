<?php 
/**
 * @file
 * REST API Controller.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link https://pclib.brambor.net/
 * @license MIT (https://opensource.org/licenses/MIT)
 */

namespace pclib;
use pclib;

/**
 * Controller for the application REST API with bearer authorisation, post/get methods, returning json.
 * Create your REST controllers as ancestors of ApiController.
 * @see App::run()
 * @see AuthToken
 */
class ApiController extends Controller
{
	/** var system\AuthToken */
	protected $token;

	/** List of public methods. Non-public methods without valid token will be rejected. */
	public $publicMethods = [];

	/** Set if whole controller is public - do not use athorisation. */
	public $publicApi = false;

	/** var Request */
	protected $request;

	function __construct($app)
	{
		parent::__construct($app);
		$db = $app->getService('db');
		$this->token = new system\AuthToken;
		$this->request = $app->request;
	}

	/**
	 * Call action method of the controller, feeding it with required parameters.
	 * @param Action $action called action.
	 */
	function run($action)
	{
		$methodName = $this->getMethod();

		if (!$methodName) {
			$this->error('Unknown api call', 404);
		}

		if (!$this->publicApi) {
			if (!in_array($methodName, $this->publicMethods) and !$this->token->validate($this->getToken())) {
				$this->error('Authentication error', 405);
			}
		}

		$args = $this->getArgs($methodName, $action->params);

		$ret = call_user_func_array([$this, $methodName], $args);

		if (is_array($ret)) {
			$this->outputJson($ret);
		}
		else die($ret);
	}

	function getMethod()
	{
		$pathArray = explode('/', $_GET['r'] ?? '');
		array_shift($pathArray); //hack: throw away controller name
		if (!$pathArray) $pathArray = ['index'];

		$path = array_reduce($pathArray, function($ret, $item) { return $ret.ucfirst($item); });
		$name = strtolower( $this->request->method).$path.'Action';
		$this->action = $name;

		return method_exists($this, $name)? $name : '';
	}

	/**
	 * Return authorisation token from request Authorization header.
	 * @return string $token
	 */
	function getToken()
	{
		$headers = $this->request->getHeaders();
		if (!isset($headers['Authorization'])) return '';

    if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        return $matches[1];
    }

    return '';
	}

	/**
	 * Exit with http status code and json message.
	 * @param string $message
	 * @param int $httpStatus
	 */
	function error($message, $httpStatus = 500, ...$args)
	{
		if (function_exists('http_response_code')) {
			http_response_code($httpStatus);
		}

		$data = [
			'response_type' => 'error',
			'status' => $httpStatus,
			'message' => $this->app->text($message, $args),
		];

		$this->outputJson($data);
	}
}

?>