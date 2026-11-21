<?php

namespace Platform\Slides\Livewire\Concerns;

use Platform\Slides\Models\SlidesDeck;
use Platform\Slides\Rendering\SlideDocument;

/**
 * Für Präsentation und Referentenansicht: Die Folien gehen einmal als JSON an den Browser,
 * die Navigation läuft dort (ohne Server-Roundtrip, ohne Neuladen der Folien).
 * Live-Aktualisierung: wire:poll ruft refreshIfChanged(); bei neuer revision geht ein Browser-Event
 * „slides-updated“ mit allen Folien (inkl. Hash) raus – der Browser setzt nur geänderte Folien neu.
 * Die Komponente selbst rendert dabei nie neu (skipRender), damit Position und Klickschritte bleiben.
 */
trait StreamsDeck
{
    public SlidesDeck $deck;

    public int $revision = 0;

    /** @return array<int,array{id:int,key:string,hash:string,doc:string,notes:?string}> */
    protected function slidePayload(): array
    {
        return $this->deck->slides()->where('is_hidden', false)->get()
            ->map(function ($s) {
                $doc = SlideDocument::render($this->deck, $s, false);

                return ['id' => $s->id, 'key' => $s->slide_key, 'hash' => md5($doc), 'doc' => $doc, 'notes' => $s->notes];
            })->values()->all();
    }

    /** @return array{name:?string, at:?string} */
    protected function editorInfo(): array
    {
        $this->deck->loadMissing('lastEditor:id,name');

        return [
            'name' => $this->deck->lastEditor?->name,
            'at' => ($this->deck->last_edited_at ?? $this->deck->updated_at)?->toIso8601String(),
        ];
    }

    public function refreshIfChanged(): void
    {
        $this->skipRender();
        $current = (int) SlidesDeck::query()->whereKey($this->deck->id)->value('revision');
        if ($current === $this->revision) {
            return;
        }
        $this->deck->refresh();
        $this->revision = $current;
        $this->dispatch('slides-updated', slides: $this->slidePayload(), editor: $this->editorInfo(), revision: $current, title: $this->deck->title);
    }

    protected function playerConfig(string $mode, int $startIndex): array
    {
        $size = $this->deck->size;

        return [
            'mode' => $mode,
            'channel' => 'slides-deck-' . $this->deck->uuid,
            'slides' => $this->slidePayload(),
            'index' => max(0, $startIndex),
            'w' => $size->width(),
            'h' => $size->height(),
            'editor' => $this->editorInfo(),
            'presentUrl' => route('slides.decks.present', $this->deck),
            'presenterUrl' => route('slides.decks.presenter', $this->deck),
            'backUrl' => route('slides.decks.show', $this->deck),
        ];
    }
}
