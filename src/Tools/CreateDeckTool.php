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
 * Tool zum Erstellen eines neuen Decks (Präsentation).
 */
class CreateDeckTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'slides.decks.POST';
    }

    public function getDescription(): string
    {
        return 'POST /decks - Erstellt ein neues Deck (Präsentation). REST-Parameter: name (required, string) - Name. description (optional, string) - Beschreibung. folder_id (optional, integer) - Ordner. theme (optional, object) - Theme-Farben. theme_preset (optional, string) - Vordefiniertes Theme. initial_layout (optional, string) - Layout-Key für den ersten Slide (Standard: title-center). slides (optional, array) - Mehrere Slides mit Platzhaltern in einem Call erstellen (überschreibt initial_layout).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Name der Präsentation (ERFORDERLICH).',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Wenn nicht angegeben, wird das aktuelle Team verwendet.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung der Präsentation.',
                ],
                'folder_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Ordner-ID für die Präsentation.',
                ],
                'theme' => [
                    'type' => 'object',
                    'description' => 'Optional: Theme-Konfiguration. Beispiel: {"colors": {"primary": "#1a1a2e", "accent": "#0f3460"}, "fonts": {"heading": "Open Sans", "body": "Open Sans"}, "fontSizes": {"title": 80, "subtitle": 40, "body": 32, "bullets": 30, "quote": 42, "stats_number": 96, "stats_label": 24, "section_title": 72, "contact": 24}}. fontSizes: Werte in px (min 12, max 200). Wenn nicht gesetzt, greifen System-Defaults.',
                ],
                'slide_width' => [
                    'type' => 'integer',
                    'description' => 'Optional: Slide-Breite in Pixel (Standard: 1920).',
                ],
                'slide_height' => [
                    'type' => 'integer',
                    'description' => 'Optional: Slide-Höhe in Pixel (Standard: 1080).',
                ],
                'initial_layout' => [
                    'type' => 'string',
                    'description' => 'Optional: Layout-Key für den ersten Slide. Standard: "title-center". Verfügbar: title-center, title-center-dark, title-left, section-break, content-text, content-bullets, content-cards, two-column, comparison, agenda, image-right, image-left, image-full, quote, stats, closing.',
                ],
                'theme_preset' => [
                    'type' => 'string',
                    'description' => 'Optional: Theme-Preset anwenden. Verfügbar: corporate-blue, corporate-dark, elegant-serif, modern-green, warm-minimal, gradient-purple, tech-dark. Überschreibt theme-Parameter falls beide angegeben.',
                ],
                'slides' => [
                    'type' => 'array',
                    'description' => 'Optional: Mehrere Slides direkt beim Erstellen des Decks anlegen. Jedes Objekt: {"layout_key": "...", "placeholders": {...}, "background": {...}, "notes": "...", "transition": "...", "col_ratio": "..."}. '
                        . 'Wenn angegeben, wird der initial_layout-Parameter ignoriert und stattdessen diese Slides erstellt. '
                        . 'Ermöglicht komplette Präsentationen in einem einzigen Tool-Call.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'layout_key' => ['type' => 'string', 'description' => 'Layout-Key (Standard: content-text)'],
                            'placeholders' => ['type' => 'object', 'description' => 'Platzhalter befüllen (Zone-Name → String oder Objekt mit Style-Overrides)'],
                            'background' => ['type' => 'object', 'description' => 'Hintergrund'],
                            'notes' => ['type' => 'string', 'description' => 'Speaker Notes'],
                            'transition' => ['type' => 'string', 'description' => 'Übergangs-Effekt'],
                            'col_ratio' => ['type' => 'string', 'description' => 'Spalten-Verhältnis für two-column'],
                        ],
                    ],
                ],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (empty($arguments['name'])) {
                return ToolResult::error('VALIDATION_ERROR', 'Name ist erforderlich.');
            }
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $teamId = $arguments['team_id'] ?? null;
            if (empty($teamId) || $teamId === 0) {
                $teamId = $context->team?->id;
            }

            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden.');
            }

            $team = $context->user->teams()->find($teamId);
            if (!$team) {
                return ToolResult::error('TEAM_NOT_FOUND', 'Team nicht gefunden oder kein Zugriff.');
            }

            try {
                Gate::forUser($context->user)->authorize('create', SlidesPresentation::class);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst keine Präsentationen erstellen.');
            }

            $deckData = [
                'name' => $arguments['name'],
                'description' => $arguments['description'] ?? null,
                'folder_id' => $arguments['folder_id'] ?? null,
                'user_id' => $context->user->id,
                'team_id' => $teamId,
            ];

            if (!empty($arguments['theme_preset'])) {
                $preset = SlidesPresentation::getThemePreset($arguments['theme_preset']);
                if ($preset) {
                    $deckData['theme'] = [
                        'colors' => $preset['colors'],
                        'fonts' => $preset['fonts'],
                        'defaultBackground' => ['type' => 'color', 'value' => $preset['colors']['background']],
                    ];
                }
            }

            if (!empty($arguments['theme'])) {
                $existing = $deckData['theme'] ?? [];
                $deckData['theme'] = array_replace_recursive($existing, $arguments['theme']);
            }

            if (!empty($arguments['slide_width'])) {
                $deckData['slide_width'] = $arguments['slide_width'];
            }

            if (!empty($arguments['slide_height'])) {
                $deckData['slide_height'] = $arguments['slide_height'];
            }

            $deck = SlidesPresentation::create($deckData);

            $layouts = SlidesSlideTemplate::systemLayouts();
            $themeFontSizes = $deck->theme['fontSizes'] ?? [];
            $slidesData = $arguments['slides'] ?? null;
            $createdSlides = [];

            if (!empty($slidesData) && is_array($slidesData)) {
                // Multi-slide creation
                foreach ($slidesData as $index => $slideSpec) {
                    $layoutKey = $slideSpec['layout_key'] ?? 'content-text';
                    $layout = collect($layouts)->firstWhere('layout_key', $layoutKey);

                    if (!$layout) {
                        $layout = collect($layouts)->firstWhere('layout_key', 'content-text');
                        $layoutKey = 'content-text';
                    }

                    $content = $layout['content'] ?? ['version' => 1, 'mode' => 'layout', 'elements' => []];
                    $content = SlidesSlideTemplate::applyThemeFontSizes($content, $themeFontSizes);

                    // Apply col_ratio for two-column
                    if (!empty($slideSpec['col_ratio']) && $layoutKey === 'two-column') {
                        if (SlidesSlideTemplate::parseColRatio($slideSpec['col_ratio'])) {
                            $content = SlidesSlideTemplate::applyColRatio($content, $slideSpec['col_ratio']);
                        }
                    }

                    $slide = $deck->slides()->create([
                        'sort_order' => $index,
                        'layout_key' => $layoutKey,
                        'content' => $content,
                        'background' => $slideSpec['background'] ?? $layout['background'] ?? ['type' => 'color', 'value' => '#ffffff'],
                        'notes' => $slideSpec['notes'] ?? null,
                        'transition' => $slideSpec['transition'] ?? null,
                    ]);

                    // Fill placeholders if provided
                    if (!empty($slideSpec['placeholders']) && is_array($slideSpec['placeholders'])) {
                        $slide->fillPlaceholders($slideSpec['placeholders']);
                        $slide->refresh();
                    }

                    $slideInfo = [
                        'id' => $slide->id,
                        'uuid' => $slide->uuid,
                        'sort_order' => $index,
                        'layout_key' => $layoutKey,
                        'placeholders' => $slide->getPlaceholders(),
                    ];

                    if ($layoutKey === 'two-column') {
                        $slideInfo['col_ratio'] = $slide->content['col_ratio'] ?? '50:50';
                    }

                    $createdSlides[] = $slideInfo;
                }
            } else {
                // Single initial slide (backward compatible)
                $layoutKey = $arguments['initial_layout'] ?? 'title-center';
                $layout = collect($layouts)->firstWhere('layout_key', $layoutKey);

                if (!$layout) {
                    $layout = collect($layouts)->firstWhere('layout_key', 'title-center');
                    $layoutKey = 'title-center';
                }

                $content = $layout['content'] ?? ['version' => 1, 'mode' => 'layout', 'elements' => []];
                $content = SlidesSlideTemplate::applyThemeFontSizes($content, $themeFontSizes);

                $slide = $deck->slides()->create([
                    'sort_order' => 0,
                    'layout_key' => $layoutKey,
                    'content' => $content,
                    'background' => $layout['background'] ?? ['type' => 'color', 'value' => '#ffffff'],
                ]);

                $createdSlides[] = [
                    'id' => $slide->id,
                    'uuid' => $slide->uuid,
                    'sort_order' => 0,
                    'layout_key' => $layoutKey,
                    'placeholders' => $slide->getPlaceholders(),
                ];
            }

            $response = [
                'id' => $deck->id,
                'uuid' => $deck->uuid,
                'name' => $deck->name,
                'description' => $deck->description,
                'team_id' => $deck->team_id,
                'folder_id' => $deck->folder_id,
                'slide_width' => $deck->slide_width,
                'slide_height' => $deck->slide_height,
                'slides' => $createdSlides,
                'slide_count' => count($createdSlides),
                'created_at' => $deck->created_at->toIso8601String(),
                'message' => "Deck '{$deck->name}' erfolgreich erstellt mit " . count($createdSlides) . " Slide(s).",
            ];

            return ToolResult::success($response);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Decks: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['slides', 'deck', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => false,
            'side_effects' => ['creates'],
        ];
    }
}
