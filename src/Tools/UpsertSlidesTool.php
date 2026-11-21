<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Tools\Concerns\HandlesDecks;

class UpsertSlidesTool implements ToolContract, ToolMetadataContract
{
    use HandlesDecks;

    public function getName(): string
    {
        return 'slides.slides.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /slides/decks/{id}/slides - Schreibt mehrere Folien in einem Aufruf (anlegen oder ändern, adressiert über key). Pflicht: deck_id, slides[] mit {key, html?, notes?, position?, is_hidden?}. html = genau ein <section> (1920×1080, Regeln: slides.guide.GET); neue Folien brauchen html, bestehende können nur notes/position ändern. position 0-basiert; ohne position kommen neue Folien ans Ende. Die Antwort nennt je Folie, was der Sanitizer entfernt hat (removed). Max. 40 Folien pro Aufruf.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deck_id' => ['type' => 'integer'],
                'slides' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'key' => ['type' => 'string', 'description' => 'Stabiler Folienschlüssel, z. B. cover, agenda, kpis.'],
                            'html' => ['type' => 'string', 'description' => 'Genau ein <section>…</section>.'],
                            'notes' => ['type' => ['string', 'null'], 'description' => 'Sprechernotizen (Klartext).'],
                            'position' => ['type' => 'integer'],
                            'is_hidden' => ['type' => 'boolean'],
                        ],
                        'required' => ['key'],
                    ],
                ],
            ],
            'required' => ['deck_id', 'slides'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['deck' => $deck, 'error' => $error] = $this->findDeck($arguments, $context, 'update');
            if ($error) {
                return $error;
            }
            $items = is_array($arguments['slides'] ?? null) ? array_values($arguments['slides']) : [];
            $result = $this->service()->upsertSlides($deck, $items);
            $created = count(array_filter($result['slides'], fn ($s) => $s['created']));
            $withRemoved = array_values(array_filter($result['slides'], fn ($s) => $s['removed'] !== []));

            return ToolResult::success([
                'deck_id' => $deck->id,
                'revision' => $result['revision'],
                'slides' => $result['slides'],
                'slide_count' => $deck->slides()->count(),
                'message' => count($items) . ' Folie(n) gespeichert (' . $created . ' neu).'
                    . ($withRemoved ? ' Achtung: Bei ' . count($withRemoved) . ' Folie(n) hat der Sanitizer etwas entfernt – siehe removed.' : ''),
            ]);
        } catch (\Throwable $e) {
            return $this->failure($e, 'Speichern der Folien');
        }
    }

    public function getMetadata(): array
    {
        return $this->meta('PUT', ['slides', 'upsert', 'batch']);
    }
}
