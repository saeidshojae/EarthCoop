<?php

namespace Tests\Unit\GroupChat;

use App\Services\GroupChat\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_it_removes_executable_html_and_unsafe_urls(): void
    {
        $html = '<p onclick="alert(1)">Safe<script>alert(1)</script>'
            . '<a href="javascript:alert(2)" style="color:red">link</a></p>';

        $clean = (new HtmlSanitizer())->sanitize($html);

        $this->assertStringContainsString('<p>Safe', $clean);
        $this->assertStringContainsString('<a rel="noopener noreferrer nofollow">link</a>', $clean);
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('style=', $clean);
    }

    public function test_it_preserves_allowlisted_formatting_and_safe_links(): void
    {
        $clean = (new HtmlSanitizer())->sanitize('<h2>Title</h2><p dir="rtl"><strong>متن</strong> <a href="https://example.com">link</a></p>');

        $this->assertStringContainsString('<h2>Title</h2>', $clean);
        $this->assertStringContainsString('dir="rtl"', $clean);
        $this->assertStringContainsString('href="https://example.com"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $clean);
    }
}
