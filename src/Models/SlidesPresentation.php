<?php

namespace Platform\Slides\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class SlidesPresentation extends Model
{
    use SoftDeletes;

    protected $table = 'slides_presentations';

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'folder_id',
        'theme',
        'settings',
        'slide_width',
        'slide_height',
        'is_published',
        'public_token',
        'user_id',
        'team_id',
    ];

    protected $casts = [
        'uuid' => 'string',
        'settings' => 'array',
        'slide_width' => 'integer',
        'slide_height' => 'integer',
        'is_published' => 'boolean',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(SlidesFolder::class, 'folder_id');
    }

    public function slides(): HasMany
    {
        return $this->hasMany(SlidesSlide::class, 'presentation_id')->orderBy('sort_order');
    }

    public function media(): HasMany
    {
        return $this->hasMany(SlidesMedia::class, 'presentation_id');
    }

    public const FONT_SIZE_MIN = 12;
    public const FONT_SIZE_MAX = 200;

    public const DEFAULT_FONT_SIZES = [
        'title' => 80,
        'subtitle' => 40,
        'body' => 32,
        'bullets' => 30,
        'quote' => 42,
        'stats_number' => 96,
        'stats_label' => 24,
        'section_title' => 72,
        'contact' => 24,
    ];

    public function getDefaultTheme(): array
    {
        return [
            'colors' => [
                'primary' => '#1a1a2e',
                'accent' => '#0f3460',
                'text' => '#1a1a2e',
                'background' => '#ffffff',
            ],
            'fonts' => [
                'heading' => 'Open Sans',
                'body' => 'Open Sans',
            ],
            'fontSizes' => self::DEFAULT_FONT_SIZES,
            'defaultBackground' => [
                'type' => 'color',
                'value' => '#ffffff',
            ],
        ];
    }

    public function getThemeAttribute($value): array
    {
        $theme = $value ? (is_array($value) ? $value : json_decode($value, true)) : [];
        return array_replace_recursive($this->getDefaultTheme(), $theme ?: []);
    }

    public function setThemeAttribute($value): void
    {
        $theme = is_array($value) ? $value : (json_decode($value, true) ?: []);

        // Validate fontSizes if present
        if (isset($theme['fontSizes']) && is_array($theme['fontSizes'])) {
            $validKeys = array_keys(self::DEFAULT_FONT_SIZES);
            $validated = [];
            foreach ($theme['fontSizes'] as $key => $size) {
                if (in_array($key, $validKeys, true) && is_numeric($size)) {
                    $validated[$key] = max(self::FONT_SIZE_MIN, min(self::FONT_SIZE_MAX, (int) $size));
                }
            }
            $theme['fontSizes'] = $validated;
        }

        $this->attributes['theme'] = json_encode($theme);
    }
}
