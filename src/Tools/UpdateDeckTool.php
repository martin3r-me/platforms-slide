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
 * Tool zum Aktualisieren eines Decks (Präsentation).
 */
class UpdateDeckTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'slides.decks.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /decks/{id} - Aktualisiert ein bestehendes Deck. REST-Parameter: deck_id (required, integer) - Deck-ID. name (optional, string). description (optional, string). theme (optional, object). folder_id (optional, integer). is_published (optional, boolean).';
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
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Name.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Beschreibung.',
                ],
                'theme' => [
                    'type' => 'object',
                    'description' => 'Optional: Theme aktualisieren. Wird mit bestehenden Werten gemerged. Unterstützt: colors, fonts, fontSizes. fontSizes-Beispiel: {"fontSizes": {"title": 80, "subtitle": 40, "body": 32, "bullets": 30, "quote": 42, "stats_number": 96, "stats_label": 24, "section_title": 72, "contact": 24}}. Werte in px (min 12, max 200).',
                ],
                'folder_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Deck in einen anderen Ordner verschieben.',
                ],
                'is_published' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Veröffentlicht-Status ändern.',
                ],
                'slide_width' => [
                    'type' => 'integer',
                    'description' => 'Optional: Slide-Breite ändern.',
                ],
                'slide_height' => [
                    'type' => 'integer',
                    'description' => 'Optional: Slide-Höhe ändern.',
                ],
                'theme_preset' => [
                    'type' => 'string',
                    'description' => 'Optional: Theme-Preset anwenden. Verfügbar: corporate-blue, corporate-dark, elegant-serif, modern-green, warm-minimal, gradient-purple, tech-dark. Wird mit bestehenden Werten gemerged, theme-Parameter hat Vorrang.',
                ],
                'settings' => [
                    'type' => 'object',
                    'description' => 'Optional: Präsentations-Settings aktualisieren. Wird mit bestehenden Werten gemerged. Struktur: {"logo": {"src": "url", "position": "top-right|top-left|bottom-right|bottom-left", "width": 120, "opacity": 1}, "slideNumber": {"enabled": true, "position": "bottom-right|bottom-left|top-right|top-left"}, "footer": {"enabled": true, "text": "Footer text", "position": "bottom-center|bottom-left|bottom-right"}}.',
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
                return ToolResult::error('ACCESS_DENIED', 'Du darfst dieses Deck nicht bearbeiten.');
            }

            $updateData = [];

            if (isset($arguments['name'])) {
                $updateData['name'] = $arguments['name'];
            }
            if (isset($arguments['description'])) {
                $updateData['description'] = $arguments['description'];
            }
            if (isset($arguments['folder_id'])) {
                $updateData['folder_id'] = $arguments['folder_id'] ?: null;
            }
            if (isset($arguments['is_published'])) {
                $updateData['is_published'] = $arguments['is_published'];
                if ($arguments['is_published'] && !$deck->public_token) {
                    $updateData['public_token'] = bin2hex(random_bytes(32));
                }
            }
            if (isset($arguments['slide_width'])) {
                $updateData['slide_width'] = $arguments['slide_width'];
            }
            if (isset($arguments['slide_height'])) {
                $updateData['slide_height'] = $arguments['slide_height'];
            }
            if (!empty($arguments['theme_preset'])) {
                $preset = SlidesPresentation::getThemePreset($arguments['theme_preset']);
                if ($preset) {
                    $currentTheme = $deck->theme;
                    $presetTheme = [
                        'colors' => $preset['colors'],
                        'fonts' => $preset['fonts'],
                        'defaultBackground' => ['type' => 'color', 'value' => $preset['colors']['background']],
                    ];
                    $updateData['theme'] = array_replace_recursive($currentTheme, $presetTheme);
                }
            }

            if (isset($arguments['theme'])) {
                $base = $updateData['theme'] ?? $deck->theme;
                $updateData['theme'] = array_replace_recursive($base, $arguments['theme']);
            }

            if (isset($arguments['settings'])) {
                $currentSettings = $deck->settings;
                $updateData['settings'] = array_replace_recursive($currentSettings, $arguments['settings']);
            }

            if (!empty($updateData)) {
                $deck->update($updateData);
            }

            $deck->refresh();

            return ToolResult::success([
                'id' => $deck->id,
                'uuid' => $deck->uuid,
                'name' => $deck->name,
                'description' => $deck->description,
                'folder_id' => $deck->folder_id,
                'theme' => $deck->theme,
                'slide_width' => $deck->slide_width,
                'slide_height' => $deck->slide_height,
                'is_published' => $deck->is_published,
                'public_token' => $deck->public_token,
                'updated_at' => $deck->updated_at->toIso8601String(),
                'message' => "Deck '{$deck->name}' erfolgreich aktualisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Decks: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['slides', 'deck', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
