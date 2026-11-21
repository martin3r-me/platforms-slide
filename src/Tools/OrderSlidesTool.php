<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Tools\Concerns\HandlesDecks;

class OrderSlidesTool implements ToolContract, ToolMetadataContract
{
    use HandlesDecks;

    public function getName(): string
    {
        return 'slides.slides.order.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /slides/decks/{id}/slides/order - Setzt die Reihenfolge der Folien. Pflicht: deck_id, keys[] in gewünschter Reihenfolge; nicht genannte Folien folgen dahinter in bisheriger Ordnung.';
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
            $revision = $this->service()->orderSlides($deck, $keys);

            return ToolResult::success([
                'order' => $deck->slides()->pluck('slide_key')->all(),
                'revision' => $revision,
                'message' => 'Reihenfolge gesetzt.',
            ]);
        } catch (\Throwable $e) {
            return $this->failure($e, 'Sortieren');
        }
    }

    public function getMetadata(): array
    {
        return $this->meta('PUT', ['slides', 'order']);
    }
}
