<?php

namespace Platform\Slides\Livewire\Deck;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Url;
use Livewire\Component;
use Platform\Slides\Enums\DeckStatus;
use Platform\Slides\Models\SlidesDeck;
use Platform\Slides\Models\SlidesDeckLink;
use Platform\Slides\Rendering\SlideDocument;
use Platform\Slides\Services\DeckService;

/**
 * Deck-Ansicht: große ausgewählte Folie + Filmstreifen, Notizen, Eckdaten, verknüpfte Objekte, Aktionen.
 * Live: wire:poll prüft die revision; Folien sind per Inhalts-Hash verschlüsselt (wire:key) –
 * unveränderte Folien bleiben im DOM stehen, nur geänderte iframes werden ersetzt (kein Flackern).
 */
class Show extends Component
{
    public SlidesDeck $deck;

    public int $revision = 0;

    #[Url(as: 'slide')]
    public ?string $selected = null;

    /** Anzeige-Namen für bekannte Morph-Aliase verknüpfter Objekte (lose Kopplung, keine Abhängigkeit). */
    private const LINK_TYPES = [
        'management_book' => ['Management Book', 'management-book.books.show'],
        'management_book_committee' => ['Gremium', 'management-book.committees.show'],
        'management_book_meeting' => ['Sitzung', 'management-book.meetings.show'],
        'project' => ['Projekt', 'planner.projects.show'],
    ];

    public function mount(SlidesDeck $deck): void
    {
        Gate::authorize('view', $deck);
        $this->deck = $deck;
        $this->revision = (int) $deck->revision;
    }

    public function select(string $key): void
    {
        $this->selected = $key;
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
    }

    public function setStatus(string $status): void
    {
        Gate::authorize('update', $this->deck);
        if (! DeckStatus::tryFrom($status)) {
            return;
        }
        ['deck' => $deck] = app(DeckService::class)->updateDeck($this->deck, ['status' => $status], Auth::user());
        $this->deck = $deck;
        $this->revision = (int) $deck->revision;
    }

    public function duplicate()
    {
        Gate::authorize('view', $this->deck);
        Gate::authorize('create', SlidesDeck::class);
        $copy = app(DeckService::class)->duplicateDeck($this->deck, Auth::user());

        return $this->redirect(route('slides.decks.show', $copy), navigate: true);
    }

    /** @return array<int,array{type:string,label:string,title:string,url:?string,role:?string}> */
    private function linkRows(): array
    {
        return $this->deck->links()->get()->map(function (SlidesDeckLink $l) {
            [$label, $route] = self::LINK_TYPES[$l->linkable_type] ?? [$l->linkable_type, null];
            $title = '#' . $l->linkable_id;
            try {
                $class = Relation::getMorphedModel($l->linkable_type);
                if ($class && class_exists($class)) {
                    $obj = $class::query()->find($l->linkable_id);
                    if ($obj) {
                        $title = method_exists($obj, 'getDisplayName') ? ($obj->getDisplayName() ?: $title) : ($obj->title ?? $obj->name ?? $title);
                    }
                }
            } catch (\Throwable) {
                // Objekt nicht auflösbar – ID bleibt sichtbar
            }

            return [
                'type' => $l->linkable_type,
                'label' => $label,
                'title' => $title,
                'url' => $route && Route::has($route) ? route($route, $l->linkable_id) : null,
                'role' => $l->role,
            ];
        })->all();
    }

    public function render()
    {
        $slides = $this->deck->slides()->get();
        $docs = $slides->mapWithKeys(fn ($s) => [$s->slide_key => SlideDocument::render($this->deck, $s, true)]);
        $current = $slides->firstWhere('slide_key', $this->selected) ?? $slides->first();
        $this->deck->loadMissing('owner:id,name', 'lastEditor:id,name');

        return view('slides::livewire.deck.show', [
            'deck' => $this->deck,
            'slides' => $slides,
            'docs' => $docs,
            'hashes' => $docs->map(fn ($d) => substr(md5($d), 0, 12)),
            'current' => $current,
            'currentIndex' => $current ? $slides->search(fn ($s) => $s->id === $current->id) : null,
            'size' => $this->deck->size,
            'links' => $this->linkRows(),
            'statusOptions' => DeckStatus::cases(),
            'editedAt' => ($this->deck->last_edited_at ?? $this->deck->updated_at)?->toIso8601String(),
            'canEdit' => Gate::allows('update', $this->deck),
            'poll' => (int) config('slides.live_poll_seconds', 3),
        ])->layout('platform::layouts.app');
    }
}
