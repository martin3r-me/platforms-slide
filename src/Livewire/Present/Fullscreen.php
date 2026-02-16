<?php

namespace Platform\Slides\Livewire\Present;

use Livewire\Component;
use Platform\Slides\Models\SlidesPresentation;

class Fullscreen extends Component
{
    public SlidesPresentation $presentation;

    public function mount(SlidesPresentation $slidesPresentation)
    {
        $this->presentation = $slidesPresentation;
        $this->authorize('view', $this->presentation);
    }

    public function render()
    {
        $slides = $this->presentation->slides()
            ->where('is_hidden', false)
            ->orderBy('sort_order')
            ->get();

        return view('slides::livewire.present.fullscreen', [
            'slides' => $slides,
        ])->layout('platform::layouts.guest');
    }
}
