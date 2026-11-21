<?php

namespace Tests\Feature;

require_once __DIR__ . '/SlidesTestHarness.php';

use Platform\Slides\Security\CssSanitizer;
use Platform\Slides\Security\HtmlSanitizer;
use Tests\TestCase;

/** Sanitizer: Gefährliches raus, Erlaubtes bleibt, genau ein <section>, Notizen aus <aside>. */
class SanitizerTest extends TestCase
{
    use SlidesTestHarness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSlides();
    }

    private function clean(string $html, string $key = 'k1'): array
    {
        return (new HtmlSanitizer())->sanitizeSlide($html, $key);
    }

    public function test_removes_scripts_handlers_and_dangerous_urls(): void
    {
        $r = $this->clean('<section class="title" onclick="x()"><p style="background:url(javascript:alert(1)); color: red; width: expression(alert(1))">t</p><script>alert(1)</script><h1 onmouseover="x()">Hallo</h1>'
            . '<img src="x" onerror="alert(1)"><img src="javascript:alert(1)"><a href="javascript:alert(1)">a</a>'
            . '<iframe src="https://evil"></iframe><form><input></form><object data="x"></object><embed src="x"></section>');
        $h = $r['html'];
        foreach (['<script', 'onclick', 'onmouseover', 'onerror', 'javascript:', '<iframe', '<form', '<input', '<object', '<embed', 'expression('] as $bad) {
            $this->assertStringNotContainsStringIgnoringCase($bad, $h, $bad);
        }
        $this->assertStringContainsString('<h1>Hallo</h1>', $h);
        $this->assertStringContainsString('color: red', $h);
        $this->assertStringContainsString('class="title"', $h);
        $this->assertNotEmpty($r['removed']);
    }

    public function test_keeps_allowed_markup_svg_and_tables(): void
    {
        $r = $this->clean('<section><h2>Zahlen</h2><table class="data"><tr><th>Jahr</th><td class="num" colspan="2">800</td></tr></table>'
            . '<svg viewBox="0 0 100 50" aria-label="Chart"><defs><linearGradient id="g1" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#000"/></linearGradient></defs>'
            . '<rect x="0" y="0" width="10" height="20" fill="url(#g1)"/><path d="M0 0 L10 10" stroke="#333"/><use href="#g1"/><use href="https://evil/x.svg#a"/></svg>'
            . '<img src="https://example.org/a.png" alt="A" width="100" height="50"><a href="https://bhg.de">Link</a></section>');
        $h = $r['html'];
        $this->assertStringContainsString('viewBox="0 0 100 50"', $h);
        $this->assertStringContainsString('<linearGradient', $h);
        $this->assertStringContainsString('gradientUnits="userSpaceOnUse"', $h);
        $this->assertStringContainsString('<table class="data">', $h);
        $this->assertStringContainsString('colspan="2"', $h);
        $this->assertStringContainsString('src="https://example.org/a.png"', $h);
        $this->assertStringContainsString('rel="noopener noreferrer"', $h);
        $this->assertStringNotContainsString('https://evil', $h);
    }

    public function test_exactly_one_section_with_key_and_notes_from_aside(): void
    {
        $r = $this->clean('<section id="foo"><h1>A</h1><aside>Sag hallo.</aside></section><section><h1>B</h1></section>', 'cover');
        $this->assertSame(1, substr_count($r['html'], '<section'));
        $this->assertStringContainsString('id="cover"', $r['html']);
        $this->assertStringNotContainsString('<aside', $r['html']);
        $this->assertSame('Sag hallo.', $r['notes']);
        $this->assertStringNotContainsString('>B<', $r['html']);

        $wrapped = $this->clean('<h2>Ohne Section</h2><p>Text</p>', 'k2');
        $this->assertStringStartsWith('<section id="k2">', $wrapped['html']);
        $this->assertStringContainsString('<h2>Ohne Section</h2>', $wrapped['html']);
    }

    public function test_unknown_elements_are_unwrapped_and_umlauts_survive(): void
    {
        $r = $this->clean('<section><x-shape kind="rect">Größe & Maß „ok“</x-shape><p>Über</p></section>');
        $this->assertStringContainsString('Größe &amp; Maß „ok“', $r['html']);
        $this->assertStringNotContainsString('<x-shape', $r['html']);
        $this->assertStringContainsString('<p>Über</p>', $r['html']);
    }

    public function test_css_sanitizer(): void
    {
        [$css, $removed] = CssSanitizer::sanitizeStylesheet('@import url(https://evil.css); section { color: red; background: url(javascript:alert(1)); } </style><script>alert(1)</script> .a{behavior:url(x.htc)} .b{width:expression(alert(1))} .c{background:url("https://ok.org/a.png")}');
        $this->assertStringNotContainsStringIgnoringCase('@import', $css);
        $this->assertStringNotContainsStringIgnoringCase('javascript', $css);
        $this->assertStringNotContainsStringIgnoringCase('</style', $css);
        $this->assertStringNotContainsStringIgnoringCase('<script', $css);
        $this->assertStringNotContainsStringIgnoringCase('expression(', $css);
        $this->assertStringContainsString('color: red', $css);
        $this->assertStringContainsString('url("https://ok.org/a.png")', $css);
        $this->assertNotEmpty($removed);
    }
}
