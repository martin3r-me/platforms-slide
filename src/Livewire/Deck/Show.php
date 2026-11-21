<?php

namespace Platform\Slides\Livewire\Deck;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Platform\Slides\Models\SlidesDeck;
use Platform\Slides\Rendering\SlideDocument;

/**
 * Deck-Ansicht: alle Folien skaliert (Minimal-UI). Live: wire:poll prüft die revision und rendert nur bei Änderung neu.
 */
class Show extends Component
{
    public SlidesDeck $deck;

    public int $revision = 0;

    public function mount(SlidesDeck $deck): void
    {
        Gate::authorize('view', $deck);
        $this->deck = $deck;
        $this->revision = (int) $deck->revision;
    }

    /** Für wire:poll: nur neu rendern, wenn sich die revision geändert hat. */
    public function refreshIfChanged(): void
    {
        $current = (int) SlidesDeck::query()->whereKey($this->deck->id)->value('revision');
        if ($current === $this->revision) {
            $this->skipRender();

            return;
        }
        $this->deck->refresh();
        $this->revision = $current;
    }

    public function render()
    {
        $slides = $this->deck->slides()->get();

        return view('slides::livewire.deck.show', [
            'deck' => $this->deck,
            'slides' => $slides,
            'docs' => $slides->mapWithKeys(fn ($s) => [$s->id => SlideDocument::render($this->deck, $s)]),
            'size' => $this->deck->size,
            'poll' => (int) config('slides.live_poll_seconds', 3),
        ])->layout('platform::layouts.app');
    }
}
