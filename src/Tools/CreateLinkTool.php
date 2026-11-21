<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Tools\Concerns\HandlesDecks;

class CreateLinkTool implements ToolContract, ToolMetadataContract
{
    use HandlesDecks;

    public function getName(): string
    {
        return 'slides.links.POST';
    }

    public function getDescription(): string
    {
        return 'POST /slides/links - Hängt ein Deck lose an ein Objekt (Morph-Alias + ID), z. B. management_book_committee, management_book_meeting, planner_project. Pflicht: deck_id, linkable_type, linkable_id. Optional: role (z. B. presentation, pre-read). Idempotent.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deck_id' => ['type' => 'integer'],
                'linkable_type' => ['type' => 'string'],
                'linkable_id' => ['type' => 'integer'],
                'role' => ['type' => 'string'],
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
            $link = $this->service()->link($deck, (string) ($arguments['linkable_type'] ?? ''), (int) ($arguments['linkable_id'] ?? 0), $arguments['role'] ?? null, $context->user);

            return ToolResult::success(['link' => $this->linkToArray($link), 'message' => "Deck #{$deck->id} verknüpft mit {$link->linkable_type} #{$link->linkable_id}."]);
        } catch (\Throwable $e) {
            return $this->failure($e, 'Verknüpfen');
        }
    }

    public function getMetadata(): array
    {
        return $this->meta('POST', ['links', 'create']);
    }
}
