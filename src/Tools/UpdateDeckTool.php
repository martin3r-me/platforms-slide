<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Tools\Concerns\HandlesDecks;

class UpdateDeckTool implements ToolContract, ToolMetadataContract
{
    use HandlesDecks;

    public function getName(): string
    {
        return 'slides.decks.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /slides/decks/{id} - Ändert ein Deck: title, description, size, status (draft|ready|archived), theme_css (null = Standard-Theme), fonts, owner_user_id. Pflicht: deck_id. include_theme=true liefert das aktuelle theme_css zurück.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deck_id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'size' => ['type' => 'string', 'enum' => \Platform\Slides\Enums\DeckSize::values()],
                'status' => ['type' => 'string', 'enum' => \Platform\Slides\Enums\DeckStatus::values()],
                'theme_css' => ['type' => ['string', 'null']],
                'fonts' => ['type' => 'array', 'items' => ['type' => 'string']],
                'owner_user_id' => ['type' => 'integer'],
                'include_theme' => ['type' => 'boolean'],
            ],
            'required' => ['deck_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['deck' => $deck, 'error' => $error] = $this->findDeck($arguments, $context, 'update');
            if ($error) {
                return $error;
            }
            $data = array_intersect_key($arguments, array_flip(['title', 'description', 'size', 'status', 'theme_css', 'fonts', 'owner_user_id']));
            ['deck' => $deck, 'removed' => $removed] = $this->service()->updateDeck($deck, $data, $context->user);

            return ToolResult::success([
                'deck' => $this->deckToArray($deck, (bool) ($arguments['include_theme'] ?? false)),
                'removed' => $removed,
                'message' => "Deck #{$deck->id} aktualisiert (Revision {$deck->revision}).",
            ]);
        } catch (\Throwable $e) {
            return $this->failure($e, 'Aktualisieren');
        }
    }

    public function getMetadata(): array
    {
        return $this->meta('PUT', ['decks', 'update']);
    }
}
