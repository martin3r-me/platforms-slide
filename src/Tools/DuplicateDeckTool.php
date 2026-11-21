<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Tools\Concerns\HandlesDecks;

class DuplicateDeckTool implements ToolContract, ToolMetadataContract
{
    use HandlesDecks;

    public function getName(): string
    {
        return 'slides.decks.duplicate.POST';
    }

    public function getDescription(): string
    {
        return 'POST /slides/decks/{id}/duplicate - Kopiert ein Deck samt Folien und Theme als neuen Entwurf (z. B. Vorlage für die nächste Sitzung). Pflicht: deck_id. Optional: title, linkable_type + linkable_id (+ role) für die Kopie.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deck_id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'linkable_type' => ['type' => 'string'],
                'linkable_id' => ['type' => 'integer'],
                'role' => ['type' => 'string'],
            ],
            'required' => ['deck_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['deck' => $deck, 'error' => $error] = $this->findDeck($arguments, $context, 'view');
            if ($error) {
                return $error;
            }
            $copy = $this->service()->duplicateDeck($deck, $context->user, $arguments['title'] ?? null);
            $link = null;
            if (! empty($arguments['linkable_type']) && ! empty($arguments['linkable_id'])) {
                $link = $this->linkToArray($this->service()->link($copy, (string) $arguments['linkable_type'], (int) $arguments['linkable_id'], $arguments['role'] ?? 'presentation', $context->user));
            }

            return ToolResult::success(['deck' => $this->deckToArray($copy), 'link' => $link, 'message' => "Deck #{$deck->id} kopiert als #{$copy->id}."]);
        } catch (\Throwable $e) {
            return $this->failure($e, 'Kopieren');
        }
    }

    public function getMetadata(): array
    {
        return $this->meta('POST', ['decks', 'duplicate']);
    }
}
