<?php

namespace Platform\Slides\Livewire\PublicView;

use Livewire\Component;
use Platform\Slides\Models\SlidesPresentation;

class View extends Component
{
    public SlidesPresentation $presentation;

    public function mount(string $token)
    {
        $this->presentation = SlidesPresentation::where('public_token', $token)
            ->where('is_published', true)
            ->firstOrFail();
    }

    public function render()
    {
        $slides = $this->presentation->slides()
            ->where('is_hidden', false)
            ->orderBy('sort_order')
            ->get();

        return view('slides::livewire.public.view', [
            'slides' => $slides,
        ])->layout('platform::layouts.guest');
    }
}
