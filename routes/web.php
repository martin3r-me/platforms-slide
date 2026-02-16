<?php

use Platform\Slides\Livewire\Dashboard;
use Platform\Slides\Livewire\Folder\Show as FolderShow;
use Platform\Slides\Livewire\Presentation\Show as PresentationShow;
use Platform\Slides\Livewire\Presentation\Settings as PresentationSettings;
use Platform\Slides\Livewire\Presentation\SlideEditor;
use Platform\Slides\Livewire\Present\Fullscreen;
use Platform\Slides\Livewire\Present\PresenterView;

Route::get('/', Dashboard::class)->name('slides.dashboard');

Route::get('/folders/{slidesFolder}', FolderShow::class)
    ->name('slides.folders.show');

Route::get('/presentations/{slidesPresentation}', PresentationShow::class)
    ->name('slides.presentations.show');

Route::get('/presentations/{slidesPresentation}/settings', PresentationSettings::class)
    ->name('slides.presentations.settings');

Route::get('/presentations/{slidesPresentation}/slides/{slidesSlide}', SlideEditor::class)
    ->name('slides.presentations.slides.edit');

Route::get('/presentations/{slidesPresentation}/present', Fullscreen::class)
    ->name('slides.presentations.present');

Route::get('/presentations/{slidesPresentation}/presenter', PresenterView::class)
    ->name('slides.presentations.presenter');
