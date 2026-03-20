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
        return 'POST /decks/{deck_id}/slides - Erstellt einen neuen Slide in einem Deck. REST-Parameter: deck_id (required), layout_key (optional, string - Standard: content-text). Verfügbare Layouts (16): title-center, title-center-dark, title-left, section-break, content-text, content-bullets, content-cards, two-column, comparison, agenda, image-right, image-left, image-full, quote, stats, closing. position (optional, integer) - Position im Deck (Standard: Ende). col_ratio (optional, string) - Spalten-Verhältnis für two-column Layout, z.B. "60:40", "40:60", "70:30". Standard: "50:50". placeholders (optional, object) - Platzhalter direkt befüllen (spart einen extra Tool-Call).';
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
                    'description' => 'Layout-Key für den Slide. Standard: "content-text". Verfügbar (16): title-center, title-center-dark, title-left, section-break, content-text, content-bullets, content-cards, two-column, comparison, agenda, image-right, image-left, image-full, quote, stats, closing.',
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
                'col_ratio' => [
                    'type' => 'string',
                    'description' => 'Optional: Spalten-Verhältnis für two-column Layout. Format: "links:rechts", z.B. "60:40", "40:60", "70:30", "33:67". Summe muss 100 ergeben, Minimum pro Spalte: 10. Standard: "50:50".',
                ],
                'placeholders' => [
                    'type' => 'object',
                    'description' => 'Optional: Platzhalter direkt beim Erstellen befüllen (spart einen extra slides.slide.content.PUT Call). '
                        . 'Key-Value-Paare: Keys sind Zone-Namen (z.B. "title", "body"), Values sind Strings oder Objekte mit Style-Overrides. '
                        . 'Beispiel: {"title": "Mein Titel", "body": {"value": "Text", "fontSize": 28, "color": "#333"}}',
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

            // Apply theme fontSizes to the template content
            $content = $layout['content'] ?? ['version' => 1, 'mode' => 'layout', 'elements' => []];
            $themeFontSizes = $deck->theme['fontSizes'] ?? [];
            $content = SlidesSlideTemplate::applyThemeFontSizes($content, $themeFontSizes);

            // Apply col_ratio for two-column layout
            $colRatio = $arguments['col_ratio'] ?? null;
            if ($colRatio !== null) {
                if ($layoutKey !== 'two-column') {
                    return ToolResult::error('VALIDATION_ERROR', 'col_ratio ist nur für das Layout "two-column" verfügbar.');
                }
                if (!SlidesSlideTemplate::parseColRatio($colRatio)) {
                    return ToolResult::error('VALIDATION_ERROR', 'Ungültiges col_ratio Format. Erwartet: "links:rechts" (z.B. "60:40"). Summe muss 100 ergeben, Minimum pro Spalte: 10.');
                }
                $content = SlidesSlideTemplate::applyColRatio($content, $colRatio);
            }

            $slideData = [
                'sort_order' => $position,
                'layout_key' => $layoutKey,
                'content' => $content,
                'background' => $arguments['background'] ?? $layout['background'] ?? ['type' => 'color', 'value' => '#ffffff'],
                'notes' => $arguments['notes'] ?? null,
                'transition' => $arguments['transition'] ?? null,
            ];

            $slide = $deck->slides()->create($slideData);

            // Fill placeholders if provided
            $placeholderResults = null;
            if (!empty($arguments['placeholders']) && is_array($arguments['placeholders'])) {
                $placeholderResults = $slide->fillPlaceholders($arguments['placeholders']);
                $slide->refresh();
            }

            $response = [
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
                'message' => "Slide mit Layout '{$layoutKey}' an Position {$position} erstellt."
                    . ($placeholderResults ? ' ' . count(array_filter($placeholderResults)) . ' Platzhalter befüllt.' : ''),
            ];

            if ($placeholderResults) {
                $response['placeholder_results'] = $placeholderResults;
            } else {
                $response['hint'] = 'Nutze "slides.slide.content.PUT" um die Platzhalter dieses Slides mit Inhalten zu befüllen, oder übergib "placeholders" direkt beim Erstellen.';
            }

            if ($slide->layout_key === 'two-column') {
                $response['col_ratio'] = $slide->content['col_ratio'] ?? '50:50';
            }

            return ToolResult::success($response);
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
