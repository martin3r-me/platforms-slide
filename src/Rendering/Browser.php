<?php

namespace Platform\Slides\Rendering;

use Spatie\Browsershot\Browsershot;

/**
 * Headless-Chrome über spatie/browsershot, konfiguriert wie der PdfRenderer des Cores
 * (platform.documents.chromium_path / node_path / npm_path) plus slides.browser.* (no_sandbox, node_modules, timeout).
 */
class Browser
{
    public static function available(): bool
    {
        return class_exists(Browsershot::class) && (bool) config('slides.browser.enabled', true);
    }

    public static function make(string $html): Browsershot
    {
        if (! self::available()) {
            throw new \RuntimeException('Kein Headless-Browser verfügbar (spatie/browsershot fehlt oder slides.browser.enabled=false).');
        }
        $b = Browsershot::html($html)->timeout((int) config('slides.browser.timeout', 60));
        $core = config('platform.documents', []);
        $chrome = config('slides.browser.chrome_path') ?: ($core['chromium_path'] ?? null);
        $node = config('slides.browser.node_path') ?: ($core['node_path'] ?? null);
        $npm = $core['npm_path'] ?? null;
        if ($chrome) {
            $b->setChromePath($chrome);
        }
        if ($node) {
            $b->setNodeBinary($node);
        }
        if ($npm) {
            $b->setNpmBinary($npm);
        }
        if ($modules = config('slides.browser.node_modules_path')) {
            $b->setNodeModulePath($modules);
        }
        if (config('slides.browser.no_sandbox', false)) {
            $b->noSandbox();
        }

        return $b;
    }
}
