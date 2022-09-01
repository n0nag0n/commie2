<?php
declare(strict_types=1);

namespace controller;

use Base;
use DateTime;
use DateTimeZone;
use logic\Paste_Logic;

class Api_Controller {

	protected Paste_Logic $Paste_Logic;

	protected array $api_result;

	/**
	 * Construct
	 *
	 * @param Base $f3 f3 class
	 */
	public function __construct(Base $f3) {
		$this->Paste_Logic = new Paste_Logic($f3);	
	}

	/**
	 * Runs before a class is executed
	 *
	 * @param Base  $f3   f3 class
	 * @return void
	 */
	public function beforeRoute(Base $f3) {
		if(empty($f3->config['api_key'])) {
			$this->api_result = [ 'error' => 'api_key needs to be set in the config file.' ];
			$this->afterRoute($f3);
			exit;
		}

		$auth_header = $f3->HEADERS['Authorization'];
		if($auth_header !== 'Bearer '.$f3->config['api_key']) {
			$this->api_result = [ 'error' => 'invalid api_key supplied in Authorization header. Make sure the request header is "Authorization: Bearer apikey"' ];
			$this->afterRoute($f3);
			exit;
		}
	}

	/**
	 * Runs after a route is found and executed
	 *
	 * @param Base  $f3   f3 class
	 * @return void
	 */
	public function afterRoute(Base $f3) {
		header('Content-Type: application/json');
		echo json_encode($this->api_result, JSON_UNESCAPED_SLASHES);
	}

	/**
	 * Saves a paste
	 *
	 * @param Base  $f3   f3 class
	 * @param array $args arg params
	 * @return void
	 */
	public function savePaste(Base $f3, array $args) {

		$required_fields = [ 'content', 'name', 'email' ];
		foreach($required_fields as $field) {
			if(empty($f3->POST[$field])) {
				$this->api_result = [ 'error' => $field.' is required' ];
				$f3->status(400);
				return;
			}
		}

		$post = $f3->clean($f3->POST);
		$this->api_result = $this->Paste_Logic->savePaste($f3->POST['content'], $post['name'], $post['email'], ($post['language'] ?? ''));
	}

	/**
	 * Saves a comment
	 *
	 * @param Base  $f3   f3 class
	 * @param array $args arg params
	 * @return void
	 */
	public function saveComment(Base $f3, array $args) {
		
		$required_fields = [ 'comment', 'name', 'email' ];
		foreach($required_fields as $field) {
			if(empty($f3->POST[$field])) {
				$this->api_result = [ 'error' => $field.' is required' ];
				$f3->status(400);
				return;
			}
		}

		$post = $f3->clean($f3->POST);
		$this->api_result = $this->Paste_Logic->saveComment($args['uid'], (int) $args['line_number'], $post['comment'], $post['name'], $post['email']);
	}

}