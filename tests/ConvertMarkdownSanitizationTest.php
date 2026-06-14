<?php
declare(strict_types=1);

namespace Tests;

use app\logic\PasteLogic;
use flight\Engine;
use PHPUnit\Framework\TestCase;

class ConvertMarkdownSanitizationTest extends TestCase
{
    public function testMarkdownScriptTagIsStrippedFromOutput(): void
    {
        $logic = new PasteLogic(new Engine(), [
            'encryption_key' => '',
            'enable_smtp' => false,
        ]);

        $html = $logic->convertMarkdownToInlineHtml('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script', $html);
    }

    public function testMarkdownLinkWithJavascriptSchemeIsNeutralized(): void
    {
        $logic = new PasteLogic(new Engine(), [
            'encryption_key' => '',
            'enable_smtp' => false,
        ]);

        $html = $logic->convertMarkdownToInlineHtml('[click](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function testSafeMarkdownStillRenders(): void
    {
        $logic = new PasteLogic(new Engine(), [
            'encryption_key' => '',
            'enable_smtp' => false,
        ]);

        $html = $logic->convertMarkdownToInlineHtml('**bold** and [link](https://example.com)');

        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
    }
}