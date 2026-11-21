<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Tools\Concerns\HandlesDecks;

class DeleteSlidesTool implements ToolContract, ToolMetadataContract
{
    use HandlesDecks;

    public function getName(): string
    {
        return 'slides.slides.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /slides/decks/{id}/slides - Löscht Folien (Soft-Delete). Pflicht: deck_id, keys[]. Zum Ausblenden ohne Löschen: slides.slides.PUT mit is_hidden=true.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['deck_id' => ['type' => 'integer'], 'keys' => ['type' => 'array', 'items' => ['type' => 'string']]],
            'required' => ['deck_id', 'keys'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['deck' => $deck, 'error' => $error] = $this->findDeck($arguments, $context, 'update');
            if ($error) {
                return $error;
            }
            $keys = is_array($arguments['keys'] ?? null) ? array_map('strval', $arguments['keys']) : [];
            if ($keys === []) {
                return ToolResult::error('keys ist erforderlich.', 'VALIDATION_ERROR');
            }
            $deleted = $this->service()->deleteSlides($deck, $keys, $context->user);

            return ToolResult::success([
                'deleted' => $deleted,
                'not_found' => array_values(array_diff($keys, $deleted)),
                'revision' => $deck->fresh()->revision,
                'message' => count($deleted) . ' Folie(n) gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return $this->failure($e, 'Löschen der Folien');
        }
    }

    public function getMetadata(): array
    {
        return $this->meta('DELETE', ['slides', 'delete']);
    }
}
