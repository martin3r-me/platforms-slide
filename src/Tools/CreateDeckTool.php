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
        return 'POST /decks - Erstellt ein neues Deck (Präsentation). REST-Parameter: name (required, string) - Name. description (optional, string) - Beschreibung. folder_id (optional, integer) - Ordner. theme (optional, object) - Theme-Farben. initial_layout (optional, string) - Layout-Key für den ersten Slide (Standard: title-center).';
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
                    'description' => 'Optional: Theme-Konfiguration. Beispiel: {"colors": {"primary": "#1a1a2e", "accent": "#0f3460"}, "fonts": {"heading": "Inter", "body": "Inter"}}',
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
                    'description' => 'Optional: Layout-Key für den ersten Slide. Standard: "title-center". Verfügbar: title-center, title-left, section-break, content-text, content-bullets, two-column, image-right, image-left, image-full, quote, stats, closing.',
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

            if (!empty($arguments['theme'])) {
                $deckData['theme'] = $arguments['theme'];
            }

            if (!empty($arguments['slide_width'])) {
                $deckData['slide_width'] = $arguments['slide_width'];
            }

            if (!empty($arguments['slide_height'])) {
                $deckData['slide_height'] = $arguments['slide_height'];
            }

            $deck = SlidesPresentation::create($deckData);

            // Create initial slide
            $layoutKey = $arguments['initial_layout'] ?? 'title-center';
            $layouts = SlidesSlideTemplate::systemLayouts();
            $layout = collect($layouts)->firstWhere('layout_key', $layoutKey);

            if (!$layout) {
                $layout = collect($layouts)->firstWhere('layout_key', 'title-center');
                $layoutKey = 'title-center';
            }

            $slide = $deck->slides()->create([
                'sort_order' => 0,
                'layout_key' => $layoutKey,
                'content' => $layout['content'] ?? ['version' => 1, 'mode' => 'layout', 'elements' => []],
                'background' => $layout['background'] ?? ['type' => 'color', 'value' => '#ffffff'],
            ]);

            return ToolResult::success([
                'id' => $deck->id,
                'uuid' => $deck->uuid,
                'name' => $deck->name,
                'description' => $deck->description,
                'team_id' => $deck->team_id,
                'folder_id' => $deck->folder_id,
                'slide_width' => $deck->slide_width,
                'slide_height' => $deck->slide_height,
                'initial_slide' => [
                    'id' => $slide->id,
                    'uuid' => $slide->uuid,
                    'layout_key' => $slide->layout_key,
                    'placeholders' => $slide->getPlaceholders(),
                ],
                'created_at' => $deck->created_at->toIso8601String(),
                'message' => "Deck '{$deck->name}' erfolgreich erstellt mit einem {$layoutKey}-Slide.",
            ]);
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
