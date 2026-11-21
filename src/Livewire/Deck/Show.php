<?php

namespace Platform\Slides\Livewire\Deck;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Slides\Enums\DeckStatus;
use Platform\Slides\Models\SlidesAsset;
use Platform\Slides\Models\SlidesDeck;
use Platform\Slides\Models\SlidesDeckLink;
use Platform\Slides\Models\SlidesDeckVersion;
use Platform\Slides\Rendering\Browser;
use Platform\Slides\Rendering\SlideDocument;
use Platform\Slides\Services\AssetService;
use Platform\Slides\Services\DeckService;
use Platform\Slides\Services\PreviewService;
use Platform\Slides\Services\VersionService;

/**
 * Deck-Ansicht: große ausgewählte Folie + Filmstreifen, Notizen, Eckdaten, verknüpfte Objekte, Aktionen.
 * Live: wire:poll prüft die revision; Folien sind per Inhalts-Hash verschlüsselt (wire:key) –
 * unveränderte Folien bleiben im DOM stehen, nur geänderte iframes werden ersetzt (kein Flackern).
 * Dazu: Versionen (festhalten, wiederherstellen), Layout-Prüfung im Headless-Chrome, Bilder hochladen.
 */
class Show extends Component
{
    use WithFileUploads;

    public SlidesDeck $deck;

    public string $versionLabel = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $upload = null;

    /** Ergebnis der Layout-Prüfung: slide_key → [issues, fill]; null = noch nicht geprüft. */
    public ?array $check = null;

    public ?int $checkRevision = null;

    /** true = im Browser gemessen; false = nur Inhaltsregeln (kein Headless-Browser). */
    public bool $checkMeasured = false;

    public ?string $notice = null;

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

    public function createVersion(): void
    {
        Gate::authorize('update', $this->deck);
        $v = app(VersionService::class)->create($this->deck, $this->versionLabel ?: null, Auth::user());
        $this->versionLabel = '';
        $this->notice = "Version #{$v->id} festgehalten.";
    }

    public function restoreVersion(int $id): void
    {
        Gate::authorize('update', $this->deck);
        $v = SlidesDeckVersion::query()->where('deck_id', $this->deck->id)->findOrFail($id);
        $backup = app(VersionService::class)->restore($this->deck, $v, Auth::user());
        $this->deck->refresh();
        $this->revision = (int) $this->deck->revision;
        $this->check = null;
        $this->notice = "Version #{$v->id} wiederhergestellt." . ($backup ? " Der vorherige Stand liegt in Version #{$backup->id}." : '');
    }

    public function updatedUpload(): void
    {
        Gate::authorize('update', $this->deck);
        $this->validate(['upload' => 'file|max:' . (int) config('slides.assets.max_kb', 10240)]);
        try {
            app(AssetService::class)->store((int) $this->deck->team_id, $this->deck, (string) file_get_contents($this->upload->getRealPath()), $this->upload->getClientOriginalName(), Auth::user());
            $this->notice = 'Bild hochgeladen. Agenten finden es mit slides.assets.GET.';
        } catch (\InvalidArgumentException $e) {
            $this->addError('upload', $e->getMessage());
        }
        $this->upload = null;
    }

    public function deleteAsset(int $id): void
    {
        Gate::authorize('update', $this->deck);
        $asset = SlidesAsset::query()->where('team_id', $this->deck->team_id)->findOrFail($id);
        $svc = app(AssetService::class);
        if ($svc->usages($asset) !== []) {
            $this->notice = 'Das Bild wird noch in Folien verwendet und bleibt erhalten.';

            return;
        }
        $svc->delete($asset);
    }

    /** Folien im Headless-Chrome messen (dauert einige Sekunden). */
    public function runCheck(): void
    {
        $svc = app(PreviewService::class);
        $slides = $this->deck->slides()->get();
        $layout = [];
        $this->notice = null;
        $this->checkMeasured = false;
        if (Browser::available()) {
            try {
                $layout = $svc->inspect($this->deck, $slides);
                $this->checkMeasured = true;
            } catch (\Throwable $e) {
                report($e);
                $this->notice = 'Layout-Messung nicht möglich (kein Headless-Browser) – nur Inhaltsprüfung.';
            }
        } else {
            $this->notice = 'Kein Headless-Browser verfügbar – nur Inhaltsprüfung.';
        }
        $this->check = $slides->mapWithKeys(fn ($s) => [$s->slide_key => [
            'issues' => $svc->merge($layout[$s->slide_key]['issues'] ?? [], $svc->lint($s)),
            'fill' => $layout[$s->slide_key]['fill'] ?? null,
        ]])->all();
        $this->checkRevision = (int) $this->deck->revision;
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
            'versions' => SlidesDeckVersion::query()->where('deck_id', $this->deck->id)->with('creator:id,name')->orderByDesc('id')->limit(8)->get(),
            'versionCount' => SlidesDeckVersion::query()->where('deck_id', $this->deck->id)->count(),
            'assets' => SlidesAsset::query()->where('team_id', $this->deck->team_id)
                ->where(fn ($q) => $q->where('deck_id', $this->deck->id)->orWhereNull('deck_id'))->orderByDesc('id')->limit(24)->get(),
            'check' => $this->check,
            'checkMeasured' => $this->checkMeasured,
            'notice' => $this->notice,
            'checkStale' => $this->check !== null && $this->checkRevision !== (int) $this->deck->revision,
            'poll' => (int) config('slides.live_poll_seconds', 3),
        ])->layout('platform::layouts.app');
    }
}
