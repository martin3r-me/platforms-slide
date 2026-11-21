<?php

/**
 * Öffentliche Slides-Routen (ohne Login, Prefix /slides):
 *
 * slides.assets.show   – Folienbild über nicht erratbares Token (lädt in iframes, Präsentation, PDF)
 * slides.previews.show – Folien-Vorschau (JPEG) für Agenten, nur mit gültiger Signatur (befristet)
 */

use Platform\Slides\Http\Controllers\AssetController;
use Platform\Slides\Http\Controllers\PreviewImageController;

Route::get('/a/{file}', AssetController::class)->name('slides.assets.show');
Route::get('/p/{deck}/{file}', PreviewImageController::class)->middleware('signed')->name('slides.previews.show');
