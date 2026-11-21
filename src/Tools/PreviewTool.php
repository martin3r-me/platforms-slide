<?php

namespace Platform\Slides\Tools;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Rendering\Browser;
use Platform\Slides\Services\PreviewService;
use Platform\Slides\Tools\Concerns\HandlesDecks;

class PreviewTool implements ToolContract, ToolMetadataContract
{
    use HandlesDecks;

    public function getName(): string
    {
        return 'slides.preview.GET';
    }

    public function getDescription(): string
    {
        return 'GET /slides/decks/{id}/preview - Zeigt dir, was du gebaut hast: rendert die Folien im Headless-Chrome und meldet je Folie gemessene Layout-Probleme (overflow = ragt über die Folie, clipped = Text abgeschnitten, outside_safe_area, small_text, image_broken, overlap, empty) plus Inhaltshinweise (dense, many_bullets, no_heading, placeholder, no_notes) und den Füllgrad (fill % der nutzbaren Höhe). Die Vorschaubilder (bis zu 6, Folien mit Problemen zuerst) hängen direkt an der Antwort – du siehst die Folien; zusätzlich je Folie image_url (signierter Link, ca. 60 Min. gültig). Nach jedem Schreiben aufrufen; errors beheben, warnings prüfen. Pflicht: deck_id. Optional: keys (nur diese Folien), screenshots (Default true; max. 8 Bilder pro Aufruf – bei größeren Decks ohne keys bekommen zuerst Folien mit Problemen ein Bild), width (Bildbreite 320–1920, Default 960).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deck_id' => ['type' => 'integer'],
                'keys' => ['type' => 'array', 'items' => ['type' => 'string']],
                'screenshots' => ['type' => 'boolean'],
                'width' => ['type' => 'integer'],
            ],
            'required' => ['deck_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['deck' => $deck, 'error' => $error] = $this->findDeck($arguments, $context);
            if ($error) {
                return $error;
            }
            $query = $deck->slides();
            $keys = ! empty($arguments['keys']) && is_array($arguments['keys']) ? array_values($arguments['keys']) : null;
            if ($keys) {
                $query->whereIn('slide_key', $keys);
            }
            $slides = $query->get();
            if ($slides->isEmpty()) {
                return ToolResult::error($keys ? 'Keine der angegebenen Folien gefunden.' : 'Das Deck hat noch keine Folien.', 'NOT_FOUND');
            }

            $svc = app(PreviewService::class);
            $browser = ['available' => Browser::available(), 'error' => null];
            $layout = [];
            if ($browser['available']) {
                try {
                    $layout = $svc->inspect($deck, $slides);
                } catch (\Throwable $e) {
                    report($e);
                    $browser = ['available' => false, 'error' => mb_substr($e->getMessage(), 0, 300)];
                }
            }

            $rows = $slides->values()->map(function ($s, $i) use ($svc, $layout) {
                $issues = $svc->merge($layout[$s->slide_key]['issues'] ?? [], $svc->lint($s));

                return [
                    'key' => $s->slide_key,
                    'number' => $i + 1,
                    'is_hidden' => $s->is_hidden,
                    'fill' => $layout[$s->slide_key]['fill'] ?? null,
                    'errors' => count(array_filter($issues, fn ($x) => $x['level'] === 'error')),
                    'warnings' => count(array_filter($issues, fn ($x) => $x['level'] === 'warning')),
                    'issues' => $issues,
                    'image_url' => null,
                ];
            })->all();

            $shotNote = null;
            $shots = []; // Reihenfolge wie erzeugt (Folien mit Problemen zuerst)
            if (($arguments['screenshots'] ?? true) && $browser['available']) {
                $max = (int) config('slides.preview.max_screenshots', 8);
                $order = array_keys($rows);
                if (! $keys && count($rows) > $max) {
                    usort($order, fn ($a, $b) => [$rows[$b]['errors'], $rows[$b]['warnings'], $a] <=> [$rows[$a]['errors'], $rows[$a]['warnings'], $b]);
                    $shotNote = 'Bilder nur für ' . $max . ' von ' . count($rows) . ' Folien (zuerst die mit Problemen). Für andere Folien keys angeben.';
                }
                $width = (int) ($arguments['width'] ?? 960);
                $minutes = (int) config('slides.preview.url_minutes', 60);
                $byKey = $slides->keyBy('slide_key');
                foreach (array_slice($order, 0, $max) as $idx) {
                    try {
                        $path = $svc->screenshot($deck, $byKey[$rows[$idx]['key']], $width);
                        $shots[] = ['path' => $path, 'key' => $rows[$idx]['key']];
                        $rows[$idx]['image_url'] = URL::temporarySignedRoute('slides.previews.show', now()->addMinutes($minutes), ['deck' => $deck->id, 'file' => basename($path)]);
                    } catch (\Throwable $e) {
                        report($e);
                        $browser['error'] ??= mb_substr($e->getMessage(), 0, 300);
                        break;
                    }
                }
            }

            $errors = array_sum(array_column($rows, 'errors'));
            $warnings = array_sum(array_column($rows, 'warnings'));

            // Bilder direkt in der MCP-Antwort mitgeben, damit der Agent sein Ergebnis sieht (Core ab e0788ad8)
            $maxInline = (int) config('slides.preview.max_inline_images', 6);
            $inline = method_exists(ToolResult::class, 'withImage') ? array_slice($shots, 0, $maxInline) : [];
            foreach ($rows as &$row) {
                $row['image_attached'] = in_array($row['key'], array_column($inline, 'key'), true);
            }
            unset($row);
            if (count($shots) > count($inline) && $inline !== []) {
                $shotNote = trim(($shotNote ?? '') . ' Direkt angehängt sind ' . count($inline) . ' Bilder (' . implode(', ', array_column($inline, 'key')) . '); weitere nur über image_url.');
            }

            $result = ToolResult::success([
                'deck_id' => $deck->id,
                'revision' => $deck->revision,
                'browser_check' => $browser['available'] ? 'ok' : 'unavailable',
                'browser_error' => $browser['error'],
                'summary' => ['slides' => count($rows), 'errors' => $errors, 'warnings' => $warnings],
                'slides' => $rows,
                'note' => $shotNote,
                'message' => ($browser['available'] ? '' : 'Kein Headless-Browser verfügbar – nur Inhaltsprüfung, keine Layout-Messung und keine Bilder. ')
                    . ($errors + $warnings === 0 ? 'Keine Probleme gefunden.' : "{$errors} Fehler, {$warnings} Warnungen.")
                    . ($inline ? ' Vorschaubilder sind angehängt (Reihenfolge: ' . implode(', ', array_column($inline, 'key')) . ').' : ''),
            ]);
            $disk = Storage::disk($svc->disk());
            foreach ($inline as $shot) {
                $result = $result->withImage((string) $disk->get($shot['path']), 'image/jpeg');
            }

            return $result;
        } catch (\Throwable $e) {
            return $this->failure($e, 'Prüfen der Folien');
        }
    }

    public function getMetadata(): array
    {
        return $this->meta('GET', ['preview', 'layout', 'quality']);
    }
}
