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
 * Tool zum Abrufen eines einzelnen Decks mit allen Slides und Platzhaltern.
 */
class GetDeckTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'slides.deck.GET';
    }

    public function getDescription(): string
    {
        return 'GET /decks/{id} - Ruft ein einzelnes Deck mit allen Slides, Platzhaltern und Theme ab. REST-Parameter: deck_id (required, integer) - Deck-ID.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deck_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Decks (ERFORDERLICH). Nutze "slides.decks.GET" um Decks zu finden.',
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

            $deck = SlidesPresentation::with(['user', 'folder', 'slides' => function ($q) {
                $q->orderBy('sort_order');
            }])->find($arguments['deck_id']);

            if (!$deck) {
                return ToolResult::error('DECK_NOT_FOUND', 'Das Deck wurde nicht gefunden.');
            }

            try {
                Gate::forUser($context->user)->authorize('view', $deck);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Deck.');
            }

            $slides = $deck->slides->map(function ($slide) {
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

                return $data;
            })->toArray();

            return ToolResult::success([
                'id' => $deck->id,
                'uuid' => $deck->uuid,
                'name' => $deck->name,
                'description' => $deck->description,
                'folder_id' => $deck->folder_id,
                'folder_name' => $deck->folder?->name,
                'theme' => $deck->theme,
                'slide_width' => $deck->slide_width,
                'slide_height' => $deck->slide_height,
                'is_published' => $deck->is_published,
                'public_token' => $deck->public_token,
                'owner_user_id' => $deck->user_id,
                'owner_name' => $deck->user?->name ?? 'Unbekannt',
                'team_id' => $deck->team_id,
                'slides' => $slides,
                'slide_count' => count($slides),
                'created_at' => $deck->created_at->toIso8601String(),
                'updated_at' => $deck->updated_at->toIso8601String(),
                'message' => "Deck '{$deck->name}' mit " . count($slides) . " Slide(s) geladen.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Decks: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['slides', 'deck', 'get', 'detail'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
