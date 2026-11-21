<?php

namespace Platform\Slides\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Platform\Slides\Models\SlidesDeck;
use Platform\Slides\Rendering\SlideDocument;

/**
 * Serverseitiger PDF-Export (optional): nur wenn config('slides.pdf.server') aktiv ist und spatie/browsershot
 * samt Chrome/Node auf dem Server verfügbar sind. Sonst Weiterleitung auf die Druckansicht (Browser „Als PDF sichern“).
 */
class DeckPdfController extends Controller
{
    public function __invoke(SlidesDeck $deck)
    {
        Gate::authorize('view', $deck);
        $printUrl = route('slides.decks.pdf', $deck) . '?print=1';
        if (! config('slides.pdf.server') || ! class_exists(\Spatie\Browsershot\Browsershot::class)) {
            return redirect()->to($printUrl);
        }

        $size = $deck->size;
        $docs = $deck->slides()->where('is_hidden', false)->get()->map(fn ($s) => SlideDocument::render($deck, $s, true));
        $html = view('slides::pdf.document', ['docs' => $docs, 'w' => $size->width(), 'h' => $size->height(), 'title' => $deck->title])->render();

        try {
            $pdf = \Spatie\Browsershot\Browsershot::html($html)
                ->paperSize($size->width(), $size->height(), 'px')
                ->margins(0, 0, 0, 0)
                ->showBackground()
                ->waitUntilNetworkIdle()
                ->timeout((int) config('slides.pdf.timeout', 60))
                ->pdf();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->to($printUrl);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . Str::slug($deck->title ?: 'praesentation') . '.pdf"',
        ]);
    }
}
