<?php

namespace Platform\Slides\Livewire\Deck;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Platform\Slides\Models\SlidesDeck;
use Platform\Slides\Rendering\SlideDocument;

/** Druckansicht: alle sichtbaren Folien als Seiten im Folienformat (Browser „Als PDF sichern“). Serverseitiger PDF-Export folgt. */
class PrintView extends Component
{
    public SlidesDeck $deck;

    public function mount(SlidesDeck $deck): void
    {
        Gate::authorize('view', $deck);
        $this->deck = $deck;
    }

    public function render()
    {
        $slides = $this->deck->slides()->where('is_hidden', false)->get();

        return view('slides::livewire.deck.print', [
            'deck' => $this->deck,
            'docs' => $slides->map(fn ($s) => SlideDocument::render($this->deck, $s)),
            'size' => $this->deck->size,
        ])->layout('slides::layouts.bare', ['title' => $this->deck->title]);
    }
}
