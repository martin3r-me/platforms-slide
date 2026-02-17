<?php

namespace Platform\Slides\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class SlidesSlide extends Model
{
    protected $table = 'slides_slides';

    protected $fillable = [
        'uuid',
        'presentation_id',
        'sort_order',
        'layout_key',
        'content',
        'background',
        'transition',
        'notes',
        'duration_seconds',
        'is_hidden',
        'placeholders',
    ];

    protected $casts = [
        'uuid' => 'string',
        'sort_order' => 'integer',
        'content' => 'array',
        'background' => 'array',
        'duration_seconds' => 'integer',
        'is_hidden' => 'boolean',
        'placeholders' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $model->uuid = $uuid;
        });
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(SlidesPresentation::class, 'presentation_id');
    }

    /**
     * Returns the default content structure for a new slide.
     */
    public function getDefaultContent(?string $layoutKey = null): array
    {
        return [
            'version' => 1,
            'mode' => $layoutKey ? 'layout' : 'freeform',
            'elements' => [],
        ];
    }

    /**
     * Returns the default background.
     */
    public static function defaultBackground(): array
    {
        return ['type' => 'color', 'value' => '#ffffff'];
    }

    /**
     * Allowed style properties for element-level overrides.
     */
    public const ALLOWED_STYLE_OVERRIDES = [
        'fontSize',
        'color',
        'align',
        'fontWeight',
        'fontStyle',
        'letterSpacing',
        'lineHeight',
    ];

    /**
     * Extract placeholder zones from the slide's content elements.
     * Each element with a 'zone' property represents a fillable placeholder.
     * Returns current style information for each placeholder.
     */
    public function getPlaceholders(): array
    {
        $elements = $this->content['elements'] ?? [];
        $placeholders = [];

        foreach ($elements as $element) {
            if (!empty($element['zone'])) {
                $placeholder = [
                    'zone' => $element['zone'],
                    'type' => $element['type'],
                    'element_id' => $element['id'],
                    'current_value' => $element['type'] === 'text'
                        ? ($element['content']['plainText'] ?? '')
                        : ($element['content']['src'] ?? ''),
                ];

                // Include current style for text elements
                if ($element['type'] === 'text' && !empty($element['style'])) {
                    $style = [];
                    foreach (self::ALLOWED_STYLE_OVERRIDES as $prop) {
                        $key = $prop === 'align' ? 'textAlign' : $prop;
                        if (isset($element['style'][$key])) {
                            $style[$prop] = $element['style'][$key];
                        }
                    }
                    if (!empty($style)) {
                        $placeholder['style'] = $style;
                    }
                }

                $placeholders[] = $placeholder;
            }
        }

        return $placeholders;
    }

    /**
     * Normalize a placeholder value input.
     *
     * Accepts either a simple string or an object with 'value' + style overrides.
     * Returns [textValue, styleOverrides].
     */
    protected function normalizePlaceholderValue(string|array $value): array
    {
        if (is_string($value)) {
            return [$value, []];
        }

        // Object format: { value: "...", fontSize: 96, color: "#FF0000", ... }
        if (is_array($value) && array_key_exists('value', $value)) {
            $textValue = $value['value'];
            $styleOverrides = [];
            foreach (self::ALLOWED_STYLE_OVERRIDES as $prop) {
                if (array_key_exists($prop, $value)) {
                    $styleOverrides[$prop] = $value[$prop];
                }
            }
            return [$textValue, $styleOverrides];
        }

        // Legacy array format (direct content merge) - keep backward compatible
        return [null, [], $value];
    }

    /**
     * Apply validated style overrides to an element's style array.
     * Maps 'align' to 'textAlign' for internal storage.
     */
    protected function applyStyleOverrides(array $style, array $overrides): array
    {
        $mapping = ['align' => 'textAlign'];

        foreach ($overrides as $prop => $val) {
            $key = $mapping[$prop] ?? $prop;
            $style[$key] = $val;
        }

        return $style;
    }

    /**
     * Legacy stat zone mapping: stat_N → stat_N_value + stat_N_label.
     * When filling a legacy stat_N zone, the value is split on \n and
     * distributed to the separate value/label zones.
     */
    protected const LEGACY_STAT_ZONES = [
        'stat_1' => ['stat_1_value', 'stat_1_label'],
        'stat_2' => ['stat_2_value', 'stat_2_label'],
        'stat_3' => ['stat_3_value', 'stat_3_label'],
        'stat_4' => ['stat_4_value', 'stat_4_label'],
    ];

    public static function getLegacyStatZones(): array
    {
        return self::LEGACY_STAT_ZONES;
    }

    /**
     * Fill a specific placeholder zone with new content.
     *
     * Accepts string values (backward compatible) or object values with style overrides:
     *   { "value": "My Title", "fontSize": 96, "color": "#FF0000", "align": "left" }
     *
     * Legacy stat zones (stat_1 through stat_4) are automatically mapped to the
     * separate stat_N_value and stat_N_label zones. Values containing \n are split
     * into value (before \n) and label (after \n).
     *
     * Returns true if the zone was found and filled.
     */
    public function fillPlaceholder(string $zone, string|array $value): bool
    {
        // Legacy stat zone backward compatibility
        if (isset(self::LEGACY_STAT_ZONES[$zone])) {
            return $this->fillLegacyStatZone($zone, $value);
        }

        $content = $this->content ?? ['version' => 1, 'mode' => 'layout', 'elements' => []];
        $found = false;

        foreach ($content['elements'] as &$element) {
            if (($element['zone'] ?? null) === $zone) {
                $normalized = $this->normalizePlaceholderValue($value);

                if ($element['type'] === 'text') {
                    // Check for legacy array format (no 'value' key)
                    if ($normalized[0] === null && isset($normalized[2])) {
                        $element['content'] = array_merge($element['content'] ?? [], $normalized[2]);
                    } else {
                        $textValue = $normalized[0];
                        $styleOverrides = $normalized[1];

                        if (is_string($textValue)) {
                            // Auto-wrap in appropriate HTML based on existing structure
                            $existingHtml = $element['content']['html'] ?? '';
                            if (str_contains($existingHtml, '<h1')) {
                                $html = '<h1>' . e($textValue) . '</h1>';
                            } elseif (str_contains($existingHtml, '<h2')) {
                                $html = '<h2>' . e($textValue) . '</h2>';
                            } elseif (str_contains($existingHtml, '<blockquote')) {
                                $html = '<blockquote>' . e($textValue) . '</blockquote>';
                            } elseif (str_contains($existingHtml, '<ul')) {
                                $items = array_filter(array_map('trim', explode("\n", $textValue)));
                                $html = '<ul>' . implode('', array_map(fn($item) => '<li>' . e($item) . '</li>', $items)) . '</ul>';
                            } else {
                                $html = '<p>' . e($textValue) . '</p>';
                            }
                            $element['content']['html'] = $html;
                            $element['content']['plainText'] = $textValue;
                        }

                        // Apply style overrides if provided
                        if (!empty($styleOverrides)) {
                            $element['style'] = $this->applyStyleOverrides(
                                $element['style'] ?? [],
                                $styleOverrides
                            );
                        }
                    }
                } elseif ($element['type'] === 'image') {
                    if ($normalized[0] === null && isset($normalized[2])) {
                        $element['content'] = array_merge($element['content'] ?? [], $normalized[2]);
                    } else {
                        $textValue = $normalized[0];
                        if (is_string($textValue)) {
                            $element['content']['src'] = $textValue;
                        }
                    }
                }
                $found = true;
                break;
            }
        }

        if ($found) {
            $this->update(['content' => $content]);
        }

        return $found;
    }

    /**
     * Handle backward-compatible filling of legacy stat_N zones.
     * Splits the value on \n and fills stat_N_value + stat_N_label.
     */
    protected function fillLegacyStatZone(string $zone, string|array $value): bool
    {
        [$valueZone, $labelZone] = self::LEGACY_STAT_ZONES[$zone];

        $normalized = $this->normalizePlaceholderValue($value);
        $textValue = $normalized[0];
        $styleOverrides = $normalized[1];

        // If legacy array format (no 'value' key), just pass through to value zone
        if ($textValue === null && isset($normalized[2])) {
            return $this->fillPlaceholder($valueZone, $value);
        }

        if (is_string($textValue) && str_contains($textValue, "\n")) {
            $parts = explode("\n", $textValue, 2);
            $statValue = trim($parts[0]);
            $statLabel = trim($parts[1]);

            $valueResult = $this->fillPlaceholder($valueZone, !empty($styleOverrides)
                ? array_merge(['value' => $statValue], $styleOverrides)
                : $statValue);
            $labelResult = $this->fillPlaceholder($labelZone, $statLabel);

            return $valueResult || $labelResult;
        }

        // No \n: fill value zone with the entire text, leave label as is
        return $this->fillPlaceholder($valueZone, $value);
    }

    /**
     * Fill multiple placeholder zones at once.
     * Returns an array of results per zone.
     */
    public function fillPlaceholders(array $data): array
    {
        $results = [];
        foreach ($data as $zone => $value) {
            $results[$zone] = $this->fillPlaceholder($zone, $value);
        }
        return $results;
    }
}
