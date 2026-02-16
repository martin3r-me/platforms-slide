<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Slides\Models\SlidesSlide;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Tool zum Löschen eines Slides.
 */
class DeleteSlideTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'slides.slides.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /slides/{id} - Löscht einen Slide aus einem Deck. REST-Parameter: slide_id (required, integer). Die verbleibenden Slides werden automatisch neu sortiert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'slide_id' => [
                    'type' => 'integer',
                    'description' => 'ID des zu löschenden Slides (ERFORDERLICH).',
                ],
            ],
            'required' => ['slide_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            if (empty($arguments['slide_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'slide_id ist erforderlich.');
            }

            $slide = SlidesSlide::with('presentation')->find($arguments['slide_id']);
            if (!$slide) {
                return ToolResult::error('SLIDE_NOT_FOUND', 'Der Slide wurde nicht gefunden.');
            }

            try {
                Gate::forUser($context->user)->authorize('update', $slide->presentation);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst diesen Slide nicht löschen.');
            }

            $slideId = $slide->id;
            $deckId = $slide->presentation_id;
            $layoutKey = $slide->layout_key;

            $slide->delete();

            // Re-order remaining slides
            $slide->presentation->slides()
                ->orderBy('sort_order')
                ->get()
                ->each(function ($s, $index) {
                    $s->update(['sort_order' => $index]);
                });

            return ToolResult::success([
                'slide_id' => $slideId,
                'deck_id' => $deckId,
                'layout_key' => $layoutKey,
                'remaining_slides' => $slide->presentation->slides()->count(),
                'message' => "Slide (ID: {$slideId}, Layout: {$layoutKey}) erfolgreich gelöscht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Slides: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['slides', 'slide', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => false,
            'side_effects' => ['deletes'],
        ];
    }
}
