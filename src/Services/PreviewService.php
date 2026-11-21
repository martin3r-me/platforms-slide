<?php

namespace Platform\Slides\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Platform\Slides\Models\SlidesDeck;
use Platform\Slides\Models\SlidesDeckSlide;
use Platform\Slides\Rendering\Browser;
use Platform\Slides\Rendering\SlideDocument;

/**
 * Damit Agenten (und Menschen) sehen, was sie gebaut haben:
 * - lint(): Inhaltsregeln ohne Browser (Textmenge, Aufzählungen, Platzhalter, fehlende Notizen …) – immer verfügbar.
 * - inspect(): misst im Headless-Chrome jede Folie (Überlauf, Satzspiegel, abgeschnittener Text, kleine Schrift,
 *   kaputte Bilder, Überlappungen, Füllgrad). Eine Browser-Sitzung für alle Folien.
 * - screenshot(): JPEG-Vorschau einer Folie, nach Inhalt gecacht.
 * Ohne Chrome/Node (Browser::available() = false) liefern inspect/screenshot nichts, lint funktioniert weiter.
 */
class PreviewService
{
    /** @return array<int,array{level:string,code:string,message:string,element:?string}> */
    public function lint(SlidesDeckSlide $slide): array
    {
        $issues = [];
        $html = (string) $slide->html;
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NONET | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $xp = new \DOMXPath($doc);
        $section = $xp->query('//section')->item(0);
        if (! $section) {
            return [['level' => 'error', 'code' => 'no_section', 'message' => 'Kein <section> gefunden.', 'element' => null]];
        }
        foreach ($xp->query('.//aside', $section) as $aside) {
            $aside->parentNode->removeChild($aside);
        }
        $classes = ' ' . $section->getAttribute('class') . ' ';
        $text = trim(preg_replace('/\s+/u', ' ', $section->textContent));
        $words = $text === '' ? 0 : count(preg_split('/\s+/u', $text));
        $bullets = $xp->query('.//li', $section)->length;

        if ($xp->query('.//h1|.//h2', $section)->length === 0 && ! str_contains($classes, ' blank ')) {
            $issues[] = ['level' => 'warning', 'code' => 'no_heading', 'message' => 'Keine Überschrift (h1/h2) – jede Folie braucht eine erkennbare Aussage.', 'element' => null];
        }
        if ($words > 90) {
            $issues[] = ['level' => 'warning', 'code' => 'dense', 'message' => "Viel Text ({$words} Wörter). Eine Aussage pro Folie – Details in die Notizen oder auf eine zweite Folie.", 'element' => null];
        }
        if ($bullets > 6) {
            $issues[] = ['level' => 'warning', 'code' => 'many_bullets', 'message' => "{$bullets} Aufzählungspunkte. Mehr als 6 lassen sich nicht erfassen – gruppieren, als Tabelle/Kacheln zeigen oder teilen.", 'element' => null];
        }
        if (preg_match_all('/\[[^\]\[]{2,60}\]/u', $text, $m)) {
            $issues[] = ['level' => 'info', 'code' => 'placeholder', 'message' => 'Offene Platzhalter: ' . implode(', ', array_slice(array_unique($m[0]), 0, 5)) . '.', 'element' => null];
        }
        foreach ($xp->query('.//*[@style]', $section) as $el) {
            if (preg_match('/font-size\s*:\s*(\d+(?:\.\d+)?)px/i', $el->getAttribute('style'), $fm) && (float) $fm[1] < 22) {
                $issues[] = ['level' => 'warning', 'code' => 'small_text', 'message' => "Inline font-size {$fm[1]}px – mind. 24 px.", 'element' => $el->nodeName];
                break;
            }
        }
        foreach ($xp->query('.//img', $section) as $img) {
            if (trim($img->getAttribute('alt')) === '') {
                $issues[] = ['level' => 'info', 'code' => 'img_alt', 'message' => 'Bild ohne alt-Text.', 'element' => 'img'];
                break;
            }
        }
        if (! $slide->notes && ! str_contains($classes, ' title ') && ! $slide->is_hidden) {
            $issues[] = ['level' => 'info', 'code' => 'no_notes', 'message' => 'Keine Sprechernotizen.', 'element' => null];
        }

        return $issues;
    }

    /**
     * Folien im Browser messen.
     *
     * @param Collection<int,SlidesDeckSlide> $slides
     * @return array<string,array{issues:array,fill:?int}> je slide_key; leer, wenn kein Browser verfügbar
     */
    public function inspect(SlidesDeck $deck, Collection $slides): array
    {
        if ($slides->isEmpty()) {
            return [];
        }
        $w = $deck->size->width();
        $h = $deck->size->height();
        $frames = $slides->map(fn ($s) => '<iframe data-key="' . e($s->slide_key) . '" srcdoc="' . e(SlideDocument::render($deck, $s, true, url('/'))) . '"></iframe>')->implode('');
        $host = '<!doctype html><html><head><meta charset="utf-8"><style>html,body{margin:0;background:#fff}iframe{display:block;border:0;width:' . $w . 'px;height:' . $h . 'px}</style></head><body>' . $frames . '</body></html>';

        $raw = Browser::make($host)->windowSize($w, $h)->waitUntilNetworkIdle()->evaluate((string) file_get_contents(__DIR__ . '/../../resources/preview/layout-check.js'));
        $data = json_decode((string) $raw, true);
        if (! is_array($data)) {
            throw new \RuntimeException('Layout-Prüfung lieferte kein Ergebnis.');
        }

        return collect($data)->map(fn ($r) => array_is_list($r) ? ['issues' => $r, 'fill' => null] : ['issues' => $r['issues'] ?? [], 'fill' => $r['fill'] ?? null])->all();
    }

    /** JPEG-Vorschau (Breite in px) auf dem Vorschau-Disk; gibt den Pfad zurück. Gleicher Inhalt → gleiche Datei. */
    public function screenshot(SlidesDeck $deck, SlidesDeckSlide $slide, int $width = 960): string
    {
        $width = max(320, min(1920, $width));
        $w = $deck->size->width();
        $h = $deck->size->height();
        $height = (int) round($width * $h / $w);
        $doc = SlideDocument::render($deck, $slide, true, url('/'));
        $path = 'slides/previews/' . $deck->id . '/' . sha1($doc . '|' . $width) . '.jpg';
        $disk = Storage::disk($this->disk());
        if ($disk->exists($path)) {
            return $path;
        }

        $scale = $width / $w;
        $host = '<!doctype html><html><head><meta charset="utf-8"><style>html,body{margin:0;width:' . $width . 'px;height:' . $height . 'px;overflow:hidden;background:#000}'
            . 'iframe{border:0;width:' . $w . 'px;height:' . $h . 'px;transform:scale(' . $scale . ');transform-origin:0 0}</style></head><body>'
            . '<iframe srcdoc="' . e($doc) . '"></iframe></body></html>';
        $jpg = Browser::make($host)->windowSize($width, $height)->waitUntilNetworkIdle()->setScreenshotType('jpeg', 85)->screenshot();
        $disk->put($path, $jpg);

        return $path;
    }

    /** Gemessene und inhaltliche Hinweise zusammenführen; was der Browser gemessen hat, ersetzt die Schätzung gleichen Codes. */
    public function merge(array $measured, array $lint): array
    {
        $codes = array_column($measured, 'code');

        return array_merge($measured, array_values(array_filter($lint, fn ($i) => ! in_array($i['code'], $codes, true))));
    }

    public function disk(): string
    {
        return (string) config('slides.preview.disk', 'local');
    }
}
