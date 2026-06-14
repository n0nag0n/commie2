<?php
declare(strict_types=1);

namespace Tests;

use app\logic\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function testStripsScriptTags(): void
    {
        $input = '<p>Hello</p><script>alert(1)</script>';
        $output = HtmlSanitizer::sanitize($input);

        $this->assertStringNotContainsString('<script', $output);
        $this->assertStringContainsString('<p>Hello</p>', $output);
    }

    public function testStripsEventHandlerAttributes(): void
    {
        $input = '<img src="https://example.com/x.png" onerror="alert(1)">';
        $output = HtmlSanitizer::sanitize($input);

        $this->assertStringNotContainsString('onerror', $output);
        $this->assertStringContainsString('src=', $output);
    }

    public function testNeutralizesJavascriptUrls(): void
    {
        $input = '<a href="javascript:alert(1)">click</a>';
        $output = HtmlSanitizer::sanitize($input);

        $this->assertStringNotContainsString('javascript:', $output);
    }

    public function testPreservesSafeLinks(): void
    {
        $input = '<a href="https://example.com">Example</a>';
        $output = HtmlSanitizer::sanitize($input);

        $this->assertStringContainsString('href="https://example.com"', $output);
        $this->assertStringContainsString('Example</a>', $output);
    }

    public function testStripsIframeTags(): void
    {
        $input = '<iframe src="https://evil.example"></iframe><p>ok</p>';
        $output = HtmlSanitizer::sanitize($input);

        $this->assertStringNotContainsString('<iframe', $output);
        $this->assertStringContainsString('<p>ok</p>', $output);
    }
}