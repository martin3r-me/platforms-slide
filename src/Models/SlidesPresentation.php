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

    /**
     * Returns all available theme presets.
     * Each preset defines colors, fonts, and optional fontSizes overrides.
     */
    public static function themePresets(): array
    {
        return [
            'corporate-blue' => [
                'name' => 'Corporate Blue',
                'colors' => [
                    'primary' => '#1e3a5f',
                    'accent' => '#2980b9',
                    'text' => '#1a1a2e',
                    'background' => '#ffffff',
                ],
                'fonts' => [
                    'heading' => 'Montserrat',
                    'body' => 'Open Sans',
                ],
            ],
            'corporate-dark' => [
                'name' => 'Corporate Dark',
                'colors' => [
                    'primary' => '#e0e0e0',
                    'accent' => '#4fc3f7',
                    'text' => '#f5f5f5',
                    'background' => '#1a1a2e',
                ],
                'fonts' => [
                    'heading' => 'Montserrat',
                    'body' => 'Inter',
                ],
            ],
            'elegant-serif' => [
                'name' => 'Elegant Serif',
                'colors' => [
                    'primary' => '#2c2c2c',
                    'accent' => '#8b6914',
                    'text' => '#333333',
                    'background' => '#faf8f5',
                ],
                'fonts' => [
                    'heading' => 'Playfair Display',
                    'body' => 'Lora',
                ],
            ],
            'modern-green' => [
                'name' => 'Modern Green',
                'colors' => [
                    'primary' => '#1b5e20',
                    'accent' => '#43a047',
                    'text' => '#212121',
                    'background' => '#ffffff',
                ],
                'fonts' => [
                    'heading' => 'Poppins',
                    'body' => 'Inter',
                ],
            ],
            'warm-minimal' => [
                'name' => 'Warm Minimal',
                'colors' => [
                    'primary' => '#3e2723',
                    'accent' => '#d84315',
                    'text' => '#4e342e',
                    'background' => '#fff8f0',
                ],
                'fonts' => [
                    'heading' => 'Raleway',
                    'body' => 'Open Sans',
                ],
            ],
            'gradient-purple' => [
                'name' => 'Gradient Purple',
                'colors' => [
                    'primary' => '#4a148c',
                    'accent' => '#ce93d8',
                    'text' => '#f3e5f5',
                    'background' => '#1a0033',
                ],
                'fonts' => [
                    'heading' => 'Poppins',
                    'body' => 'Nunito Sans',
                ],
            ],
            'tech-dark' => [
                'name' => 'Tech Dark',
                'colors' => [
                    'primary' => '#00e676',
                    'accent' => '#00bcd4',
                    'text' => '#e0e0e0',
                    'background' => '#121212',
                ],
                'fonts' => [
                    'heading' => 'Inter',
                    'body' => 'JetBrains Mono',
                ],
            ],
        ];
    }

    /**
     * Get a single theme preset by key. Returns null if not found.
     */
    public static function getThemePreset(string $key): ?array
    {
        return self::themePresets()[$key] ?? null;
    }

    /**
     * Returns the default settings structure.
     * Settings cover persistent elements: logo, slide numbers, footer.
     */
    public static function getDefaultSettings(): array
    {
        return [
            'logo' => [
                'src' => null,
                'position' => 'top-right',
                'width' => 120,
                'opacity' => 1,
            ],
            'slideNumber' => [
                'enabled' => false,
                'position' => 'bottom-right',
            ],
            'footer' => [
                'enabled' => false,
                'text' => '',
                'position' => 'bottom-center',
            ],
        ];
    }

    public function getSettingsAttribute($value): array
    {
        $settings = $value ? (is_array($value) ? $value : json_decode($value, true)) : [];
        return array_replace_recursive(self::getDefaultSettings(), $settings ?: []);
    }

    public function setSettingsAttribute($value): void
    {
        $settings = is_array($value) ? $value : (json_decode($value, true) ?: []);
        $this->attributes['settings'] = json_encode($settings);
    }

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
