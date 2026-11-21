<?php

namespace Platform\Slides\Services;

use Illuminate\Support\Facades\DB;
use Platform\Core\Models\User;
use Platform\Slides\Enums\DeckSize;
use Platform\Slides\Enums\DeckStatus;
use Platform\Slides\Models\SlidesDeck;
use Platform\Slides\Models\SlidesDeckLink;
use Platform\Slides\Models\SlidesDeckSlide;
use Platform\Slides\Rendering\SlideDocument;
use Platform\Slides\Security\CssSanitizer;
use Platform\Slides\Security\HtmlSanitizer;
use Platform\Slides\Themes\BhgTheme;

/**
 * Use-Cases rund um Decks: anlegen/ändern, Folien per slide_key upserten (Batch), Reihenfolge, Löschen,
 * Duplizieren, lose Links. Jede Änderung erhöht die revision des Decks (Live-Aktualisierung).
 * Alles HTML/CSS wird beim Speichern bereinigt; die Hinweise (removed) gehen an den Aufrufer zurück.
 */
class DeckService
{
    public const KEY_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

    public const MAX_SLIDES_PER_CALL = 40;

    public function __construct(private HtmlSanitizer $sanitizer = new HtmlSanitizer())
    {
    }

    /** @return array{deck:SlidesDeck, removed:string[]} */
    public function createDeck(User $user, int $teamId, array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title ist erforderlich.');
        }
        $attrs = $this->deckAttributes($data, true);
        $removed = $attrs['_removed'];
        unset($attrs['_removed']);

        $deck = SlidesDeck::create(array_merge([
            'team_id' => $teamId,
            'title' => $title,
            'size' => DeckSize::WIDE,
            'theme_css' => BhgTheme::css(),
            'fonts' => BhgTheme::fonts(),
            'status' => DeckStatus::DRAFT,
            'owner_user_id' => $user->id,
            'created_by' => $user->id,
        ], $attrs));

        return ['deck' => $deck->fresh(), 'removed' => $removed];
    }

    /** @return array{deck:SlidesDeck, removed:string[]} */
    public function updateDeck(SlidesDeck $deck, array $data, ?User $by = null): array
    {
        if (array_key_exists('title', $data) && trim((string) $data['title']) === '') {
            throw new \InvalidArgumentException('title darf nicht leer sein.');
        }
        $attrs = $this->deckAttributes($data, false);
        $removed = $attrs['_removed'];
        unset($attrs['_removed']);
        if (array_key_exists('title', $data)) {
            $attrs['title'] = trim((string) $data['title']);
        }
        if ($attrs !== []) {
            $deck->update($attrs);
            $deck->bumpRevision($by?->id);
        }

        return ['deck' => $deck->fresh(), 'removed' => $removed];
    }

    /**
     * Folien anlegen oder ändern, adressiert über key. Fehlende Felder bleiben bei bestehenden Folien unverändert.
     * position (0-basiert) setzt die Stelle; neue Folien ohne position kommen ans Ende.
     *
     * @param array<int,array{key:string, html?:string, notes?:?string, position?:int, is_hidden?:bool}> $items
     * @return array{slides:array<int,array{key:string, created:bool, removed:string[]}>, revision:int}
     */
    public function upsertSlides(SlidesDeck $deck, array $items, ?User $by = null): array
    {
        if ($items === []) {
            throw new \InvalidArgumentException('slides darf nicht leer sein.');
        }
        if (count($items) > self::MAX_SLIDES_PER_CALL) {
            throw new \InvalidArgumentException('Höchstens ' . self::MAX_SLIDES_PER_CALL . ' Folien pro Aufruf.');
        }

        $report = [];
        DB::transaction(function () use ($deck, $items, &$report) {
            $existing = SlidesDeckSlide::query()->where('deck_id', $deck->id)->get()->keyBy('slide_key');
            $moves = [];

            foreach ($items as $i => $item) {
                $key = (string) ($item['key'] ?? '');
                if (! preg_match(self::KEY_PATTERN, $key)) {
                    throw new \InvalidArgumentException("slides[{$i}].key ungültig (erlaubt: A-Z a-z 0-9 _ -, 1–64 Zeichen).");
                }
                $slide = $existing->get($key);
                if (! $slide && ! array_key_exists('html', $item)) {
                    throw new \InvalidArgumentException("slides[{$i}]: neue Folie {$key} braucht html.");
                }

                $fields = [];
                $removed = [];
                if (array_key_exists('html', $item)) {
                    $result = $this->sanitizer->sanitizeSlide((string) $item['html'], $key);
                    $fields['html'] = $result['html'];
                    $removed = $result['removed'];
                    if ($result['notes'] !== null && ! array_key_exists('notes', $item)) {
                        $fields['notes'] = $result['notes'];
                    }
                }
                if (array_key_exists('notes', $item)) {
                    $fields['notes'] = $item['notes'] === null ? null : mb_substr(trim((string) $item['notes']), 0, 8000);
                }
                if (array_key_exists('is_hidden', $item)) {
                    $fields['is_hidden'] = (bool) $item['is_hidden'];
                }

                if ($slide) {
                    $slide->update($fields);
                    $created = false;
                } else {
                    $slide = SlidesDeckSlide::create(array_merge($fields, [
                        'deck_id' => $deck->id,
                        'slide_key' => $key,
                        'position' => PHP_INT_MAX >> 33, // ans Ende, wird unten normalisiert
                    ]));
                    $existing->put($key, $slide);
                    $created = true;
                }
                if (array_key_exists('position', $item) && $item['position'] !== null) {
                    $moves[$key] = max(0, (int) $item['position']);
                }
                $report[] = ['key' => $key, 'created' => $created, 'removed' => $removed];
            }

            $this->normalizePositions($deck, $moves);
        });

        $deck->bumpRevision($by?->id);

        return ['slides' => $report, 'revision' => $deck->revision];
    }

    /**
     * Reihenfolge setzen: übergebene Keys in dieser Reihenfolge zuerst, übrige Folien behalten ihre relative Ordnung dahinter.
     *
     * @param string[] $keys
     */
    public function orderSlides(SlidesDeck $deck, array $keys, ?User $by = null): int
    {
        $slides = SlidesDeckSlide::query()->where('deck_id', $deck->id)->orderBy('position')->orderBy('id')->get()->keyBy('slide_key');
        $unknown = array_diff($keys, $slides->keys()->all());
        if ($unknown !== []) {
            throw new \InvalidArgumentException('Unbekannte Folien: ' . implode(', ', $unknown) . '.');
        }
        $ordered = array_values(array_unique($keys));
        foreach ($slides->keys() as $k) {
            if (! in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        DB::transaction(function () use ($ordered, $slides) {
            foreach ($ordered as $pos => $k) {
                if ($slides[$k]->position !== $pos) {
                    $slides[$k]->update(['position' => $pos]);
                }
            }
        });
        $deck->bumpRevision($by?->id);

        return $deck->revision;
    }

    /** @param string[] $keys @return string[] gelöschte Keys */
    public function deleteSlides(SlidesDeck $deck, array $keys, ?User $by = null): array
    {
        $slides = SlidesDeckSlide::query()->where('deck_id', $deck->id)->whereIn('slide_key', $keys)->get();
        foreach ($slides as $s) {
            $s->delete();
        }
        $this->normalizePositions($deck, []);
        $deck->bumpRevision($by?->id);

        return $slides->pluck('slide_key')->all();
    }

    public function duplicateDeck(SlidesDeck $deck, User $user, ?string $title = null): SlidesDeck
    {
        return DB::transaction(function () use ($deck, $user, $title) {
            $copy = SlidesDeck::create([
                'team_id' => $deck->team_id,
                'title' => $title ?: $deck->title . ' (Kopie)',
                'description' => $deck->description,
                'size' => $deck->size,
                'theme_css' => $deck->theme_css,
                'fonts' => $deck->fonts,
                'status' => DeckStatus::DRAFT,
                'owner_user_id' => $user->id,
                'created_by' => $user->id,
            ]);
            foreach ($deck->slides()->get() as $s) {
                SlidesDeckSlide::create([
                    'deck_id' => $copy->id,
                    'slide_key' => $s->slide_key,
                    'position' => $s->position,
                    'html' => $s->html,
                    'notes' => $s->notes,
                    'is_hidden' => $s->is_hidden,
                ]);
            }

            return $copy->fresh();
        });
    }

    public function link(SlidesDeck $deck, string $linkableType, int $linkableId, ?string $role, ?User $user): SlidesDeckLink
    {
        $linkableType = trim($linkableType);
        if (! preg_match('/^[a-z0-9_.\\\\-]{2,100}$/i', $linkableType) || $linkableId <= 0) {
            throw new \InvalidArgumentException('linkable_type (Morph-Alias) und linkable_id sind erforderlich.');
        }
        $link = SlidesDeckLink::firstOrNew(['deck_id' => $deck->id, 'linkable_type' => $linkableType, 'linkable_id' => $linkableId]);
        $link->role = $role !== null ? mb_substr(trim($role), 0, 50) : $link->role;
        $link->created_by ??= $user?->id;
        $link->save();

        return $link;
    }

    public function unlink(SlidesDeck $deck, string $linkableType, int $linkableId): bool
    {
        return SlidesDeckLink::query()->where('deck_id', $deck->id)
            ->where('linkable_type', $linkableType)->where('linkable_id', $linkableId)->delete() > 0;
    }

    /** Gerendertes srcdoc einer Folie (für UI und Vorschau). */
    public function document(SlidesDeck $deck, SlidesDeckSlide $slide): string
    {
        return SlideDocument::render($deck, $slide);
    }

    /** Positionen lückenlos 0..n-1; $moves (key → Zielposition) zuerst einsortieren. */
    private function normalizePositions(SlidesDeck $deck, array $moves): void
    {
        $slides = SlidesDeckSlide::query()->where('deck_id', $deck->id)->orderBy('position')->orderBy('id')->get();
        $order = $slides->reject(fn ($s) => array_key_exists($s->slide_key, $moves))->values()->all();
        asort($moves);
        foreach ($moves as $key => $pos) {
            $slide = $slides->firstWhere('slide_key', $key);
            array_splice($order, min($pos, count($order)), 0, [$slide]);
        }
        foreach ($order as $pos => $slide) {
            if ((int) $slide->position !== $pos) {
                $slide->update(['position' => $pos]);
            }
        }
    }

    /** @return array<string,mixed> inkl. '_removed' */
    private function deckAttributes(array $data, bool $creating): array
    {
        $attrs = ['_removed' => []];
        if (array_key_exists('description', $data)) {
            $attrs['description'] = $data['description'] === null ? null : trim((string) $data['description']);
        }
        if (array_key_exists('size', $data) && $data['size'] !== null) {
            $size = DeckSize::tryFrom((string) $data['size']) ?? throw new \InvalidArgumentException('size muss 16:9 oder 4:3 sein.');
            $attrs['size'] = $size;
        }
        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $status = DeckStatus::tryFrom((string) $data['status']) ?? throw new \InvalidArgumentException('status muss einer von ' . implode(', ', DeckStatus::values()) . ' sein.');
            $attrs['status'] = $status;
        }
        if (array_key_exists('theme_css', $data)) {
            if ($data['theme_css'] === null || trim((string) $data['theme_css']) === '') {
                $attrs['theme_css'] = BhgTheme::css();
            } else {
                [$css, $removed] = CssSanitizer::sanitizeStylesheet((string) $data['theme_css']);
                $attrs['theme_css'] = $css;
                $attrs['_removed'] = array_map(fn ($r) => 'theme_css: ' . $r, $removed);
            }
        }
        if (array_key_exists('fonts', $data)) {
            $fonts = is_array($data['fonts']) ? $data['fonts'] : [];
            $clean = SlideDocument::fontFamilies($fonts);
            if (count($clean) !== count($fonts)) {
                $attrs['_removed'][] = 'fonts: ungültige oder überzählige Schriftnamen entfernt (max. 4, nur Familiennamen).';
            }
            $attrs['fonts'] = $clean;
        }
        if (array_key_exists('owner_user_id', $data) && $data['owner_user_id'] !== null) {
            if (! User::whereKey((int) $data['owner_user_id'])->exists()) {
                throw new \InvalidArgumentException('owner_user_id unbekannt.');
            }
            $attrs['owner_user_id'] = (int) $data['owner_user_id'];
        }

        return $attrs;
    }
}
