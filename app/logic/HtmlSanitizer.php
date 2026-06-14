<?php
declare(strict_types=1);

namespace app\logic;

class HtmlSanitizer {

	/** @var string[] */
	private static $blockedTags = [
		'script', 'iframe', 'object', 'embed', 'form', 'input', 'meta', 'link', 'base', 'style',
	];

	/**
	 * Strip dangerous HTML while preserving safe rendered markdown.
	 */
	public static function sanitize(string $html): string {
		foreach (self::$blockedTags as $tag) {
			$html = preg_replace('#<' . $tag . '\b[^>]*>.*?</' . $tag . '>#is', '', $html);
			$html = preg_replace('#<' . $tag . '\b[^>]*/?>#is', '', $html);
		}

		$html = preg_replace('/\s+on\w+\s*=\s*("|\').*?\1/is', '', $html);
		$html = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/is', '', $html);

		$html = preg_replace_callback(
			'#\s(href|src)\s*=\s*("|\')([^"\']*)\2#i',
			function (array $matches): string {
				$url = trim($matches[3]);
				if (preg_match('#^(javascript|vbscript|data:text/html)#i', $url)) {
					return ' ' . $matches[1] . '="#"';
				}
				return $matches[0];
			},
			$html
		);

		return $html;
	}
}