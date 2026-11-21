<?php

/**
 * Slides-Routen (Prefix /slides über ModuleRouter).
 *
 * slides.dashboard      – Übersicht der Decks
 * slides.decks.show     – Deck-Ansicht (alle Folien skaliert)
 * slides.decks.present  – Präsentationsmodus (eine Folie, blättern, Live-Aktualisierung)
 * slides.decks.presenter – Referentenansicht (aktuelle + nächste Folie, Notizen, Timer; steuert die Präsentation)
 * slides.decks.pdf      – Druckansicht (eine Folie pro Seite im Folienformat; ?print=1 öffnet den Druckdialog)
 * slides.decks.pdf.download – serverseitiges PDF (nur mit SLIDES_PDF_SERVER=true, sonst Weiterleitung auf die Druckansicht)
 */

use Platform\Slides\Livewire\Dashboard;
use Platform\Slides\Http\Controllers\DeckPdfController;
use Platform\Slides\Livewire\Deck\Present;
use Platform\Slides\Livewire\Deck\Presenter;
use Platform\Slides\Livewire\Deck\PrintView;
use Platform\Slides\Livewire\Deck\Show;

Route::get('/', Dashboard::class)->name('slides.dashboard');
Route::get('/decks/{deck}', Show::class)->name('slides.decks.show');
Route::get('/decks/{deck}/present', Present::class)->name('slides.decks.present');
Route::get('/decks/{deck}/presenter', Presenter::class)->name('slides.decks.presenter');
Route::get('/decks/{deck}/pdf', PrintView::class)->name('slides.decks.pdf');
Route::get('/decks/{deck}/pdf/download', DeckPdfController::class)->name('slides.decks.pdf.download');
