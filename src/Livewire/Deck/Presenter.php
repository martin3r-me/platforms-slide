<?php

namespace Platform\Slides\Livewire\Deck;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Platform\Slides\Livewire\Concerns\StreamsDeck;
use Platform\Slides\Models\SlidesDeck;

/**
 * Referentenansicht: aktuelle und nächste Folie, Notizen groß, Uhr und Timer.
 * Steuert ein offenes Präsentationsfenster (gleicher Browser) synchron über BroadcastChannel – in beide Richtungen.
 */
class Presenter extends Component
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
        return view('slides::livewire.deck.presenter', [
            'deck' => $this->deck,
            'config' => $this->playerConfig('presenter', (int) request()->query('slide', 1) - 1),
            'poll' => (int) config('slides.live_poll_seconds', 3),
        ])->layout('slides::layouts.bare', ['title' => 'Referentenansicht · ' . $this->deck->title]);
    }
}
