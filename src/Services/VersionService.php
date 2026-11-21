<?php

namespace Platform\Slides\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Platform\Core\Models\User;
use Platform\Slides\Models\SlidesDeck;
use Platform\Slides\Models\SlidesDeckSlide;
use Platform\Slides\Models\SlidesDeckVersion;

/**
 * Versionen eines Decks. DeckService ruft checkpoint() vor jeder Änderung auf: Beginnt eine neue Änderungsrunde
 * (letzte Änderung länger als slides.versions.session_minutes her) oder ist der letzte Schnappschuss älter als
 * slides.versions.auto_minutes, wird der bisherige Stand gesichert. Unveränderte Stände werden nie doppelt gespeichert.
 * Wiederherstellen sichert vorher den aktuellen Stand – jede Wiederherstellung ist also selbst umkehrbar.
 */
class VersionService
{
    /** Stand vor einer Änderung sichern, falls nötig. */
    public function checkpoint(SlidesDeck $deck, ?User $by = null): ?SlidesDeckVersion
    {
        $last = $this->latest($deck);
        $session = (int) config('slides.versions.session_minutes', 3);
        $auto = (int) config('slides.versions.auto_minutes', 15);
        $editedAt = $deck->last_edited_at ?? $deck->updated_at;
        $newSession = ! $editedAt || $editedAt->lt(now()->subMinutes($session));
        $stale = ! $last || $last->created_at->lt(now()->subMinutes($auto));
        if (! $newSession && ! $stale) {
            return null;
        }

        return $this->snapshot($deck, SlidesDeckVersion::KIND_AUTO, null, $by, $last);
    }

    /** Stand bewusst festhalten (UI-Knopf, Agent vor einem Umbau). Gibt bei unverändertem Stand die vorhandene Version zurück und vergibt ggf. das Label. */
    public function create(SlidesDeck $deck, ?string $label, ?User $by = null): SlidesDeckVersion
    {
        $label = $label !== null ? (mb_substr(trim($label), 0, 200) ?: null) : null;
        $last = $this->latest($deck);
        $version = $this->snapshot($deck, SlidesDeckVersion::KIND_MANUAL, $label, $by, $last);
        if ($version) {
            return $version;
        }
        // Inhalt identisch mit der letzten Version: diese zur festgehaltenen machen statt zu duplizieren
        $last->update(['kind' => SlidesDeckVersion::KIND_MANUAL, 'label' => $label ?? $last->label]);

        return $last->fresh();
    }

    /** Deck auf eine Version zurücksetzen. Der aktuelle Stand wird vorher als before_restore gesichert. */
    public function restore(SlidesDeck $deck, SlidesDeckVersion $version, ?User $by = null): SlidesDeckVersion
    {
        if ((int) $version->deck_id !== (int) $deck->id) {
            throw new \InvalidArgumentException("Version #{$version->id} gehört nicht zu Deck #{$deck->id}.");
        }
        $snap = $version->snapshot ?? [];

        $backup = DB::transaction(function () use ($deck, $version, $snap, $by) {
            $backup = $this->snapshot($deck, SlidesDeckVersion::KIND_BEFORE_RESTORE, 'Vor Wiederherstellung von Version #' . $version->id, $by, $this->latest($deck))
                ?? $this->latest($deck);

            $meta = array_intersect_key($snap['deck'] ?? [], array_flip(['title', 'description', 'size', 'theme_css', 'fonts']));
            if ($meta !== []) {
                $deck->update($meta);
            }

            $current = SlidesDeckSlide::query()->where('deck_id', $deck->id)->get()->keyBy('slide_key');
            $keep = [];
            foreach ($snap['slides'] ?? [] as $s) {
                $fields = [
                    'position' => (int) $s['position'],
                    'html' => (string) $s['html'],
                    'notes' => $s['notes'] ?? null,
                    'is_hidden' => (bool) ($s['is_hidden'] ?? false),
                ];
                if ($slide = $current->get($s['key'])) {
                    $slide->update($fields);
                } else {
                    SlidesDeckSlide::create($fields + ['deck_id' => $deck->id, 'slide_key' => $s['key']]);
                }
                $keep[] = $s['key'];
            }
            foreach ($current as $key => $slide) {
                if (! in_array($key, $keep, true)) {
                    $slide->delete();
                }
            }

            return $backup;
        });

        $deck->bumpRevision($by?->id);

        return $backup;
    }

    /** Unterschied einer Version zum aktuellen Stand: Folien hinzugekommen/entfernt/geändert seit dieser Version. */
    public function diffToCurrent(SlidesDeck $deck, SlidesDeckVersion $version): array
    {
        $then = collect($version->snapshot['slides'] ?? [])->keyBy('key');
        $now = collect($this->state($deck)['slides'])->keyBy('key');
        $changed = $now->filter(fn ($s, $k) => $then->has($k) && $this->slideHash($s) !== $this->slideHash($then[$k]))->keys()->values()->all();
        $order = $then->keys()->values()->all() !== $now->keys()->values()->all();

        return [
            'added' => $now->keys()->diff($then->keys())->values()->all(),
            'removed' => $then->keys()->diff($now->keys())->values()->all(),
            'changed' => $changed,
            'reordered' => $order,
            'deck_changed' => $this->deckHash($version->snapshot['deck'] ?? []) !== $this->deckHash($this->state($deck)['deck']),
        ];
    }

    public function latest(SlidesDeck $deck): ?SlidesDeckVersion
    {
        return SlidesDeckVersion::query()->where('deck_id', $deck->id)->orderByDesc('id')->first();
    }

    /** Aktueller Stand als Schnappschuss-Array. */
    public function state(SlidesDeck $deck): array
    {
        return [
            'deck' => [
                'title' => $deck->title,
                'description' => $deck->description,
                'size' => $deck->size?->value ?? $deck->size,
                'theme_css' => $deck->theme_css,
                'fonts' => $deck->fonts ?? [],
            ],
            'slides' => SlidesDeckSlide::query()->where('deck_id', $deck->id)->orderBy('position')->orderBy('id')->get()
                ->map(fn ($s) => [
                    'key' => $s->slide_key,
                    'position' => (int) $s->position,
                    'html' => $s->html,
                    'notes' => $s->notes,
                    'is_hidden' => (bool) $s->is_hidden,
                ])->values()->all(),
        ];
    }

    private function snapshot(SlidesDeck $deck, string $kind, ?string $label, ?User $by, ?SlidesDeckVersion $last): ?SlidesDeckVersion
    {
        $state = $this->state($deck);
        // Ein leeres, neues Deck braucht keine automatische Version
        if ($kind === SlidesDeckVersion::KIND_AUTO && $state['slides'] === [] && ! $last) {
            return null;
        }
        $hash = sha1(json_encode($state));
        if ($last && $last->content_hash === $hash) {
            return null;
        }

        $version = SlidesDeckVersion::create([
            'deck_id' => $deck->id,
            'revision' => (int) $deck->revision,
            'kind' => $kind,
            'label' => $label,
            'slide_count' => count($state['slides']),
            'content_hash' => $hash,
            'snapshot' => $state,
            'created_by' => $by?->id ?? Auth::id(),
        ]);
        $this->prune($deck);

        return $version;
    }

    /** Nur automatische Versionen ausdünnen; festgehaltene bleiben. */
    private function prune(SlidesDeck $deck): void
    {
        $keep = max(5, (int) config('slides.versions.keep_auto', 50));
        $ids = SlidesDeckVersion::query()->where('deck_id', $deck->id)->where('kind', '!=', SlidesDeckVersion::KIND_MANUAL)
            ->orderByDesc('id')->skip($keep)->take(1000)->pluck('id');
        if ($ids->isNotEmpty()) {
            SlidesDeckVersion::query()->whereIn('id', $ids)->delete();
        }
    }

    private function slideHash(array $s): string
    {
        return md5(($s['html'] ?? '') . "\0" . ($s['notes'] ?? '') . "\0" . (int) ($s['is_hidden'] ?? false));
    }

    private function deckHash(array $d): string
    {
        return md5(json_encode([$d['title'] ?? null, $d['description'] ?? null, $d['size'] ?? null, $d['theme_css'] ?? null, $d['fonts'] ?? []]));
    }
}
