<?php
declare(strict_types=1);

namespace n0nag0n\paste;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Exception;
use Highlight\Highlighter;
use Northys\CSSInliner\CSSInliner;
use Parsedown;
use Snipworks\Smtp\Email;

use function HighlightUtilities\splitCodeIntoArray;

class PasteManager {

	/** @var string */
	protected $savedir;

	/** @var array<string, mixed> */
	protected $config;

	/**
	 * Construct
	 */
	public function __construct() {
		$this->savedir = __DIR__ . '/../data/';
		$this->config = require(__DIR__ . '/../.config.php');
	}

	/**
	 * Saves the given content under a new UID
	 *
	 * @param string $content
	 * @param string $name
	 * @param string $email
	 * @param string $language
	 * @return bool|string UID or false on error
	 */
	public function savePaste(string $content, string $name, string $email, string $language = '') {
		$uid = $this->createUniqueHash();
		do {
			$path = $this->getDataFilePath($uid, 'paste');
		} while (file_exists($path));

		@mkdir(dirname($path), 0777, true);
		if($this->saveFileContentsFromObject($path, [ 
			'uid' => $uid, 
			'content' => $content, 
			'name' => $name, 
			'email' => $email, 
			'language' => $language,
			'time' => time(), 
			'comments' => [] 
		])) {
			return json_encode([ 'uid' => $uid ], JSON_UNESCAPED_SLASHES);
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
		if(!file_exists($path)) {
			return false;
		}
		return json_encode($this->getFileContentsAsObject($path, true), JSON_UNESCAPED_SLASHES);
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

		$paste = $this->getFileContentsAsObject($paste_path);

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
		if($this->saveFileContentsFromObject($paste_path, $paste)) {
			$data['comment'] = $this->convertMarkdownToInlineHtml($data['comment']);
			return json_encode($data, JSON_UNESCAPED_SLASHES);
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
	 * Gets file contents as object
	 *
	 * @param string $file_path [description]
	 * @return stdClass
	 */
	protected function getFileContentsAsObject(string $file_path, bool $add_highlight = false) {
		$paste = json_decode(Crypto::decrypt(file_get_contents($file_path), Key::loadFromAsciiSafeString($this->config['encryption_key'])));
		if($add_highlight === true) {
			// $Highlighter = new Highlighter();
			// if(empty($paste->language)) {
			// 	$Highlighter->setAutodetectLanguages([
			// 		'php', 'sql', 'markdown', 'diff', 'javascript', 'json', 'bash', 'css', 'go', 'xml', 'ini', 'apache',  'plaintext'
			// 	]);
			// 	$highlighted = $Highlighter->highlightAuto($paste->content);
			// } else {
			// 	$highlighted = $Highlighter->highlight($paste->language, $paste->content);
			// }

			// $lines = splitCodeIntoArray($highlighted->value);
			// $html = '<div class="hljs"><ol class="linenums">';
			// foreach($lines as $line_number => $line) {
			// 	$html .= '<li id="L'.$line_number.'" class="L0"><pre><code class="'.$highlighted->language.'">'.$line.'</code></pre></li>';
			// }
			// $html .= '</ol></div>';
			// $paste->highlighted_content = $html;

			$highlighted_result = $this->highlightText($paste);
			$paste->highlighted_content = $highlighted_result['highlighted_content'];
			$paste->highlighted_language = $highlighted_result['highlighted_language'];

			foreach($paste->comments as &$comment) {
				$comment->comment = $this->convertMarkdownToInlineHtml($comment->comment);
			}

			if($paste->highlighted_language === 'markdown') {
				$paste->preview_html = $this->convertMarkdownToInlineHtml($paste->content);
			}
		}
		return $paste;
	}

	/**
	 * Saves the file contents
	 *
	 * @param string $file_path file_path
	 * @param mixed  $contents  contents
	 * @return mixed see file_put_contents()
	 */
	protected function saveFileContentsFromObject(string $file_path, $contents) {
		return file_put_contents($file_path, Crypto::encrypt(json_encode($contents, JSON_UNESCAPED_SLASHES), Key::loadFromAsciiSafeString($this->config['encryption_key'])));
	}

	/**
	 * Return all comments
	 *
	 * @param string $uid
	 * @return array|bool
	 */
	// public function loadComments(string $uid) {
	// 	$paste_path = $this->getDataFilePath($uid, 'paste');
	// 	if(!file_exists($paste_path)) {
	// 		return false;
	// 	}

	// 	$paste = $this->getFileContentsAsObject($paste_path);

		
	// 	return json_encode($paste->comments);
	// }

	/**
	 * Get the full path to the file for the given UID and extension
	 *
	 * @param string $uid
	 * @param string $type
	 * @return string
	 */
	public function getDataFilePath(string $uid, string $type) {
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
	public function createUniqueHash($len = 8) {
		$hex  = md5("someething salty!!!" . uniqid("", true));
		$pack = pack('H*', $hex);
		$tmp  = base64_encode($pack);
		$uid  = preg_replace("#(*UTF8)[^A-Za-z0-9]#", "", $tmp);
		$len  = max(4, min(128, $len));

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

		$before_content = $this->generateBeforeCommentInsight($paste, $line);
		$after_content = $this->generateAfterCommentInsight($paste, $line);
		$comment_color = substr(md5($comment_user_name),0,6);
		$prepared_comment = '<div class="email-comment" style="border-color: #'.$comment_color.'">Comment: '.$this->convertMarkdownToInlineHtml($comment).'</div>';
		$Smtp = new Email($this->config['smtp']['host'], $this->config['smtp']['port']);
		$Smtp->setLogin($this->config['smtp']['username'], $this->config['smtp']['password']);
		$Smtp->addTo($paste->email);
		$Smtp->setFrom($this->config['smtp']['from_email'], $this->config['smtp']['from_name']);
		$Smtp->addReplyTo($comment_user_email);
		$Smtp->setProtocol(Email::TLS);
		$Smtp->setSubject('You have a new paste comment!');
		$html = <<<EOT
<html>
	<head>
		<title>You have a new paste comment!</title>
		<style>
			#email_body {
				padding: 15px;
			}
		</style>
	</head>
	<body>
		<div id="email_body">
			<p>Hi there,</p>
			<p>{$comment_user_name} wants you to talk smack about their code. Here is what they said:</p>
			{$before_content}
			{$prepared_comment}
			{$after_content}
			<p>You can view this comment directly on the page by clicking <a href="{$this->config['app_base_url']}#{$paste->uid}">here</a>.</p>
			<p>Hope you have a nice day!</p>
			<p>Commie Bot</p>
		</div>
	</body>
</html>
EOT;

		// Disable some crappy errors
		$internalErrors = libxml_use_internal_errors(true);
		$css_inliner = new CSSInliner;
		$css_inliner->addCSS(__DIR__.'/../public/lib/style.css');
		$css_inliner->addCSS(__DIR__.'/../public/lib/sunburst.css');
		$html = $css_inliner->render($html);
		// Re-enable crappy errors
		libxml_use_internal_errors($internalErrors);

		$Smtp->setHtmlMessage($html);
		$send_result = $Smtp->send();
		return (bool) $send_result;
	}

	/**
	 * Generates the section of code before the comment
	 *
	 * @param array   $array_of_lines array_of_lines
	 * @param integer $line           line
	 * @return string
	 */
	public function generateBeforeCommentInsight($paste, int $line): string {
		if($line - 5 < 0) {
			$cutting_line = 0;
			$ending_line = $line;
		} else {
			$cutting_line = $line - 4;
			$ending_line = 5;
		}
		$starting_number = $cutting_line + 1;
		return $this->highlightText($paste, $starting_number, $cutting_line, $ending_line)['highlighted_content'];
		// $before_content = array_slice($array_of_lines, $cutting_line, $ending_line);
		// for($current_line = $cutting_line + 1, $i = 0; $i < count($before_content) ; ++$i, ++$current_line) {
		// 	$before_content[$i] = str_pad($current_line.'.', 5, ' ', STR_PAD_LEFT).$before_content[$i];
		// }
		// return join("\n", $before_content);
	}

	/**
	 * Generates the section of code after the comment
	 *
	 * @param \stdClass $paste paste
	 * @param integer   $line  line
	 * @return string
	 */
	public function generateAfterCommentInsight($paste, int $line): string {
		$line_count = count(explode("\n", $paste->content));
		if($line + 5 > $line_count) {
			$cutting_line = $line_count - ($line_count - $line);
			$ending_line = $line_count;
		} else {
			$cutting_line = $line + 1;
			$ending_line = 5;
		}
		$starting_number = $cutting_line + 1;
		return $this->highlightText($paste, $starting_number, $cutting_line, $ending_line)['highlighted_content'];
		// $after_content = array_slice($array_of_lines, $cutting_line, $ending_line);
		// for($current_line = $cutting_line + 1, $i = 0; $i < count($after_content) ; ++$i, ++$current_line) {
		// 	$after_content[$i] = str_pad($current_line.'.', 5, ' ', STR_PAD_LEFT).$after_content[$i];
		// }

		// return join("\n", $after_content);
	}

	/**
	 * Highlights the text
	 *
	 * @param \stdClass $paste paste
	 * @return array [ 'highlighted_content', 'highlighted_language' ]
	 */
	protected function highlightText($paste, int $line_starting_number = 1, ?int $slice_key_offset = null, ?int $slice_length = null): array {
		$Highlighter = new Highlighter();
		if(empty($paste->language)) {
			$Highlighter->setAutodetectLanguages([
				'php', 'sql', 'markdown', 'diff', 'javascript', 'json', 'bash', 'css', 'go', 'xml', 'ini', 'apache',  'plaintext'
			]);
			$highlighted = $Highlighter->highlightAuto($paste->content);
		} else {
			$highlighted = $Highlighter->highlight($paste->language, $paste->content);
		}
		$lines = splitCodeIntoArray($highlighted->value);

		if($slice_key_offset !== null && $slice_length !== null) {
			$lines = array_slice($lines, $slice_key_offset, $slice_length);
		}

		$html = '<div class="hljs"><ol class="linenums" start="'.$line_starting_number.'">';
		foreach($lines as $line_number => $line) {
			$html .= '<li id="L'.$line_number.'" class="L0"><pre><code class="'.$highlighted->language.'">'.$line.'</code></pre></li>';
		}
		$html .= '</ol></div>';
		return [ 
			'highlighted_content' => $html, 
			'highlighted_language' => $highlighted->language
		];
	}

	/**
	 * Does what it says
	 *
	 * @param string $text markdown
	 * @return string
	 */
	public function convertMarkdownToInlineHtml(string $text): string {
		$parser = new \cebe\markdown\GithubMarkdown();
		return $parser->parse($text);
	}
}