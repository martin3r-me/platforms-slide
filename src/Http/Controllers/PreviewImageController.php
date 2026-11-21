<?php

namespace Platform\Slides\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Platform\Slides\Services\PreviewService;

/** Liefert eine von slides.preview.GET erzeugte Folien-Vorschau aus (signierte, befristete URL – ohne Login). */
class PreviewImageController extends Controller
{
    public function __invoke(int $deck, string $file, PreviewService $previews)
    {
        if (! preg_match('/^[a-f0-9]{40}\.jpg$/', $file)) {
            abort(404);
        }
        $path = 'slides/previews/' . $deck . '/' . $file;
        $disk = Storage::disk($previews->disk());
        if (! $disk->exists($path)) {
            abort(404);
        }

        return response($disk->get($path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
