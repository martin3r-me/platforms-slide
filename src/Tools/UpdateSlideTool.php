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
        return 'PUT /slides/{id} - Aktualisiert einen bestehenden Slide. REST-Parameter: slide_id (required, integer). layout_key (optional, string) - Layout wechseln (16 Layouts verfügbar: title-center, title-center-dark, title-left, section-break, content-text, content-bullets, content-cards, two-column, comparison, agenda, image-right, image-left, image-full, quote, stats, closing). background (optional, object). notes (optional, string). transition (optional, string). is_hidden (optional, boolean). col_ratio (optional, string) - Spalten-Verhältnis für two-column Layout, z.B. "60:40", "40:60", "70:30". Standard: "50:50".';
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
                'col_ratio' => [
                    'type' => 'string',
                    'description' => 'Optional: Spalten-Verhältnis für two-column Layout. Format: "links:rechts", z.B. "60:40", "40:60", "70:30", "33:67". Summe muss 100 ergeben, Minimum pro Spalte: 10. Standard: "50:50".',
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

            // Apply col_ratio for two-column layout
            if (isset($arguments['col_ratio'])) {
                $effectiveLayoutKey = $updateData['layout_key'] ?? $slide->layout_key;
                if ($effectiveLayoutKey !== 'two-column') {
                    return ToolResult::error('VALIDATION_ERROR', 'col_ratio ist nur für das Layout "two-column" verfügbar.');
                }
                if (!SlidesSlideTemplate::parseColRatio($arguments['col_ratio'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'Ungültiges col_ratio Format. Erwartet: "links:rechts" (z.B. "60:40"). Summe muss 100 ergeben, Minimum pro Spalte: 10.');
                }
                $contentToModify = $updateData['content'] ?? $slide->content;
                $updateData['content'] = SlidesSlideTemplate::applyColRatio($contentToModify, $arguments['col_ratio']);
            }

            if (!empty($updateData)) {
                $slide->update($updateData);
            }

            $slide->refresh();

            $response = [
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
            ];

            if ($slide->layout_key === 'two-column') {
                $response['col_ratio'] = $slide->content['col_ratio'] ?? '50:50';
            }

            return ToolResult::success($response);
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
