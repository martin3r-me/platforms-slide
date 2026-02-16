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
    ];

    protected $casts = [
        'uuid' => 'string',
        'sort_order' => 'integer',
        'content' => 'array',
        'background' => 'array',
        'duration_seconds' => 'integer',
        'is_hidden' => 'boolean',
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
}
