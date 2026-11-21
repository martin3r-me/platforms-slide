<?php

/**
 * Slides-Routen (Prefix /slides über ModuleRouter).
 *
 * slides.dashboard      – Übersicht der Decks
 * slides.decks.show     – Deck-Ansicht (alle Folien skaliert)
 * slides.decks.present  – Präsentationsmodus (eine Folie, blättern, Live-Aktualisierung)
 * slides.decks.pdf      – Druckansicht (alle Folien im Seitenformat, für „Als PDF drucken“)
 */

use Platform\Slides\Livewire\Dashboard;
use Platform\Slides\Livewire\Deck\Present;
use Platform\Slides\Livewire\Deck\PrintView;
use Platform\Slides\Livewire\Deck\Show;

Route::get('/', Dashboard::class)->name('slides.dashboard');
Route::get('/decks/{deck}', Show::class)->name('slides.decks.show');
Route::get('/decks/{deck}/present', Present::class)->name('slides.decks.present');
Route::get('/decks/{deck}/pdf', PrintView::class)->name('slides.decks.pdf');
