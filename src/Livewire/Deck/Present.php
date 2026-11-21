<?php

namespace Platform\Slides\Livewire\Deck;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Platform\Slides\Models\SlidesDeck;
use Platform\Slides\Rendering\SlideDocument;

/**
 * Präsentationsmodus (Minimal): eine Folie bildschirmfüllend, Pfeiltasten/Buttons zum Blättern,
 * Live-Aktualisierung über die revision. Referentenansicht, Fragmente und Editor folgen.
 */
class Present extends Component
{
    public SlidesDeck $deck;

    #[Url(as: 'slide')]
    public int $index = 0;

    public int $revision = 0;

    public function mount(SlidesDeck $deck): void
    {
        Gate::authorize('view', $deck);
        $this->deck = $deck;
        $this->revision = (int) $deck->revision;
    }

    public function next(): void
    {
        $this->index = min($this->index + 1, max(0, $this->visibleCount() - 1));
    }

    public function previous(): void
    {
        $this->index = max(0, $this->index - 1);
    }

    public function go(int $index): void
    {
        $this->index = max(0, min($index, max(0, $this->visibleCount() - 1)));
    }

    public function refreshIfChanged(): void
    {
        $current = (int) SlidesDeck::query()->whereKey($this->deck->id)->value('revision');
        if ($current === $this->revision) {
            $this->skipRender();

            return;
        }
        $this->deck->refresh();
        $this->revision = $current;
        $this->index = min($this->index, max(0, $this->visibleCount() - 1));
    }

    private function visibleCount(): int
    {
        return $this->deck->slides()->where('is_hidden', false)->count();
    }

    public function render()
    {
        $slides = $this->deck->slides()->where('is_hidden', false)->get()->values();
        $slide = $slides[$this->index] ?? $slides->first();

        return view('slides::livewire.deck.present', [
            'deck' => $this->deck,
            'slide' => $slide,
            'doc' => $slide ? SlideDocument::render($this->deck, $slide) : null,
            'count' => $slides->count(),
            'size' => $this->deck->size,
            'poll' => (int) config('slides.live_poll_seconds', 3),
        ])->layout('slides::layouts.bare', ['title' => $this->deck->title]);
    }
}
