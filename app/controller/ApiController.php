<?php
declare(strict_types=1);

namespace app\controller;

use flight\Engine;
use app\logic\PasteLogic;

class ApiController {

	protected PasteLogic $PasteLogic;

	protected Engine $app;

	protected array $api_result;

	/**
	 * Construct
	 *
	 * @param Engine $app class
	 */
	public function __construct(Engine $app, array $config) {
		$this->app = $app;
		$this->PasteLogic = new PasteLogic($app, $config);
	}

	/**
	 * Saves a paste
	 *
	 * @return void
	 */
	public function savePaste() {

		$required_fields = [ 'content', 'name', 'email' ];
		$post = $this->app->request()->data;
		foreach($required_fields as $field) {
			if(empty($post[$field])) {
				$this->api_result = [ 'error' => $field.' is required' ];
				$this->app->response()->status(400);
				return;
			}
		}

		$this->app->json($this->PasteLogic->savePaste($post['content'], $post['name'], $post['email'], ($post['language'] ?? '')));
	}

	/**
	 * Saves a comment
	 *
	 * @param string $uid uid of the paste
	 * @param int $line_number line number
	 * @return void
	 */
	public function saveComment(string $uid, int $line_number) {
		
		$required_fields = [ 'comment', 'name', 'email' ];
		$post = $this->app->request()->data;
		foreach($required_fields as $field) {
			if(empty($post[$field])) {
				$this->api_result = [ 'error' => $field.' is required' ];
				$this->app->response()->status(400);
				return;
			}
		}

		$this->app->json($this->PasteLogic->saveComment($uid, $line_number, $post['comment'], $post['name'], $post['email']));
	}

}