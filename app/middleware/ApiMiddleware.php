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

	public function before() {
		if(empty($this->config['api_key'])) {
			$api_result = [ 'error' => 'api_key needs to be set in the config file.' ];
			header('Content-Type: application/json');
			die(json_encode($api_result, JSON_UNESCAPED_SLASHES));
		}

		$auth_header = $this->app->request()->getHeader('X-Authorization');
		if(empty($auth_header)) {
			$auth_header = $this->app->request()->getHeader('Authorization');
		}
		if($auth_header !== 'Bearer '.$this->config['api_key']) {
			$api_result = [ 'error' => 'invalid api_key supplied in Authorization header. Make sure the request header is "Authorization: Bearer apikey"' ];
			header('Content-Type: application/json');
			die(json_encode($api_result, JSON_UNESCAPED_SLASHES));
		}
	}
}