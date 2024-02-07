<?php
declare(strict_types=1);

namespace controller;

use Base;
use DateTime;
use DateTimeZone;
use logic\Paste_Logic;
use Throwable;
use Web;

class Paste_Controller {

	protected Paste_Logic $Paste_Logic;

	/**
	 * Construct
	 *
	 * @param Base $f3 f3 class
	 */
	public function __construct(Base $f3) {
		$this->Paste_Logic = new Paste_Logic($f3);	
	}

	/**
	 * Default index page, or loads a paste
	 *
	 * @param Base  $f3   f3 class
	 * @param array $args arg params
	 * @return void
	 */
	public function load(Base $f3, array $args) {

		$template_vars = [
			'name' => ($_COOKIE['name'] ?? ''),
			'email' => ($_COOKIE['email'] ?? ''),
			'page_title' => 'Home - Create a new paste',
			'base_url' => $f3->BASE,
			'highlighted_content' => '',
			'content' => '',
			'preview_html' => '',
			'author' => '',
			'time' => 0,
			'page_title' => ''
		];

		if(!empty($args['uid'])) {
			$time_zone = $this->Paste_Logic->getTimeZone();
			
			$Paste = $this->Paste_Logic->getFileContentsAsObject($args['uid'], true);
			$template_vars = [
				'highlighted_content' => $Paste->highlighted_content,
				'content' => $Paste->content,
				'preview_html' => $Paste->preview_html,
				'author' => $Paste->name,
				'time' => (new DateTime('@'.$Paste->time, new DateTimeZone($time_zone))),
				'page_title' => 'Paste by '.$Paste->name.' ('.$args['uid'].')'
			] + $template_vars;
		}

		echo $f3->Latte->render(__DIR__.'/../views/index.latte', $template_vars);
	}

	/**
	 * Saves a paste from the UI
	 *
	 * @param Base  $f3   f3 class
	 * @param array $args arg params
	 * @return void
	 */
	public function savePaste(Base $f3, array $args) {
		$post = $f3->clean($f3->POST);
		$save_result = $this->Paste_Logic->savePaste($f3->POST['content'], $post['name'], $post['email'], ($post['language'] ?? ''));
		$Paste = $this->Paste_Logic->getFileContentsAsObject($save_result['uid'], true);

		$time_zone = $this->Paste_Logic->getTimeZone();

		// sets the url
		header('HX-Push: '.$Paste->uid);
		$template_vars = [
			'highlighted_content' => $Paste->highlighted_content,
			'content' => $Paste->content,
			'preview_html' => $Paste->preview_html,
			'author' => $Paste->name,
			'time' => (new DateTime('@'.$Paste->time, new DateTimeZone($time_zone))),
		];
		echo $f3->Latte->render(__DIR__.'/../views/paste_content.latte', $template_vars);
	}

	/**
	 * Gets the comment form (self explanatory?)
	 *
	 * @param Base  $f3   f3 class
	 * @param array $args arg params
	 * @return void
	 */
	public function getCommentForm(Base $f3, array $args) {
		echo $f3->Latte->render(__DIR__.'/../views/comment_form.latte', $args + $_COOKIE + [ 'base_url' => $f3->BASE ]);
	}

	/**
	 * Saves a comment from the UI
	 *
	 * @param Base  $f3   f3 class
	 * @param array $args arg params
	 * @return void
	 */
	public function saveComment(Base $f3, array $args) {

		$post = $f3->clean($f3->POST);
		$save_result = $this->Paste_Logic->saveComment($args['uid'], (int) $args['line_number'], $f3->POST['comment'], $post['name'], ($post['email'] ?? ''));

		$time_zone = $this->Paste_Logic->getTimeZone();

		$template_vars = [
			'comment' => $save_result['comment'],
			'color' => $save_result['color'],
			'time' => (new DateTime('@'.$save_result['time'], new DateTimeZone($time_zone))),
			'name' => $save_result['name']
		];
		echo '<div hx-swap-oob="afterbegin:#L'.$args['line_number'].' .comments">'.$f3->Latte->render(__DIR__.'/../views/comment.latte', $template_vars).'</div>';
	}

	/**
	 * Merges and gzips and all the js files.
	 *
	 * @param Base  $f3   f3 class
	 * @param array $args arg params
	 * @return void
	 */
	public function js(Base $f3, array $args) {
		$contents = $f3->read(__DIR__.'/../../public/lib/htmx-1.8.0.min.js')."\n";
		$contents .= $f3->read(__DIR__.'/../../public/lib/hyperscript-0.9.7.min.js')."\n";

		$f3->expire(21600);
		if(strpos($f3->HEADERS['Accept-Encoding'], 'gzip') !== false) {
			header('Content-Encoding: gzip');
			$contents = gzencode($contents, 9);
		}
		header('Content-Type: text/javascript');
		echo $contents;
	}

	/**
	 * Merges, minifies, and gzips all the css files
	 *
	 * @param Base  $f3   f3 class
	 * @param array $args arg params
	 * @return void
	 */
	public function css(Base $f3, array $args) {
		$contents = Web::instance()->minify([ 'style.css', 'sunburst.css' ], null, false, realpath(__DIR__.'/../../public/lib/').'/');
		$f3->expire(21600);
		if(strpos($f3->HEADERS['Accept-Encoding'], 'gzip') !== false) {
			header('Content-Encoding: gzip');
			$contents = gzencode($contents, 9);
		}
		header('Content-Type: text/css');
		echo $contents;
	}

	/**
	 * Search from the command line for a specific paste
	 *
	 * @param Base $f3 f3 class
	 * @return void
	 */
	public function search(Base $f3) {
		$keyword = $f3->GET['keyword'];
		
		if(empty($keyword)) {
			die('You need to specify a --keyword');
		}
		$f3->DEBUG = 3;
		foreach(glob(__DIR__.'/../../data/**/**.paste') as $file_path) {
			$base_name = basename($file_path);
			$exploded = explode('.', $base_name);
			$uid = $exploded[0];
			try {
				$Paste = $this->Paste_Logic->getFileContentsAsObject($uid);
			} catch(Throwable $e) {
				echo "Error: ".$e->getMessage()."\n";
				continue;
			}
			if(stripos($Paste->content, $keyword) === false) {
				continue;
			}
			
			echo "Found in ".$base_name."\n";
			echo $Paste->content."\n\n";
		}
	}

}