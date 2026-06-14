<?php 
declare(strict_types=1);

namespace app\middleware;

use flight\Engine;

class ApiMiddleware {

	protected Engine $app;
	protected array $config;

	public function __construct(Engine $app, array $config)
	{
		$this->app = $app;
		$this->config = $config;
	}

	/**
	 * @return array<string, string>|null
	 */
	public function getAuthorizationError(?string $authHeader): ?array {
		if(empty($this->config['api_key'])) {
			return [ 'error' => 'api_key needs to be set in the config file.' ];
		}

		$expected = 'Bearer '.$this->config['api_key'];
		if(!hash_equals($expected, $authHeader ?? '')) {
			return [ 'error' => 'invalid api_key supplied in Authorization header. Make sure the request header is "Authorization: Bearer apikey"' ];
		}

		return null;
	}

	public function before() {
		$auth_header = $this->app->request()->getHeader('X-Authorization');
		if(empty($auth_header)) {
			$auth_header = $this->app->request()->getHeader('Authorization');
		}

		$error = $this->getAuthorizationError($auth_header);
		if($error !== null) {
			header('Content-Type: application/json');
			die(json_encode($error, JSON_UNESCAPED_SLASHES));
		}
	}
}