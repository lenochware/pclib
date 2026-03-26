<?php 

namespace pclib;
use pclib;

class ApiController extends Controller
{
	protected $token;

	public $publicMethods = [];
	public $publicApi = false;

	function __construct($app)
	{
		parent::__construct($app);
		$db = $app->getService('db');
		$this->token = new system\AuthToken;
	}

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

		$ret = call_user_func([$this, $methodName]);

		if (is_array($ret)) {
			$this->outputJson($ret);
		}
		else die($ret);
	}

	protected function getMethod()
	{
		$pathArray = explode('/', $_GET['r'] ?? '');
		array_shift($pathArray); //hack: throw away controller name
		if (!$pathArray) $pathArray = ['index'];

		$path = array_reduce($pathArray, function($ret, $item) { return $ret.ucfirst($item); });
		$name = strtolower($_SERVER['REQUEST_METHOD']).$path.'Action';

		return method_exists($this, $name)? $name : '';
	}

	protected function getToken()
	{
		//if (isset($_GET['apiKey'])) return $_GET['apiKey'];

		$headers = getallheaders();
		if (!isset($headers['Authorization'])) return '';

    if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        return $matches[1];
    }

    return '';
	}

	protected function getRequestJson()
	{
		$rawBody = file_get_contents('php://input');
		return json_decode($rawBody, true);
	}

	protected function getRequestValue($name, $default = null)
	{
		$data = $_POST? $_POST : $_GET;
		return $data[$name] ?? $default;
	}

	public function error($message, $httpStatus = 500)
	{
		if (function_exists('http_response_code')) {
			http_response_code($httpStatus);
		}

		$data = [
			'response_type' => 'error',
			'status' => $httpStatus,
			'message' => $message,
		];

		$this->outputJson($data);
	}
}

?>