<?php
declare(strict_types=1);

namespace app\controller;

use DateTime;
use DateTimeZone;
use flight\Engine;
use app\logic\PasteLogic;

class PasteController {

	protected PasteLogic $PasteLogic;

	protected Engine $app;

	/**
	 * Construct
	 *
	 * @param Engine $app app
	 */
	public function __construct(Engine $app, array $config) {
		$this->app = $app;
		$this->PasteLogic = new PasteLogic($app, $config);	
	}

	/**
	 * Default index page, or loads a paste
	 *
	 * @param string $uid uid of the paste
	 * @return void
	 */
	public function load(?string $uid = null) {

		$template_vars = [
			'name' => ($_COOKIE['name'] ?? ''),
			'email' => ($_COOKIE['email'] ?? ''),
			'page_title' => 'Home - Create a new paste',
			'base_url' => $this->app->get('flight.base_url'),
			'highlighted_content' => '',
			'content' => '',
			'preview_html' => '',
			'author' => '',
			'time' => 0,
			'page_title' => ''
		];

		if($uid) {
			$time_zone = $this->PasteLogic->getTimeZone();
			
			$Paste = $this->PasteLogic->getFileContentsAsObject($uid, true);
			$template_vars = [
				'highlighted_content' => $Paste->highlighted_content,
				'content' => $Paste->content,
				'preview_html' => $Paste->preview_html ?? '',
				'author' => $Paste->name,
				'time' => (new DateTime('@'.$Paste->time, new DateTimeZone($time_zone))),
				'page_title' => 'Paste by '.$Paste->name.' ('.$uid.')',
				'uid' =>  $uid,
			] + $template_vars;
		}

		echo $this->app->latte()->render(__DIR__.'/../views/index.latte', $template_vars);
	}

	/**
	 * Saves a paste from the UI
	 *
	 * @return void
	 */
	public function savePaste() {
		$post = $this->app->request()->data;
		$save_result = $this->PasteLogic->savePaste($post['content'], $post['name'], $post['email'], ($post['language'] ?? ''));
		$Paste = $this->PasteLogic->getFileContentsAsObject($save_result['uid'], true);

		$time_zone = $this->PasteLogic->getTimeZone();

		// sets the url
		header('HX-Push: '.$Paste->uid);
		$template_vars = [
			'highlighted_content' => $Paste->highlighted_content,
			'content' => $Paste->content,
			'preview_html' => $Paste->preview_html ?? '',
			'author' => $Paste->name,
			'time' => (new DateTime('@'.$Paste->time, new DateTimeZone($time_zone))),
			'uid' => $Paste->uid,
		];
		echo $this->app->latte()->render(__DIR__.'/../views/paste_content.latte', $template_vars);
	}

	/**
	 * Gets the comment form (self explanatory?)
	 *
	 * @param string $uid		uid of the paste
	 * @param int    $line_number line number of the comment
	 * @return void
	 */
	public function getCommentForm(string $uid, int $line_number) {
		echo $this->app->latte()->render(__DIR__.'/../views/comment_form.latte', [ 
				'uid' => $uid, 
				'line_number' => $line_number, 
				'base_url' => $this->app->get('flight.base_url'),
			] + $this->app->request()->cookies->getData() + [
				'name' => '',
				'email' => '' 
			]
		);
	}

	/**
	 * Saves a comment from the UI
	 *
	 * @param string $uid		uid of the paste
	 * @param int    $line_number line number of the comment
	 * @return void
	 */
	public function saveComment(string $uid, int $line_number) {

		$post = $this->app->request()->data;
		$save_result = $this->PasteLogic->saveComment($uid, $line_number, $post['comment'], $post['name'], ($post['email'] ?? ''));

		$time_zone = $this->PasteLogic->getTimeZone();

		$template_vars = [
			'comment' => $save_result['comment'],
			'color' => $save_result['color'],
			'time' => (new DateTime('@'.$save_result['time'], new DateTimeZone($time_zone))),
			'name' => $save_result['name']
		];
		echo '<div hx-swap-oob="afterbegin:#L'.$line_number.' .comments">'.$this->app->latte()->render(__DIR__.'/../views/comment.latte', $template_vars).'</div>';
	}

	/**
	 * Merges and gzips and all the js files.
	 *
	 * @return void
	 */
	public function js() {
		$contents = file_get_contents(__DIR__.'/../../public/lib/htmx-1.9.10.min.js')."\n";
		$contents .= file_get_contents(__DIR__.'/../../public/lib/hyperscript-0.9.7.min.js')."\n";

		$this->app->response()->cache(time() + 21600);
		if(strpos($this->app->request()->getHeader('Accept-Encoding'), 'gzip') !== false) {
			header('Content-Encoding: gzip');
			$contents = gzencode($contents, 9);
		}
		header('Content-Type: text/javascript');
		echo $contents;
	}

	/**
	 * Merges, minifies, and gzips all the css files
	 *
	 * @return void
	 */
	public function css() {
		$contents = file_get_contents(__DIR__.'/../../public/lib/style.css')."\n";
		$contents .= file_get_contents(__DIR__.'/../../public/lib/sunburst.css')."\n";
		$this->app->response()->cache(time() + 21600);
		if(strpos($this->app->request()->getHeader('Accept-Encoding'), 'gzip') !== false) {
			header('Content-Encoding: gzip');
			$contents = gzencode($contents, 9);
		}
		header('Content-Type: text/css');
		echo $contents;
	}

	/**
	 * 
	 * Flight doesn't have a CLI manager by default, so this is commented out
	 * 
	 * Search from the command line for a specific paste
	 *
	 * @return void
	 */
	// public function search() {
	// 	$keyword = $f3->GET['keyword'];
		
	// 	if(empty($keyword)) {
	// 		die('You need to specify a --keyword');
	// 	}
	// 	$f3->DEBUG = 3;
	// 	foreach(glob(__DIR__.'/../../data/**/**.paste') as $file_path) {
	// 		$base_name = basename($file_path);
	// 		$exploded = explode('.', $base_name);
	// 		$uid = $exploded[0];
	// 		try {
	// 			$Paste = $this->PasteLogic->getFileContentsAsObject($uid);
	// 		} catch(Throwable $e) {
	// 			echo "Error: ".$e->getMessage()."\n";
	// 			continue;
	// 		}
	// 		if(stripos($Paste->content, $keyword) === false) {
	// 			continue;
	// 		}
			
	// 		echo "Found in ".$base_name."\n";
	// 		echo $Paste->content."\n\n";
	// 	}
	// }

}