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
 * Tool zum Befüllen von Slide-Platzhaltern mit Werten.
 *
 * Dies ist das zentrale Tool, um Slide-Templates mit dynamischen
 * Inhalten zu füllen. Jeder Slide hat Platzhalter-Zonen (z.B. title,
 * body, bullets, media), die gezielt befüllt werden können.
 */
class FillSlideContentTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'slides.slide.content.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /slides/{id}/content - Befüllt die Platzhalter eines Slides mit Werten. WICHTIG: Nutze "slides.deck.GET" oder "slides.slides.GET" um die verfügbaren Platzhalter (zones) eines Slides zu sehen, bevor du dieses Tool nutzt. REST-Parameter: slide_id (required, integer). placeholders (required, object) - Key-Value-Paare von Zone-Name zu Inhalt. Werte können einfache Strings sein ODER Objekte mit Style-Overrides: {"title": {"value": "Mein Titel", "fontSize": 96, "color": "#FF0000", "align": "center"}}. Erlaubte Style-Properties: fontSize, color, align, fontWeight, fontStyle, letterSpacing, lineHeight.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'slide_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Slides (ERFORDERLICH). Nutze "slides.slides.GET" um Slides zu finden.',
                ],
                'placeholders' => [
                    'type' => 'object',
                    'description' => 'Key-Value-Paare zum Befüllen der Platzhalter. Keys sind Zone-Namen (z.B. "title", "body", "bullets", "media"). '
                        . 'Values können sein: (1) String mit dem Text/URL, (2) Objekt mit "value" und optionalen Style-Overrides. '
                        . 'Beispiel String-Format: {"title": "Mein Titel", "body": "Mein Text"} '
                        . 'Beispiel Objekt-Format: {"title": {"value": "Mein Titel", "fontSize": 96, "color": "#FF0000", "align": "center"}, "body": "Einfacher Text bleibt String"} '
                        . 'Erlaubte Style-Properties: fontSize (number, px), color (string, hex), align (string: left/center/right), fontWeight (string/number), fontStyle (string: normal/italic), letterSpacing (number, px), lineHeight (number).',
                    'additionalProperties' => [
                        'oneOf' => [
                            ['type' => 'string'],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'value' => ['type' => 'string', 'description' => 'Der Textinhalt oder die Bild-URL'],
                                    'fontSize' => ['type' => 'number', 'description' => 'Schriftgröße in px'],
                                    'color' => ['type' => 'string', 'description' => 'Textfarbe (Hex, z.B. #FF0000)'],
                                    'align' => ['type' => 'string', 'enum' => ['left', 'center', 'right'], 'description' => 'Textausrichtung'],
                                    'fontWeight' => ['type' => ['string', 'number'], 'description' => 'Schriftstärke (z.B. 400, 700, bold)'],
                                    'fontStyle' => ['type' => 'string', 'enum' => ['normal', 'italic'], 'description' => 'Schriftstil'],
                                    'letterSpacing' => ['type' => 'number', 'description' => 'Buchstabenabstand in px'],
                                    'lineHeight' => ['type' => 'number', 'description' => 'Zeilenhöhe (z.B. 1.2, 1.5)'],
                                ],
                                'required' => ['value'],
                            ],
                        ],
                    ],
                ],
            ],
            'required' => ['slide_id', 'placeholders'],
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

            if (empty($arguments['placeholders']) || !is_array($arguments['placeholders'])) {
                return ToolResult::error('VALIDATION_ERROR', 'placeholders (Objekt mit Zone-Wert-Paaren) ist erforderlich.');
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

            // Get available placeholders
            $availablePlaceholders = $slide->getPlaceholders();
            $availableZones = array_column($availablePlaceholders, 'zone');

            // Validate style properties in object-format placeholders
            $invalidStyles = [];
            foreach ($arguments['placeholders'] as $zone => $value) {
                if (is_array($value) && array_key_exists('value', $value)) {
                    $unknownProps = array_diff(
                        array_keys(array_diff_key($value, ['value' => true])),
                        SlidesSlide::ALLOWED_STYLE_OVERRIDES
                    );
                    if (!empty($unknownProps)) {
                        $invalidStyles[$zone] = $unknownProps;
                    }
                }
            }

            if (!empty($invalidStyles)) {
                $details = [];
                foreach ($invalidStyles as $zone => $props) {
                    $details[] = "{$zone}: " . implode(', ', $props);
                }
                return ToolResult::error('VALIDATION_ERROR',
                    'Ungültige Style-Properties: ' . implode('; ', $details)
                    . '. Erlaubt: ' . implode(', ', SlidesSlide::ALLOWED_STYLE_OVERRIDES) . '.'
                );
            }

            // Fill placeholders
            $results = [];
            $unknownZones = [];

            foreach ($arguments['placeholders'] as $zone => $value) {
                if (!in_array($zone, $availableZones)) {
                    $unknownZones[] = $zone;
                    $results[$zone] = false;
                    continue;
                }

                $results[$zone] = $slide->fillPlaceholder($zone, $value);
            }

            $slide->refresh();
            $filledCount = count(array_filter($results));
            $failedCount = count($results) - $filledCount;

            // Track which zones had style overrides
            $styleOverridesApplied = [];
            foreach ($arguments['placeholders'] as $zone => $value) {
                if (is_array($value) && array_key_exists('value', $value)) {
                    $overrides = array_intersect_key(
                        $value,
                        array_flip(SlidesSlide::ALLOWED_STYLE_OVERRIDES)
                    );
                    if (!empty($overrides) && ($results[$zone] ?? false)) {
                        $styleOverridesApplied[$zone] = array_keys($overrides);
                    }
                }
            }

            $response = [
                'slide_id' => $slide->id,
                'deck_id' => $slide->presentation_id,
                'layout_key' => $slide->layout_key,
                'results' => $results,
                'filled_count' => $filledCount,
                'failed_count' => $failedCount,
                'placeholders_after' => $slide->getPlaceholders(),
            ];

            if (!empty($styleOverridesApplied)) {
                $response['style_overrides_applied'] = $styleOverridesApplied;
            }

            if (!empty($unknownZones)) {
                $response['unknown_zones'] = $unknownZones;
                $response['available_zones'] = $availableZones;
                $response['hint'] = 'Folgende Zonen existieren nicht in diesem Slide: ' . implode(', ', $unknownZones) . '. Verfügbare Zonen: ' . implode(', ', $availableZones);
            }

            $response['message'] = "{$filledCount} Platzhalter befüllt" . ($failedCount > 0 ? ", {$failedCount} fehlgeschlagen" : '') . '.';

            return ToolResult::success($response);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Befüllen des Slides: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['slides', 'slide', 'content', 'fill', 'placeholder'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
