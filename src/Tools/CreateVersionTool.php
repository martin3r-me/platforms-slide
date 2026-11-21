<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Services\VersionService;
use Platform\Slides\Tools\Concerns\HandlesDecks;

class CreateVersionTool implements ToolContract, ToolMetadataContract
{
    use HandlesDecks;

    public function getName(): string
    {
        return 'slides.versions.POST';
    }

    public function getDescription(): string
    {
        return 'POST /slides/decks/{id}/versions - Hält den aktuellen Stand eines Decks als benannte Version fest (bleibt dauerhaft, wird nicht ausgedünnt). Vor größeren Umbauten aufrufen, z. B. label "Vor Überarbeitung Kapitel 2". Pflicht: deck_id. Optional: label.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deck_id' => ['type' => 'integer'],
                'label' => ['type' => 'string'],
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
            $v = app(VersionService::class)->create($deck, $arguments['label'] ?? null, $context->user);

            return ToolResult::success(['version' => $this->versionToArray($v), 'message' => "Version #{$v->id} festgehalten ({$v->slide_count} Folien)."]);
        } catch (\Throwable $e) {
            return $this->failure($e, 'Festhalten der Version');
        }
    }

    public function getMetadata(): array
    {
        return $this->meta('POST', ['versions', 'snapshot']);
    }
}
