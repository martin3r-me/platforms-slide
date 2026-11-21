<?php

namespace Platform\Slides\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Platform\Slides\Enums\DeckStatus;
use Platform\Slides\Models\SlidesDeck;
use Platform\Slides\Services\DeckService;

/** Übersicht der Decks des aktuellen Teams (Minimal-UI; Ausbau folgt). */
class Dashboard extends Component
{
    public string $statusFilter = '';

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
        $decks = SlidesDeck::query()->where('team_id', $user->currentTeam?->id)->withCount('slides')
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('updated_at')->get();

        return view('slides::livewire.dashboard', [
            'decks' => $decks,
            'statusOptions' => array_map(fn ($s) => ['value' => $s->value, 'label' => $s->label()], DeckStatus::cases()),
        ])->layout('platform::layouts.app');
    }
}
