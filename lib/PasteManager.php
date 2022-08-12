<?php

namespace n0nag0n\paste;

use Exception;
use Parsedown;
use Snipworks\Smtp\Email;

class PasteManager {

	/** @var string */
	protected $savedir;

	/** @var array<string, mixed> */
	protected $config;

	/**
	 * Construct
	 */
	public function __construct() {
		require_once('Email.php');
		require_once('Parsedown.php');
		$this->savedir = __DIR__ . '/../data/';
		$this->config = require(__DIR__ . '/../.config.php');
	}

	/**
	 * Saves the given content under a new UID
	 *
	 * @param string $content
	 * @param string $email
	 * @return bool|string UID or false on error
	 */
	public function savePaste(string $content, string $name, string $email) {
		$uid = $this->createUniqueHash();
		do {
			$path = $this->getDataFilePath($uid, 'paste');
		} while (file_exists($path));

		@mkdir(dirname($path), 0777, true);
		if(file_put_contents($path, json_encode([ 'uid' => $uid, 'content' => $content, 'name' => $name, 'email' => $email, 'time' => time(), 'comments' => [] ]))) {
			return json_encode([ 'uid' => $uid ]);
		}
		return false;
	}

	/**
	 * Loads a paste file
	 *
	 * @param $uid
	 * @return bool|string the paste's content, false on error
	 */
	public function loadPaste($uid) {
		$path = $this->getDataFilePath($uid, 'paste');
		if(!file_exists($path)) return false;
		return file_get_contents($path);
	}

	/**
	 * Stores a comment
	 *
	 * @param string $uid
	 * @param int $line
	 * @param string $comment
	 * @param string $user_name
	 * @param string $user_email
	 * @return bool|array
	 */
	public function saveComment(string $uid, int $line, string $comment, string $user_name, string $user_email) {
		$paste_path = $this->getDataFilePath($uid, 'paste');
		if(!file_exists($paste_path)) {
			return false;
		}

		$paste = json_decode(file_get_contents($paste_path));

		if($this->config['enable_smtp'] === true && $this->checkValidEmail($paste->email) === true) {
			$this->sendCommentReplyEmail($paste, $comment, $user_name, $user_email, $line);
		}

		$data = compact('uid', 'line', 'comment', 'user_name', 'user_email');
		$data['time'] = time();
		$data['color'] = substr(md5($user_name),0,6);
		if(!isset($paste->comments)) {
			$paste->comments = [];
		}
		$paste->comments[] = $data;
		if(file_put_contents($paste_path, json_encode($paste))) {
			$data['comment'] = $this->convertMarkdownToInlineHtml($data['comment']);
			return json_encode($data);
		}
		return false;
	}

	/**
	 * Checks for a valid email
	 *
	 * @param string|null $email the email address in question
	 * @return boolean
	 */
	protected function checkValidEmail(?string $email): bool {
		return (bool) preg_match("/^[^\s@]+@[^\s@]+\.[^\s@]+$/i", $email);
	}

	/**
	 * Return all comments
	 *
	 * @param string $uid
	 * @return array|bool
	 */
	public function loadComments(string $uid) {
		$paste_path = $this->getDataFilePath($uid, 'paste');
		if(!file_exists($paste_path)) return false;

		$paste = json_decode(file_get_contents($paste_path));

		foreach($paste->comments as &$comment) {
			$comment->comment = $this->convertMarkdownToInlineHtml($comment->comment);
		}
		return json_encode($paste->comments);
	}

	/**
	 * Get the full path to the file for the given UID and extension
	 *
	 * @param string $uid
	 * @param string $type
	 * @return string
	 */
	public function getDataFilePath(string $uid, string $type)
	{
		$prefix = substr($uid, 0, 1);
		return $this->savedir . $prefix . '/' . $uid . '.' . $type;
	}

	/**
	 * Creates a unique name
	 *
	 * @link http://stackoverflow.com/a/3537633/172068
	 * @param int $len
	 * @return string
	 */
	public function createUniqueHash($len = 8)
	{

		$hex = md5("someething salty!!!" . uniqid("", true));

		$pack = pack('H*', $hex);
		$tmp = base64_encode($pack);

		$uid = preg_replace("#(*UTF8)[^A-Za-z0-9]#", "", $tmp);

		$len = max(4, min(128, $len));

		while (strlen($uid) < $len) {
			$uid .= $this->createUniqueHash(22);
		}

		return substr($uid, 0, $len);
	}

	/**
	 * Sends an email to those who comment on your pastes
	 *
	 * @param object  $paste              the paste object from the stored file
	 * @param string  $comment            comment
	 * @param string  $comment_user_name  username
	 * @param string  $comment_user_email email
	 * @param integer $line               line_number
	 * @return boolean
	 */
	protected function sendCommentReplyEmail($paste, string $comment, string $comment_user_name, string $comment_user_email, int $line): bool {
		if($this->config['enable_smtp'] !== true) {
			throw new Exception('You need to enable_smtp to be true with proper configs to send emails');
		}

		$exploded_content = explode("\n", $paste->content);

		$before_content = $this->generateBeforeCommentInsight($exploded_content, $line);
		$after_content = $this->generateAfterCommentInsight($exploded_content, $line);
		$prepared_comment = "<p>Comment: ".$this->convertMarkdownToInlineHtml($comment).'</p>';
		$Smtp = new Email($this->config['smtp']['host'], $this->config['smtp']['port']);
		$Smtp->setLogin($this->config['smtp']['username'], $this->config['smtp']['password']);
		$Smtp->addTo($paste->email);
		$Smtp->setFrom($this->config['smtp']['from_email'], $this->config['smtp']['from_name']);
		$Smtp->addReplyTo($comment_user_email);
		$Smtp->setSubject('You have a new paste comment!');
		$Smtp->setHtmlMessage(<<<EOT
<p>Hi there,</p>
<p>{$comment_user_name} wants you to talk smack about their code. Here is what they said:</p>
<pre><code>{$before_content}</code></pre>
{$prepared_comment}
<pre><code>{$after_content}</code></pre>
<p>You can view this comment directly on the page by clicking <a href="{$this->config['app_base_url']}#{$paste->uid}">here</a>.</p>
<p>Hope you have a nice day!</p>
<p>Commie Bot</p>
EOT);
		$send_result = $Smtp->send();
		return (bool) $send_result;
	}

	public function generateBeforeCommentInsight(array $array_of_lines, int $line): string {
		if($line - 5 < 0) {
			$cutting_line = 0;
			$ending_line = $line;
		} else {
			$cutting_line = $line - 5;
			$ending_line = 6;
		}
		$before_content = array_slice($array_of_lines, $cutting_line, $ending_line);
		for($current_line = $cutting_line + 1, $i = 0; $i < count($before_content) ; ++$i, ++$current_line) {
			$before_content[$i] = str_pad($current_line.'.', 5, ' ', STR_PAD_LEFT).$before_content[$i];
		}
		return join("\n", $before_content);
	}

	public function generateAfterCommentInsight(array $array_of_lines, int $line): string {
		$line_count = count($array_of_lines);
		if($line + 5 > $line_count) {
			$cutting_line = $line_count - ($line_count - $line);
			$ending_line = $line_count;
		} else {
			$cutting_line = $line + 1;
			$ending_line = 5;
		}
		$after_content = array_slice($array_of_lines, $cutting_line, $ending_line);
		for($current_line = $cutting_line + 1, $i = 0; $i < count($after_content) ; ++$i, ++$current_line) {
			$after_content[$i] = str_pad($current_line.'.', 5, ' ', STR_PAD_LEFT).$after_content[$i];
		}
		return join("\n", $after_content);
	}

	public function convertMarkdownToInlineHtml(string $text) {
		$Parsedown = new Parsedown;
		$Parsedown->setBreaksEnabled(true)->setMarkupEscaped(false)->setUrlsLinked(true);
		return $Parsedown->line($text);
	}
}