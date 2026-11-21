<?php

namespace Platform\Slides\Rendering;

use Platform\Slides\Enums\DeckSize;
use Platform\Slides\Models\SlidesDeck;
use Platform\Slides\Models\SlidesDeckSlide;
use Platform\Slides\Security\CssSanitizer;

/**
 * Baut das vollständige HTML-Dokument einer Folie für ein <iframe sandbox srcdoc>.
 * Folien-HTML und Theme-CSS sind beim Speichern bereinigt; hier kommt eine restriktive CSP dazu
 * (keine Skripte, Bilder nur https/eigene/data, Schriften nur Google Fonts).
 * Die Skalierung auf die verfügbare Breite übernimmt das Eltern-Layout (CSS transform).
 */
class SlideDocument
{
    /**
     * @param bool $static true = Standbild (Übersicht, Druck): Klickschritte (.fragment) sind sichtbar, keine Übergänge.
     *                     false = Präsentation: .fragment ist bis zum Klick verborgen; das Eltern-Fenster setzt .visible/.current-fragment.
     * @param ?string $base Basis-URL für relative Pfade (nötig, wenn das Dokument außerhalb der Plattform gerendert wird: Headless-Chrome, PDF).
     */
    public static function render(SlidesDeck $deck, SlidesDeckSlide|string $slide, bool $static = true, ?string $base = null): string
    {
        $size = $deck->size instanceof DeckSize ? $deck->size : (DeckSize::tryFrom((string) $deck->size) ?? DeckSize::WIDE);
        $w = $size->width();
        $h = $size->height();
        $html = $slide instanceof SlidesDeckSlide ? $slide->html : $slide;
        // Theme-CSS ist gespeichert bereits bereinigt; erneut absichern, falls direkt in die DB geschrieben wurde
        [$theme] = CssSanitizer::sanitizeStylesheet((string) $deck->theme_css);

        $fontLink = '';
        $families = self::fontFamilies($deck->fonts ?? []);
        if ($families !== []) {
            // Variable Schriften mit Gewichtsachse anfragen, sonst Standardschnitt (falsche Achsen lassen die API scheitern)
            $variable = ['Inter' => '300..800', 'Source Serif 4' => '300..800', 'Space Grotesk' => '300..700', 'Fraunces' => '300..800',
                'Manrope' => '300..800', 'DM Sans' => '300..800', 'Outfit' => '300..800', 'Roboto Flex' => '300..800', 'Montserrat' => '300..800',
                'Playfair Display' => '400..800', 'Lora' => '400..700', 'Work Sans' => '300..800', 'Plus Jakarta Sans' => '300..800'];
            $query = implode('&', array_map(fn ($f) => 'family=' . str_replace(' ', '+', $f) . (isset($variable[$f]) ? ':wght@' . $variable[$f] : ''), $families));
            $fontLink = '<link rel="preconnect" href="https://fonts.googleapis.com">'
                . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?' . htmlspecialchars($query, ENT_QUOTES) . '&display=swap">';
        }

        // Eigene Bilder (slides.assets) auch dann zulassen, wenn die Plattform ohne https läuft (lokal)
        $own = self::origin((string) config('app.url'));
        $csp = "default-src 'none'; img-src 'self'" . ($own ? ' ' . $own : '') . " https: data:; style-src 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com data:";

        return '<!doctype html><html><head><meta charset="utf-8">'
            . '<meta http-equiv="Content-Security-Policy" content="' . $csp . '">'
            . ($base ? '<base href="' . htmlspecialchars(rtrim($base, '/') . '/', ENT_QUOTES) . '">' : '')
            . $fontLink
            . '<style>*,*::before,*::after{box-sizing:border-box}html,body{margin:0;padding:0;width:' . $w . 'px;height:' . $h . 'px;overflow:hidden;background:transparent}'
            . 'body>section{position:relative;width:' . $w . 'px;height:' . $h . 'px;overflow:hidden;margin:0}img,svg{max-width:100%}'
            // Klickschritte (reveal.js-Semantik): .fragment bis zum Klick verborgen, .visible danach. Das Theme kann beides überschreiben.
            . '.fragment{opacity:0;transition:opacity .35s ease,transform .35s ease}.fragment.visible{opacity:1}</style>'
            . '<style>' . $theme . '</style>'
            . ($static ? '<style>.fragment,.fragment:not(.visible){opacity:1!important;visibility:visible!important;transform:none!important;transition:none!important}</style>' : '')
            . '</head><body class="' . ($static ? 'is-static' : 'is-live') . '">' . $html . '</body></html>';
    }

    private static function origin(string $url): ?string
    {
        $p = parse_url($url);
        if (empty($p['scheme']) || empty($p['host']) || ! in_array($p['scheme'], ['http', 'https'], true)) {
            return null;
        }

        return $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    }

    /** @return string[] */
    public static function fontFamilies(array $fonts): array
    {
        $out = [];
        foreach ($fonts as $f) {
            $f = trim((string) $f);
            if (preg_match('/^[A-Za-z][A-Za-z0-9 ]{0,39}$/', $f)) {
                $out[] = $f;
            }
        }

        return array_slice(array_values(array_unique($out)), 0, 4);
    }
}
