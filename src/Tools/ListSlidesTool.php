<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Slides\Models\SlidesPresentation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Tool zum Auflisten von Slides innerhalb eines Decks.
 */
class ListSlidesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'slides.slides.GET';
    }

    public function getDescription(): string
    {
        return 'GET /decks/{deck_id}/slides - Listet alle Slides eines Decks auf (sortiert nach sort_order). REST-Parameter: deck_id (required, integer) - Deck-ID. include_content (optional, boolean) - Ob vollständiger Content mitgeladen werden soll (Standard: false).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deck_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Decks (ERFORDERLICH).',
                ],
                'include_content' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Vollständigen Content laden. Standard: false (nur Platzhalter werden angezeigt).',
                ],
            ],
            'required' => ['deck_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            if (empty($arguments['deck_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'deck_id ist erforderlich.');
            }

            $deck = SlidesPresentation::find($arguments['deck_id']);
            if (!$deck) {
                return ToolResult::error('DECK_NOT_FOUND', 'Das Deck wurde nicht gefunden.');
            }

            try {
                Gate::forUser($context->user)->authorize('view', $deck);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Deck.');
            }

            $includeContent = $arguments['include_content'] ?? false;

            $slides = $deck->slides()->orderBy('sort_order')->get()->map(function ($slide) use ($includeContent) {
                $data = [
                    'id' => $slide->id,
                    'uuid' => $slide->uuid,
                    'sort_order' => $slide->sort_order,
                    'layout_key' => $slide->layout_key,
                    'placeholders' => $slide->getPlaceholders(),
                    'background' => $slide->background,
                    'transition' => $slide->transition,
                    'notes' => $slide->notes,
                    'is_hidden' => $slide->is_hidden,
                    'duration_seconds' => $slide->duration_seconds,
                ];

                if ($slide->layout_key === 'two-column') {
                    $data['col_ratio'] = $slide->content['col_ratio'] ?? '50:50';
                }

                if ($includeContent) {
                    $data['content'] = $slide->content;
                }

                return $data;
            })->toArray();

            return ToolResult::success([
                'deck_id' => $deck->id,
                'deck_name' => $deck->name,
                'slides' => $slides,
                'count' => count($slides),
                'message' => count($slides) . ' Slide(s) gefunden.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Slides: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['slides', 'slide', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
