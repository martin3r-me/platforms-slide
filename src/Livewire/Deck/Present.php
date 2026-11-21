<?php

namespace Platform\Slides\Livewire\Deck;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Platform\Slides\Livewire\Concerns\StreamsDeck;
use Platform\Slides\Models\SlidesDeck;

/**
 * Präsentationsmodus: bildschirmfüllend, Pfeile/Leertaste/Klick, Klickschritte (.fragment), Vollbild, Fortschritt.
 * ?slide=n (1-basiert) hält die Position in der URL. Synchron mit der Referentenansicht über BroadcastChannel.
 */
class Present extends Component
{
    use StreamsDeck;

    public function mount(SlidesDeck $deck): void
    {
        Gate::authorize('view', $deck);
        $this->deck = $deck;
        $this->revision = (int) $deck->revision;
    }

    public function render()
    {
        return view('slides::livewire.deck.present', [
            'deck' => $this->deck,
            'config' => $this->playerConfig('present', (int) request()->query('slide', 1) - 1),
            'poll' => (int) config('slides.live_poll_seconds', 3),
        ])->layout('slides::layouts.bare', ['title' => $this->deck->title]);
    }
}
