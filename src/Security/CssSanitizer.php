<?php

namespace Platform\Slides\Security;

/**
 * Bereinigt Theme-CSS (Stylesheet) und style-Attribute: keine Imports, keine Skript-/Binding-Ausdrücke,
 * keine Ausbrüche aus dem <style>-Block, url() nur auf https, eigene Pfade, Fragmente oder data:image/data:font.
 */
class CssSanitizer
{
    public const MAX_CSS_BYTES = 120_000;

    /**
     * @return array{0:string, 1:string[]} [css, removed]
     */
    public static function sanitizeStylesheet(string $css): array
    {
        $removed = [];
        if (strlen($css) > self::MAX_CSS_BYTES) {
            throw new \InvalidArgumentException('Theme-CSS ist größer als ' . (self::MAX_CSS_BYTES / 1000) . ' KB.');
        }

        // Ausbruch aus <style> verhindern
        if (preg_match('#</?\s*(style|script)#i', $css)) {
            $css = preg_replace('#</?\s*(style|script)[^>]*>?#i', '', $css);
            $removed[] = 'HTML-Tags im CSS entfernt.';
        }
        $css = str_replace(['<!--', '-->'], '', $css);

        if (preg_match('/@import\b/i', $css)) {
            $css = preg_replace('/@import\b[^;]*;?/i', '', $css);
            $removed[] = '@import entfernt (Schriften über das Feld fonts angeben).';
        }
        if (preg_match('/@charset\b/i', $css)) {
            $css = preg_replace('/@charset\b[^;]*;?/i', '', $css);
        }

        [$css, $more] = self::stripDangerous($css);

        return [trim($css), array_values(array_unique(array_merge($removed, $more)))];
    }

    /**
     * Für style-Attribute: nur Deklarationen, keine Blöcke.
     *
     * @return array{0:string, 1:string[]}
     */
    public static function sanitizeDeclarations(string $css): array
    {
        $removed = [];
        if (preg_match('/[{}<>]|@import/i', $css)) {
            $css = preg_replace('/[{}<>]|@import/i', '', $css);
            $removed[] = 'unzulässige Zeichen ({ } < > @import) entfernt';
        }
        [$css, $more] = self::stripDangerous($css);

        return [trim($css), array_values(array_unique(array_merge($removed, $more)))];
    }

    /** @return array{0:string, 1:string[]} */
    private static function stripDangerous(string $css): array
    {
        $removed = [];
        // Unicode-/Hex-Escapes ausschreiben, damit nichts an den Prüfungen vorbeigeschmuggelt wird
        $decoded = preg_replace_callback('/\\\\([0-9a-fA-F]{1,6})\s?/', fn ($m) => mb_chr(hexdec($m[1])) ?: '', $css);
        if ($decoded !== $css && preg_match('/expression|javascript|behavior|binding|url\s*\(/i', $decoded)) {
            $css = $decoded;
            $removed[] = 'CSS-Escapes aufgelöst';
        }

        foreach (['/expression\s*\(/i' => 'expression()', '/-moz-binding\s*:/i' => '-moz-binding', '/behavior\s*:/i' => 'behavior'] as $pattern => $label) {
            if (preg_match($pattern, $css)) {
                $css = preg_replace($pattern, 'x-removed(', $css);
                $removed[] = "{$label} entfernt";
            }
        }

        $css = preg_replace_callback('/url\s*\(\s*([\'"]?)(.*?)\1\s*\)/is', function ($m) use (&$removed) {
            $target = trim($m[2]);
            $lower = strtolower(preg_replace('/\s+/', '', $target));
            $ok = str_starts_with($lower, 'https://')
                || str_starts_with($lower, '#')
                || (str_starts_with($lower, '/') && ! str_starts_with($lower, '//'))
                || preg_match('#^data:(image/(png|jpe?g|webp|gif|svg\+xml)|font/(woff2?|ttf|otf)|application/font-woff2?)[;,]#', $lower);
            if (! $ok) {
                $removed[] = 'url(' . mb_substr($target, 0, 40) . ') entfernt';

                return 'none';
            }

            return 'url("' . str_replace(['"', "\n", "\r"], '', $target) . '")';
        }, $css);

        if (preg_match('/javascript\s*:|vbscript\s*:/i', $css)) {
            $css = preg_replace('/(javascript|vbscript)\s*:/i', '', $css);
            $removed[] = 'Skript-URL entfernt';
        }

        return [$css, $removed];
    }
}
