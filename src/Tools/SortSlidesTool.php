<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Slides\Models\SlidesPresentation;
use Platform\Slides\Models\SlidesSlide;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Tool zum Sortieren/Neuordnen von Slides innerhalb eines Decks.
 */
class SortSlidesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'slides.slides.SORT';
    }

    public function getDescription(): string
    {
        return 'PUT /decks/{deck_id}/slides/sort - Sortiert Slides innerhalb eines Decks neu. REST-Parameter: deck_id (required, integer). slide_ids (required, array of integers) - Neue Reihenfolge der Slide-IDs.';
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
                'slide_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Neue Reihenfolge der Slide-IDs (ERFORDERLICH). Alle Slide-IDs des Decks müssen enthalten sein.',
                ],
            ],
            'required' => ['deck_id', 'slide_ids'],
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

            if (empty($arguments['slide_ids']) || !is_array($arguments['slide_ids'])) {
                return ToolResult::error('VALIDATION_ERROR', 'slide_ids (Array von Slide-IDs) ist erforderlich.');
            }

            $deck = SlidesPresentation::find($arguments['deck_id']);
            if (!$deck) {
                return ToolResult::error('DECK_NOT_FOUND', 'Das Deck wurde nicht gefunden.');
            }

            try {
                Gate::forUser($context->user)->authorize('update', $deck);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst die Slides nicht neu sortieren.');
            }

            // Verify all slide IDs belong to the deck
            $existingIds = $deck->slides()->pluck('id')->toArray();
            $requestedIds = $arguments['slide_ids'];

            $missingIds = array_diff($existingIds, $requestedIds);
            $unknownIds = array_diff($requestedIds, $existingIds);

            if (!empty($unknownIds)) {
                return ToolResult::error('INVALID_SLIDE_IDS', 'Folgende Slide-IDs gehören nicht zu diesem Deck: ' . implode(', ', $unknownIds));
            }

            // Update sort order
            foreach ($requestedIds as $index => $slideId) {
                SlidesSlide::where('id', $slideId)
                    ->where('presentation_id', $deck->id)
                    ->update(['sort_order' => $index]);
            }

            // Return new order
            $slides = $deck->slides()->orderBy('sort_order')->get()->map(function ($slide) {
                return [
                    'id' => $slide->id,
                    'sort_order' => $slide->sort_order,
                    'layout_key' => $slide->layout_key,
                ];
            })->toArray();

            return ToolResult::success([
                'deck_id' => $deck->id,
                'slides' => $slides,
                'count' => count($slides),
                'message' => count($slides) . ' Slides erfolgreich neu sortiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Sortieren der Slides: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['slides', 'slide', 'sort', 'reorder'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
