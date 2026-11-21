<?php

namespace Platform\Slides\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Platform\Slides\Enums\DeckStatus;
use Platform\Slides\Models\SlidesDeck;
use Platform\Slides\Rendering\SlideDocument;
use Platform\Slides\Services\DeckService;

/** Übersicht der Decks des aktuellen Teams: Karten mit Vorschau der ersten Folie, Suche, Statusfilter. */
class Dashboard extends Component
{
    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'q')]
    public string $search = '';

    public bool $showCreate = false;

    public string $title = '';

    public function create()
    {
        Gate::authorize('create', SlidesDeck::class);
        $this->validate(['title' => 'required|string|max:255']);
        $user = Auth::user();
        ['deck' => $deck] = app(DeckService::class)->createDeck($user, (int) $user->currentTeam->id, ['title' => $this->title]);
        $this->reset('title', 'showCreate');

        return $this->redirect(route('slides.decks.show', $deck), navigate: true);
    }

    public function render()
    {
        $user = Auth::user();
        $base = SlidesDeck::query()->where('team_id', $user->currentTeam?->id);
        $counts = (clone $base)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        $decks = $base->withCount(['slides' => fn ($q) => $q->where('is_hidden', false)])
            ->with(['lastEditor:id,name', 'links'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%' . trim($this->search) . '%';
                $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->orderByDesc('updated_at')->limit(60)->get();

        // Vorschau: erste sichtbare Folie je Deck (statisch, alle Klickschritte sichtbar)
        $previews = [];
        foreach ($decks as $deck) {
            $first = $deck->slides()->where('is_hidden', false)->first();
            $previews[$deck->id] = $first ? SlideDocument::render($deck, $first, true) : null;
        }

        return view('slides::livewire.dashboard', [
            'decks' => $decks,
            'previews' => $previews,
            'counts' => $counts,
            'statusOptions' => DeckStatus::cases(),
            'canCreate' => Gate::allows('create', SlidesDeck::class),
        ])->layout('platform::layouts.app');
    }
}
