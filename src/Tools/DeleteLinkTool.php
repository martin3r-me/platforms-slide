<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Tools\Concerns\HandlesDecks;

class DeleteLinkTool implements ToolContract, ToolMetadataContract
{
    use HandlesDecks;

    public function getName(): string
    {
        return 'slides.links.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /slides/links - Löst die Verknüpfung eines Decks mit einem Objekt. Pflicht: deck_id, linkable_type, linkable_id. Das Deck selbst bleibt bestehen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deck_id' => ['type' => 'integer'],
                'linkable_type' => ['type' => 'string'],
                'linkable_id' => ['type' => 'integer'],
            ],
            'required' => ['deck_id', 'linkable_type', 'linkable_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['deck' => $deck, 'error' => $error] = $this->findDeck($arguments, $context, 'update');
            if ($error) {
                return $error;
            }
            $ok = $this->service()->unlink($deck, (string) ($arguments['linkable_type'] ?? ''), (int) ($arguments['linkable_id'] ?? 0));

            return ToolResult::success(['deleted' => $ok, 'message' => $ok ? 'Verknüpfung gelöst.' : 'Keine solche Verknüpfung.']);
        } catch (\Throwable $e) {
            return $this->failure($e, 'Lösen');
        }
    }

    public function getMetadata(): array
    {
        return $this->meta('DELETE', ['links', 'delete']);
    }
}
