<?php

use Platform\Slides\Livewire\PublicView\View;

Route::get('/public/{token}', View::class)
    ->name('slides.public.view');
