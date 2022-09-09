<?php
declare(strict_types=1);

namespace logic;

use Base;
use DateTime;
use DateTimeZone;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Exception;
use Highlight\Highlighter;
use Snipworks\Smtp\Email;
use Throwable;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

use function HighlightUtilities\splitCodeIntoArray;

class Paste_Logic {

	/** @var string */
	protected $savedir;

	/** @var array<string, mixed> */
	protected $config;

	protected Base $f3;

	/**
	 * Construct
	 */
	public function __construct(Base $f3) {
		$this->f3 = $f3;
		$this->savedir = __DIR__ . '/../../data/';
		$this->config = $f3->config;
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
			$thirty_days = (time() + (60 * 60 * 24 * 30));
			$this->f3->set('COOKIE.name', $name, $thirty_days);
			$this->f3->set('COOKIE.email', $email, $thirty_days);
			return [ 'uid' => $uid ];
		}
		return false;
	}

	/**
	 * Stores a comment
	 *
	 * @param string $uid
	 * @param int $line
	 * @param string $comment
	 * @param string $name
	 * @param string $email
	 * @return bool|array
	 */
	public function saveComment(string $uid, int $line, string $comment, string $name, string $email) {
		$paste_path = $this->getDataFilePath($uid, 'paste');
		if(!file_exists($paste_path)) {
			return false;
		}
		
		$paste = $this->getFileContentsAsObject($uid);

		$comment = htmlspecialchars($comment);

		$data = compact('uid', 'line', 'comment', 'name', 'email');
		$data['time'] = time();
		$data['color'] = substr(md5($name),0,6);
		if(!isset($paste->comments)) {
			$paste->comments = [];
		}
		$paste->comments[] = (object) $data;
		if($this->saveFileContentsFromObject($paste_path, $paste)) {
			$data['comment'] = $this->convertMarkdownToInlineHtml($data['comment']);

			if($this->config['enable_smtp'] === true && $this->checkValidEmail($paste->email) === true) {
				$this->sendCommentReplyEmail($paste, $comment, $name, $email, $line);
			}

			$thirty_days = (time() + (60 * 60 * 24 * 30));
			$this->f3->set('COOKIE.name', $name, $thirty_days);
			$this->f3->set('COOKIE.email', $email, $thirty_days);

			return $data;
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
	 * @param string $uid uid of the paste
	 * @return stdClass
	 */
	public function getFileContentsAsObject(string $uid, bool $add_highlight = false) {
		$file_path = $this->getDataFilePath($uid, 'paste');
		if(file_exists($file_path) !== true) {
			throw new Exception('Unable to find paste');
		}
		$paste = json_decode(Crypto::decrypt(file_get_contents($file_path), Key::loadFromAsciiSafeString($this->config['encryption_key'])));
		if($add_highlight === true) {
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

		// gather all the people that have made comments
		$emails = [ $paste->email ];
		foreach($paste->comments as $comment) {
			$emails[] = $comment->email;
		}

		// get rid of duplicates
		$emails = array_unique($emails);

		// the commenter should not get an email...
		$array_key = array_search($comment_user_email, $emails, true);
		if(isset($emails[$array_key])) {
			unset($emails[$array_key]);
		}

		// technically it did it's job. It send an email to 0 people.
		if(count($emails) === 0) {
			return true;
		}

		$content = $this->generateCommentInsightChunk($paste, $line);
		$Smtp = new Email($this->config['smtp']['host'], $this->config['smtp']['port']);
		$Smtp->setLogin($this->config['smtp']['username'], $this->config['smtp']['password']);
		$Smtp->addTo(join(',', $emails));
		$Smtp->setFrom($this->config['smtp']['from_email'], $this->config['smtp']['from_name']);
		$Smtp->addReplyTo($comment_user_email);
		$Smtp->setProtocol(Email::TLS);
		$Smtp->setSubject('You have a new paste comment!');
		$html = <<<HTML
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
			<div id="paste">
				{$content}
			</div>
			<p>You can view this comment directly on the page by clicking <a href="{$this->config['app_base_url']}/{$paste->uid}">here</a>.</p>
			<p>Hope you have a nice day!</p>
			<p>Commie Bot</p>
		</div>
	</body>
</html>
HTML;

		$css = file_get_contents(__DIR__.'/../../public/lib/style.css')."\n".
			file_get_contents(__DIR__.'/../../public/lib/sunburst.css');

		$CssToInlineStyles = new CssToInlineStyles;
		$html = $CssToInlineStyles->convert($html, $css);
		$Smtp->setHtmlMessage($html);
		$send_result = $Smtp->send();
		//$send_result = true;
		return (bool) $send_result;
	}

	/**
	 * Generates the section of code before the comment
	 *
	 * @param array   $array_of_lines array_of_lines
	 * @param integer $line           line
	 * @return string
	 */
	public function generateCommentInsightChunk($paste, int $line): string {
		$line_count = count(explode("\n", $paste->content));
		if($line - 5 < 0) {
			$cutting_line = 0;
		} 
		
		if($line + 5 > $line_count) {
			$ending_line = $line_count;
		} else {
			$ending_line = 10;
		}

		$starting_number = $cutting_line + 1;
		return $this->highlightText($paste, $starting_number, $cutting_line, $ending_line)['highlighted_content'];
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
			$lines = array_slice($lines, $slice_key_offset, $slice_length, true);
		}

		$comments_by_line = [];
		if($paste->comments) {
			
			foreach($paste->comments as $comment) {
				$comments_by_line[$comment->line][] = $comment;
			}
		}

		$html = '<div class="hljs"><ol class="linenums" start="'.$line_starting_number.'">';
		foreach($lines as $line_number => $line) {
			$real_line_number = $line_number + 1;

			$comment_html = '';
			if(isset($comments_by_line[$real_line_number])) {
				foreach($comments_by_line[$real_line_number] as $comment_line) {
					$template_vars = [
						'comment' => $this->convertMarkdownToInlineHtml($comment_line->comment),
						'color' => $comment_line->color,
						'time' => (new DateTime('@'.$comment_line->time, new DateTimeZone($this->getTimeZone()))),
						'name' => $comment_line->name
					];
					$comment_html .= $this->f3->Latte->renderToString(__DIR__.'/../views/comment.latte', $template_vars);
				}
			}
			$html .= <<<HTML
<li 
	id="L{$real_line_number}" 
	class="line-number" 
>
	<span class="edit" hx-get="{$this->f3->BASE}/{$paste->uid}/get-comment-form/{$real_line_number}" 
	hx-target="#L{$real_line_number} .comment-form-container"
	_="on htmx:beforeRequest 
		if .new_comment_form.innerHTML.length > 0
			halt the event
		end
	   end"
	>&#9998;</span> 
	<pre><code class="{$highlighted->language}">{$line}</code></pre>
	<div class="comment-form-container"></div>
	<div class="comments">{$comment_html}</div>
</li>
HTML;
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
		$parser->html5 = true;
		$parsed_text = $parser->parse($text); 
		// this undoes some of the htmlspecialchars() that was done by the plugin to display properly.
		$parsed_text = preg_replace_callback("~(<pre><code>|<code>)([\s\S]+)(</code></pre>|</code>)~iUm", function($matches) {
			return $matches[1].htmlspecialchars_decode($matches[2]).$matches[3];
		}, $parsed_text);
		return $parsed_text;
	}

	/**
	 * Gets the time zone from a geo ip api endpoint if it's not in session
	 *
	 * @param string $ip_address an ip address
	 * @return string
	 */
	public function getTimeZone(string $ip_address = ''): string {
		
		// Pull out time zone data
		if(empty($this->f3->COOKIE['time_zone'])) {

			if(empty($ip_address)) {
				$ip_address = $this->f3->IP;
			}

			$data = [];
			try {
				$data = json_decode($this->f3->read('https://geo-ip.io/1.0/ip/'.$ip_address), true, 512, JSON_THROW_ON_ERROR);
			} catch(Throwable $e) {
				$data['timezone'] = 'UTC';
			}
			$this->f3->COOKIE['time_zone'] = $data['timezone'] ?? 'UTC';
		}
		return $this->f3->COOKIE['time_zone'];
	}
}