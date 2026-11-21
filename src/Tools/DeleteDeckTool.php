<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Tools\Concerns\HandlesDecks;

class DeleteDeckTool implements ToolContract, ToolMetadataContract
{
    use HandlesDecks;

    public function getName(): string
    {
        return 'slides.decks.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /slides/decks/{id} - Löscht ein Deck (Soft-Delete, wiederherstellbar). Pflicht: deck_id. Zum Ablegen ohne Löschen: slides.decks.PUT status=archived.';
    }

    public function getSchema(): array
    {
        return ['type' => 'object', 'properties' => ['deck_id' => ['type' => 'integer']], 'required' => ['deck_id']];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['deck' => $deck, 'error' => $error] = $this->findDeck($arguments, $context, 'delete');
            if ($error) {
                return $error;
            }
            $deck->delete();

            return ToolResult::success(['id' => $deck->id, 'message' => "Deck '{$deck->title}' (#{$deck->id}) gelöscht (Soft-Delete)."]);
        } catch (\Throwable $e) {
            return $this->failure($e, 'Löschen');
        }
    }

    public function getMetadata(): array
    {
        return $this->meta('DELETE', ['decks', 'delete']);
    }
}
