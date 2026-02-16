<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Slides\Models\SlidesSlide;
use Platform\Slides\Models\SlidesSlideTemplate;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Tool zum Aktualisieren eines Slides.
 */
class UpdateSlideTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'slides.slides.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /slides/{id} - Aktualisiert einen bestehenden Slide. REST-Parameter: slide_id (required, integer). layout_key (optional, string) - Layout wechseln. background (optional, object). notes (optional, string). transition (optional, string). is_hidden (optional, boolean).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'slide_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Slides (ERFORDERLICH).',
                ],
                'layout_key' => [
                    'type' => 'string',
                    'description' => 'Optional: Layout wechseln. ACHTUNG: Setzt den Content auf die Template-Defaults zurück!',
                ],
                'background' => [
                    'type' => 'object',
                    'description' => 'Optional: Neuer Hintergrund.',
                ],
                'notes' => [
                    'type' => 'string',
                    'description' => 'Optional: Speaker Notes aktualisieren.',
                ],
                'transition' => [
                    'type' => 'string',
                    'description' => 'Optional: Übergangs-Effekt ändern.',
                ],
                'is_hidden' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Slide verstecken/anzeigen.',
                ],
                'duration_seconds' => [
                    'type' => 'integer',
                    'description' => 'Optional: Anzeigedauer in Sekunden.',
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
                return ToolResult::error('ACCESS_DENIED', 'Du darfst diesen Slide nicht bearbeiten.');
            }

            $updateData = [];

            // Layout change (resets content to template defaults)
            if (isset($arguments['layout_key'])) {
                $layouts = SlidesSlideTemplate::systemLayouts();
                $layout = collect($layouts)->firstWhere('layout_key', $arguments['layout_key']);

                if (!$layout) {
                    return ToolResult::error('INVALID_LAYOUT', "Layout '{$arguments['layout_key']}' nicht gefunden.");
                }

                $updateData['layout_key'] = $arguments['layout_key'];
                $updateData['content'] = $layout['content'];

                if (isset($layout['background'])) {
                    $updateData['background'] = $layout['background'];
                }
            }

            if (isset($arguments['background'])) {
                $updateData['background'] = $arguments['background'];
            }
            if (isset($arguments['notes'])) {
                $updateData['notes'] = $arguments['notes'] ?: null;
            }
            if (isset($arguments['transition'])) {
                $updateData['transition'] = $arguments['transition'] ?: null;
            }
            if (isset($arguments['is_hidden'])) {
                $updateData['is_hidden'] = $arguments['is_hidden'];
            }
            if (isset($arguments['duration_seconds'])) {
                $updateData['duration_seconds'] = $arguments['duration_seconds'];
            }

            if (!empty($updateData)) {
                $slide->update($updateData);
            }

            $slide->refresh();

            return ToolResult::success([
                'id' => $slide->id,
                'uuid' => $slide->uuid,
                'deck_id' => $slide->presentation_id,
                'sort_order' => $slide->sort_order,
                'layout_key' => $slide->layout_key,
                'placeholders' => $slide->getPlaceholders(),
                'background' => $slide->background,
                'transition' => $slide->transition,
                'notes' => $slide->notes,
                'is_hidden' => $slide->is_hidden,
                'duration_seconds' => $slide->duration_seconds,
                'updated_at' => $slide->updated_at->toIso8601String(),
                'message' => 'Slide erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Slides: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['slides', 'slide', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
