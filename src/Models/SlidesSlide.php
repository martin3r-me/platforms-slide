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
     * Extract placeholder zones from the slide's content elements.
     * Each element with a 'zone' property represents a fillable placeholder.
     */
    public function getPlaceholders(): array
    {
        $elements = $this->content['elements'] ?? [];
        $placeholders = [];

        foreach ($elements as $element) {
            if (!empty($element['zone'])) {
                $placeholders[] = [
                    'zone' => $element['zone'],
                    'type' => $element['type'],
                    'element_id' => $element['id'],
                    'current_value' => $element['type'] === 'text'
                        ? ($element['content']['plainText'] ?? '')
                        : ($element['content']['src'] ?? ''),
                ];
            }
        }

        return $placeholders;
    }

    /**
     * Fill a specific placeholder zone with new content.
     * Returns true if the zone was found and filled.
     */
    public function fillPlaceholder(string $zone, string|array $value): bool
    {
        $content = $this->content ?? ['version' => 1, 'mode' => 'layout', 'elements' => []];
        $found = false;

        foreach ($content['elements'] as &$element) {
            if (($element['zone'] ?? null) === $zone) {
                if ($element['type'] === 'text') {
                    if (is_string($value)) {
                        // Auto-wrap in appropriate HTML based on existing structure
                        $existingHtml = $element['content']['html'] ?? '';
                        if (str_contains($existingHtml, '<h1')) {
                            $html = '<h1>' . e($value) . '</h1>';
                        } elseif (str_contains($existingHtml, '<h2')) {
                            $html = '<h2>' . e($value) . '</h2>';
                        } elseif (str_contains($existingHtml, '<blockquote')) {
                            $html = '<blockquote>' . e($value) . '</blockquote>';
                        } elseif (str_contains($existingHtml, '<ul')) {
                            $items = array_filter(array_map('trim', explode("\n", $value)));
                            $html = '<ul>' . implode('', array_map(fn($item) => '<li>' . e($item) . '</li>', $items)) . '</ul>';
                        } else {
                            $html = '<p>' . e($value) . '</p>';
                        }
                        $element['content']['html'] = $html;
                        $element['content']['plainText'] = $value;
                    } elseif (is_array($value)) {
                        $element['content'] = array_merge($element['content'] ?? [], $value);
                    }
                } elseif ($element['type'] === 'image') {
                    if (is_string($value)) {
                        $element['content']['src'] = $value;
                    } elseif (is_array($value)) {
                        $element['content'] = array_merge($element['content'] ?? [], $value);
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
