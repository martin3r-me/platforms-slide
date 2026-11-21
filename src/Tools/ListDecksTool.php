<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Tools\Concerns\HandlesDecks;
use Platform\Slides\Models\SlidesDeck;
use Platform\Slides\Models\SlidesDeckLink;

class ListDecksTool implements ToolContract, ToolMetadataContract
{
    use HandlesDecks;

    public function getName(): string
    {
        return 'slides.decks.GET';
    }

    public function getDescription(): string
    {
        return 'GET /slides/decks - Listet Präsentationen (Decks) des Teams. Einzelnes Deck: id (liefert zusätzlich die Folienübersicht ohne HTML). Filter: status (draft|ready|archived), search, linkable_type + linkable_id (Decks, die an ein Objekt gehängt sind, z. B. management_book_meeting #7), limit/offset.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'Optional: einzelnes Deck.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team (Default: aktuelles Team).'],
                'status' => ['type' => 'string', 'enum' => \Platform\Slides\Enums\DeckStatus::values()],
                'search' => ['type' => 'string', 'description' => 'Suche in Titel/Beschreibung.'],
                'linkable_type' => ['type' => 'string', 'description' => 'Optional: Morph-Alias des verknüpften Objekts.'],
                'linkable_id' => ['type' => 'integer', 'description' => 'Optional: ID des verknüpften Objekts.'],
                'limit' => ['type' => 'integer'],
                'offset' => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (! empty($arguments['id'])) {
                ['deck' => $deck, 'error' => $error] = $this->findDeck(['deck_id' => $arguments['id']], $context);
                if ($error) {
                    return $error;
                }

                return ToolResult::success([
                    'deck' => $this->deckToArray($deck),
                    'slides' => $deck->slides()->get()->map(fn ($s) => $this->slideToArray($s, false))->all(),
                    'links' => $deck->links()->get()->map(fn ($l) => $this->linkToArray($l))->all(),
                ]);
            }

            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            $query = SlidesDeck::query()->where('team_id', $team->id)->withCount('slides');
            if (! empty($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }
            if (! empty($arguments['search'])) {
                $term = '%' . $arguments['search'] . '%';
                $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('description', 'like', $term));
            }
            if (! empty($arguments['linkable_type']) && ! empty($arguments['linkable_id'])) {
                $query->whereIn('id', SlidesDeckLink::query()->where('linkable_type', $arguments['linkable_type'])
                    ->where('linkable_id', (int) $arguments['linkable_id'])->select('deck_id'));
            }
            $total = (clone $query)->count();
            $limit = min(max((int) ($arguments['limit'] ?? 50), 1), 200);
            $decks = $query->orderByDesc('updated_at')->skip(max((int) ($arguments['offset'] ?? 0), 0))->take($limit)->get();

            return ToolResult::success([
                'decks' => $decks->map(fn ($d) => $this->deckToArray($d))->all(),
                'total' => $total,
                'team_id' => $team->id,
            ]);
        } catch (\Throwable $e) {
            return $this->failure($e, 'Laden der Decks');
        }
    }

    public function getMetadata(): array
    {
        return $this->meta('GET', ['decks', 'list']);
    }
}
