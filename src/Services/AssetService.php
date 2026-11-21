<?php

namespace Platform\Slides\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Platform\Core\Models\User;
use Platform\Slides\Models\SlidesAsset;
use Platform\Slides\Models\SlidesDeck;
use Platform\Slides\Models\SlidesDeckSlide;

/**
 * Bilder für Folien speichern und finden. Der Dateityp wird am Inhalt erkannt (nicht am Namen);
 * erlaubt sind PNG, JPEG, WebP, GIF und SVG. Ausgeliefert über SlidesAssetController (Token-URL).
 */
class AssetService
{
    public const TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
    ];

    public function store(int $teamId, ?SlidesDeck $deck, string $contents, ?string $originalName, ?User $by, ?string $alt = null): SlidesAsset
    {
        $max = (int) config('slides.assets.max_kb', 10240) * 1024;
        if ($contents === '') {
            throw new \InvalidArgumentException('Die Datei ist leer.');
        }
        if (strlen($contents) > $max) {
            throw new \InvalidArgumentException('Bild zu groß (max. ' . round($max / 1048576, 1) . ' MB).');
        }
        if ($deck && (int) $deck->team_id !== $teamId) {
            throw new \InvalidArgumentException('Deck gehört zu einem anderen Team.');
        }

        $mime = $this->detectMime($contents);
        $ext = self::TYPES[$mime] ?? throw new \InvalidArgumentException('Nur PNG, JPEG, WebP, GIF oder SVG (erkannt: ' . $mime . ').');

        [$w, $h] = [null, null];
        if ($mime === 'image/svg+xml') {
            [$w, $h] = $this->svgSize($contents);
        } elseif ($info = @getimagesizefromstring($contents)) {
            [$w, $h] = [$info[0] ?: null, $info[1] ?: null];
        }

        $token = Str::random(40);
        $disk = (string) config('slides.assets.disk', 'local');
        $path = 'slides/assets/' . $teamId . '/' . $token . '.' . $ext;
        Storage::disk($disk)->put($path, $contents);

        return SlidesAsset::create([
            'token' => $token,
            'team_id' => $teamId,
            'deck_id' => $deck?->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $originalName !== null ? mb_substr(basename($originalName), 0, 255) : null,
            'mime_type' => $mime,
            'extension' => $ext,
            'size' => strlen($contents),
            'width' => $w,
            'height' => $h,
            'alt' => $alt !== null ? (mb_substr(trim($alt), 0, 300) ?: null) : null,
            'created_by' => $by?->id,
        ]);
    }

    /** Folien (im Team), die das Bild verwenden: [[deck_id, deck_title, key], …] */
    public function usages(SlidesAsset $asset): array
    {
        return SlidesDeckSlide::query()
            ->join('slides_decks', 'slides_decks.id', '=', 'slides_deck_slides.deck_id')
            ->where('slides_decks.team_id', $asset->team_id)
            ->whereNull('slides_decks.deleted_at')
            ->where('slides_deck_slides.html', 'like', '%' . $asset->token . '%')
            ->get(['slides_deck_slides.deck_id', 'slides_decks.title', 'slides_deck_slides.slide_key'])
            ->map(fn ($r) => ['deck_id' => (int) $r->deck_id, 'deck_title' => $r->title, 'key' => $r->slide_key])
            ->all();
    }

    public function delete(SlidesAsset $asset): void
    {
        try {
            Storage::disk($asset->disk)->delete($asset->path);
        } catch (\Throwable) {
            // Datei schon weg – Eintrag trotzdem entfernen
        }
        $asset->delete();
    }

    private function detectMime(string $contents): string
    {
        $head = ltrim(substr($contents, 0, 1024));
        if (preg_match('/^(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*(<!DOCTYPE svg[^>]*>\s*)?<svg[\s>]/is', $head)) {
            return 'image/svg+xml';
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        return (string) $finfo->buffer($contents);
    }

    /** @return array{0:?int,1:?int} */
    private function svgSize(string $svg): array
    {
        if (preg_match('/<svg[^>]*>/is', $svg, $m)) {
            $tag = $m[0];
            $num = fn ($attr) => preg_match('/\s' . $attr . '\s*=\s*["\']\s*([\d.]+)\s*(px)?\s*["\']/i', $tag, $x) ? (int) round((float) $x[1]) : null;
            $w = $num('width');
            $h = $num('height');
            if ((! $w || ! $h) && preg_match('/viewBox\s*=\s*["\']\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)/i', $tag, $v)) {
                [$w, $h] = [(int) round((float) $v[1]), (int) round((float) $v[2])];
            }

            return [$w ?: null, $h ?: null];
        }

        return [null, null];
    }
}
