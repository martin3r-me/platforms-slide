<?php

namespace Platform\Slides\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Slides\Enums\DeckStatus;
use Platform\Slides\Models\SlidesDeck;

/** Modul-Sidebar: Einstieg und die zuletzt bearbeiteten Decks des Teams. */
class Sidebar extends Component
{
    public function render()
    {
        $decks = SlidesDeck::query()->where('team_id', Auth::user()?->currentTeam?->id)
            ->where('status', '!=', DeckStatus::ARCHIVED->value)
            ->orderByDesc('updated_at')->limit(12)->get(['id', 'title', 'status']);

        return view('slides::livewire.sidebar', ['decks' => $decks]);
    }
}
