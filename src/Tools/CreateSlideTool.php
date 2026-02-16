<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Slides\Models\SlidesPresentation;
use Platform\Slides\Models\SlidesSlideTemplate;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Tool zum Erstellen eines neuen Slides in einem Deck.
 */
class CreateSlideTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'slides.slides.POST';
    }

    public function getDescription(): string
    {
        return 'POST /decks/{deck_id}/slides - Erstellt einen neuen Slide in einem Deck. REST-Parameter: deck_id (required), layout_key (optional, string - Standard: content-text). Verfügbare Layouts: title-center, title-left, section-break, content-text, content-bullets, two-column, image-right, image-left, image-full, quote, stats, closing. position (optional, integer) - Position im Deck (Standard: Ende).';
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
                'layout_key' => [
                    'type' => 'string',
                    'description' => 'Layout-Key für den Slide. Standard: "content-text". Verfügbar: title-center, title-left, section-break, content-text, content-bullets, two-column, image-right, image-left, image-full, quote, stats, closing.',
                ],
                'position' => [
                    'type' => 'integer',
                    'description' => 'Optional: Position des Slides (0-basiert). Standard: Am Ende.',
                ],
                'background' => [
                    'type' => 'object',
                    'description' => 'Optional: Hintergrund. Beispiel: {"type": "color", "value": "#ffffff"} oder {"type": "gradient", "value": {"direction": "to-br", "stops": ["#667eea", "#764ba2"]}}.',
                ],
                'notes' => [
                    'type' => 'string',
                    'description' => 'Optional: Speaker Notes.',
                ],
                'transition' => [
                    'type' => 'string',
                    'description' => 'Optional: Übergangs-Effekt (fade, slide-left, slide-right, slide-up, zoom).',
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
                Gate::forUser($context->user)->authorize('update', $deck);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst keine Slides in diesem Deck erstellen.');
            }

            $layoutKey = $arguments['layout_key'] ?? 'content-text';
            $layouts = SlidesSlideTemplate::systemLayouts();
            $layout = collect($layouts)->firstWhere('layout_key', $layoutKey);

            if (!$layout) {
                $availableKeys = collect($layouts)->pluck('layout_key')->implode(', ');
                return ToolResult::error('INVALID_LAYOUT', "Layout '{$layoutKey}' nicht gefunden. Verfügbar: {$availableKeys}");
            }

            // Determine position
            $maxOrder = $deck->slides()->max('sort_order') ?? -1;
            $position = $arguments['position'] ?? ($maxOrder + 1);

            // Shift existing slides if inserting at a specific position
            if ($position <= $maxOrder) {
                $deck->slides()
                    ->where('sort_order', '>=', $position)
                    ->increment('sort_order');
            }

            $slideData = [
                'sort_order' => $position,
                'layout_key' => $layoutKey,
                'content' => $layout['content'] ?? ['version' => 1, 'mode' => 'layout', 'elements' => []],
                'background' => $arguments['background'] ?? $layout['background'] ?? ['type' => 'color', 'value' => '#ffffff'],
                'notes' => $arguments['notes'] ?? null,
                'transition' => $arguments['transition'] ?? null,
            ];

            $slide = $deck->slides()->create($slideData);

            return ToolResult::success([
                'id' => $slide->id,
                'uuid' => $slide->uuid,
                'deck_id' => $deck->id,
                'sort_order' => $slide->sort_order,
                'layout_key' => $slide->layout_key,
                'placeholders' => $slide->getPlaceholders(),
                'background' => $slide->background,
                'transition' => $slide->transition,
                'notes' => $slide->notes,
                'created_at' => $slide->created_at->toIso8601String(),
                'message' => "Slide mit Layout '{$layoutKey}' an Position {$position} erstellt.",
                'hint' => 'Nutze "slides.slide.content.PUT" um die Platzhalter dieses Slides mit Inhalten zu befüllen.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Slides: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['slides', 'slide', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => false,
            'side_effects' => ['creates'],
        ];
    }
}
