<?php

namespace Platform\Slides\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Platform\Slides\Models\SlidesAsset;

/**
 * Liefert ein Folienbild über seine Token-URL aus (ohne Login – Folien-iframes, Präsentation und serverseitiges PDF
 * senden keine Session mit). Strenge Header: kein MIME-Sniffing, SVG ohne Skripte (CSP sandbox).
 */
class AssetController extends Controller
{
    public function __invoke(string $file)
    {
        if (! preg_match('/^([A-Za-z0-9]{40})\.(png|jpg|webp|gif|svg)$/', $file, $m)) {
            abort(404);
        }
        $asset = SlidesAsset::query()->where('token', $m[1])->first();
        if (! $asset || $asset->extension !== $m[2] || ! Storage::disk($asset->disk)->exists($asset->path)) {
            abort(404);
        }

        return response(Storage::disk($asset->disk)->get($asset->path), 200, [
            'Content-Type' => $asset->mime_type,
            'Content-Length' => (string) $asset->size,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src data:; style-src 'unsafe-inline'; sandbox",
            'Content-Disposition' => 'inline',
        ]);
    }
}
