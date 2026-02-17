<?php

namespace Platform\Slides\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class SlidesSlideTemplate extends Model
{
    protected $table = 'slides_slide_templates';

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'category',
        'layout_key',
        'thumbnail_path',
        'content',
        'background',
        'is_system',
        'user_id',
        'team_id',
    ];

    protected $casts = [
        'uuid' => 'string',
        'content' => 'array',
        'background' => 'array',
        'is_system' => 'boolean',
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

    /**
     * Maps element zones to fontSizes theme keys.
     * Used to resolve theme-level font sizes for each zone.
     */
    public const ZONE_FONT_SIZE_MAP = [
        'title' => 'title',
        'subtitle' => 'subtitle',
        'description' => 'body',
        'body' => 'body',
        'bullets' => 'bullets',
        'col_left' => 'body',
        'col_right' => 'body',
        'overlay_title' => 'section_title',
        'overlay_text' => 'body',
        'quote' => 'quote',
        'author' => 'contact',
        'stat_1' => 'stats_number',
        'stat_2' => 'stats_number',
        'stat_3' => 'stats_number',
        'stat_4' => 'stats_number',
        'contact' => 'contact',
    ];

    /**
     * Applies theme fontSizes to a layout content structure.
     * Returns a new content array with font sizes resolved from the theme.
     * If no fontSizes are set in the theme, the template defaults remain.
     */
    public static function applyThemeFontSizes(array $content, array $themeFontSizes): array
    {
        if (empty($themeFontSizes) || empty($content['elements'])) {
            return $content;
        }

        foreach ($content['elements'] as &$element) {
            if ($element['type'] !== 'text' || empty($element['zone'])) {
                continue;
            }

            $zone = $element['zone'];
            $fontSizeKey = self::ZONE_FONT_SIZE_MAP[$zone] ?? null;

            if ($fontSizeKey && isset($themeFontSizes[$fontSizeKey])) {
                $element['style']['fontSize'] = $themeFontSizes[$fontSizeKey];
            }
        }

        return $content;
    }

    /**
     * Returns all 12 system layout definitions.
     */
    public static function systemLayouts(): array
    {
        return [
            [
                'name' => 'Titel zentriert',
                'description' => 'Titel zentriert + Untertitel',
                'category' => 'title',
                'layout_key' => 'title-center',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => 100, 'y' => 300, 'width' => 1720, 'height' => 260, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h1>Titel</h1>', 'plainText' => 'Titel'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 84, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'center', 'lineHeight' => 1.2]],
                        ['id' => 'el_subtitle', 'type' => 'text', 'zone' => 'subtitle', 'x' => 300, 'y' => 580, 'width' => 1320, 'height' => 120, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<p>Untertitel</p>', 'plainText' => 'Untertitel'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 36, 'fontWeight' => '400', 'color' => '#666666', 'textAlign' => 'center', 'lineHeight' => 1.4]],
                    ],
                ],
            ],
            [
                'name' => 'Titel links',
                'description' => 'Titel links + Beschreibung rechts',
                'category' => 'title',
                'layout_key' => 'title-left',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => 100, 'y' => 280, 'width' => 800, 'height' => 300, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h1>Titel</h1>', 'plainText' => 'Titel'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 80, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'left', 'lineHeight' => 1.2]],
                        ['id' => 'el_description', 'type' => 'text', 'zone' => 'description', 'x' => 1020, 'y' => 300, 'width' => 800, 'height' => 400, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<p>Beschreibung</p>', 'plainText' => 'Beschreibung'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 32, 'fontWeight' => '400', 'color' => '#444444', 'textAlign' => 'left', 'lineHeight' => 1.5]],
                    ],
                ],
            ],
            [
                'name' => 'Kapitelüberschrift',
                'description' => 'Kapitelüberschrift, farbiger Hintergrund',
                'category' => 'title',
                'layout_key' => 'section-break',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => 100, 'y' => 360, 'width' => 1720, 'height' => 240, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h1>Kapitel</h1>', 'plainText' => 'Kapitel'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 80, 'fontWeight' => '800', 'color' => '#ffffff', 'textAlign' => 'center', 'lineHeight' => 1.1]],
                    ],
                ],
                'background' => ['type' => 'gradient', 'value' => ['direction' => 'to-br', 'stops' => ['#667eea', '#764ba2']]],
            ],
            [
                'name' => 'Text-Inhalt',
                'description' => 'Überschrift + Textblock',
                'category' => 'content',
                'layout_key' => 'content-text',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => 100, 'y' => 80, 'width' => 1720, 'height' => 140, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h2>Überschrift</h2>', 'plainText' => 'Überschrift'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 52, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'left', 'lineHeight' => 1.2]],
                        ['id' => 'el_body', 'type' => 'text', 'zone' => 'body', 'x' => 100, 'y' => 260, 'width' => 1720, 'height' => 700, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<p>Textinhalt</p>', 'plainText' => 'Textinhalt'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 32, 'fontWeight' => '400', 'color' => '#333333', 'textAlign' => 'left', 'lineHeight' => 1.6]],
                    ],
                ],
            ],
            [
                'name' => 'Aufzählung',
                'description' => 'Überschrift + Aufzählungsliste',
                'category' => 'content',
                'layout_key' => 'content-bullets',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => 100, 'y' => 80, 'width' => 1720, 'height' => 140, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h2>Überschrift</h2>', 'plainText' => 'Überschrift'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 52, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'left', 'lineHeight' => 1.2]],
                        ['id' => 'el_bullets', 'type' => 'text', 'zone' => 'bullets', 'x' => 100, 'y' => 260, 'width' => 1720, 'height' => 700, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<ul><li>Punkt 1</li><li>Punkt 2</li><li>Punkt 3</li></ul>', 'plainText' => "Punkt 1\nPunkt 2\nPunkt 3"], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 30, 'fontWeight' => '400', 'color' => '#333333', 'textAlign' => 'left', 'lineHeight' => 1.8]],
                    ],
                ],
            ],
            [
                'name' => 'Zwei Spalten',
                'description' => 'Zwei gleichbreite Spalten',
                'category' => 'content',
                'layout_key' => 'two-column',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => 100, 'y' => 80, 'width' => 1720, 'height' => 140, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h2>Überschrift</h2>', 'plainText' => 'Überschrift'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 52, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'left', 'lineHeight' => 1.2]],
                        ['id' => 'el_col_left', 'type' => 'text', 'zone' => 'col_left', 'x' => 100, 'y' => 260, 'width' => 820, 'height' => 700, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<p>Linke Spalte</p>', 'plainText' => 'Linke Spalte'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 28, 'fontWeight' => '400', 'color' => '#333333', 'textAlign' => 'left', 'lineHeight' => 1.6]],
                        ['id' => 'el_col_right', 'type' => 'text', 'zone' => 'col_right', 'x' => 1000, 'y' => 260, 'width' => 820, 'height' => 700, 'rotation' => 0, 'zIndex' => 3, 'locked' => false, 'content' => ['html' => '<p>Rechte Spalte</p>', 'plainText' => 'Rechte Spalte'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 28, 'fontWeight' => '400', 'color' => '#333333', 'textAlign' => 'left', 'lineHeight' => 1.6]],
                    ],
                ],
            ],
            [
                'name' => 'Bild rechts',
                'description' => 'Text links, Bild rechts',
                'category' => 'media',
                'layout_key' => 'image-right',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => 100, 'y' => 80, 'width' => 860, 'height' => 140, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h2>Überschrift</h2>', 'plainText' => 'Überschrift'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 48, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'left', 'lineHeight' => 1.2]],
                        ['id' => 'el_body', 'type' => 'text', 'zone' => 'body', 'x' => 100, 'y' => 260, 'width' => 860, 'height' => 700, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<p>Text</p>', 'plainText' => 'Text'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 28, 'fontWeight' => '400', 'color' => '#333333', 'textAlign' => 'left', 'lineHeight' => 1.6]],
                        ['id' => 'el_media', 'type' => 'image', 'zone' => 'media', 'x' => 1020, 'y' => 80, 'width' => 800, 'height' => 860, 'rotation' => 0, 'zIndex' => 3, 'locked' => false, 'content' => ['src' => '', 'alt' => 'Bild', 'mediaId' => null], 'style' => ['objectFit' => 'cover', 'borderRadius' => 12, 'opacity' => 1]],
                    ],
                ],
            ],
            [
                'name' => 'Bild links',
                'description' => 'Bild links, Text rechts',
                'category' => 'media',
                'layout_key' => 'image-left',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_media', 'type' => 'image', 'zone' => 'media', 'x' => 100, 'y' => 80, 'width' => 800, 'height' => 860, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['src' => '', 'alt' => 'Bild', 'mediaId' => null], 'style' => ['objectFit' => 'cover', 'borderRadius' => 12, 'opacity' => 1]],
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => 960, 'y' => 80, 'width' => 860, 'height' => 140, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<h2>Überschrift</h2>', 'plainText' => 'Überschrift'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 48, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'left', 'lineHeight' => 1.2]],
                        ['id' => 'el_body', 'type' => 'text', 'zone' => 'body', 'x' => 960, 'y' => 260, 'width' => 860, 'height' => 700, 'rotation' => 0, 'zIndex' => 3, 'locked' => false, 'content' => ['html' => '<p>Text</p>', 'plainText' => 'Text'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 28, 'fontWeight' => '400', 'color' => '#333333', 'textAlign' => 'left', 'lineHeight' => 1.6]],
                    ],
                ],
            ],
            [
                'name' => 'Vollbild',
                'description' => 'Vollbild-Bild + Textoverlay',
                'category' => 'media',
                'layout_key' => 'image-full',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_media', 'type' => 'image', 'zone' => 'media', 'x' => 0, 'y' => 0, 'width' => 1920, 'height' => 1080, 'rotation' => 0, 'zIndex' => 1, 'locked' => true, 'content' => ['src' => '', 'alt' => 'Hintergrundbild', 'mediaId' => null], 'style' => ['objectFit' => 'cover', 'borderRadius' => 0, 'opacity' => 1]],
                        ['id' => 'el_overlay_title', 'type' => 'text', 'zone' => 'overlay_title', 'x' => 100, 'y' => 660, 'width' => 1720, 'height' => 180, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<h1>Titel</h1>', 'plainText' => 'Titel'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 72, 'fontWeight' => '700', 'color' => '#ffffff', 'textAlign' => 'left', 'lineHeight' => 1.2]],
                        ['id' => 'el_overlay_text', 'type' => 'text', 'zone' => 'overlay_text', 'x' => 100, 'y' => 860, 'width' => 1720, 'height' => 120, 'rotation' => 0, 'zIndex' => 3, 'locked' => false, 'content' => ['html' => '<p>Beschreibung</p>', 'plainText' => 'Beschreibung'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 32, 'fontWeight' => '400', 'color' => '#ffffffcc', 'textAlign' => 'left', 'lineHeight' => 1.4]],
                    ],
                ],
                'background' => ['type' => 'color', 'value' => '#1a1a2e'],
            ],
            [
                'name' => 'Zitat',
                'description' => 'Zitat mit Autor',
                'category' => 'content',
                'layout_key' => 'quote',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_quote', 'type' => 'text', 'zone' => 'quote', 'x' => 200, 'y' => 220, 'width' => 1520, 'height' => 450, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<blockquote>&ldquo;Zitat hier einfügen&rdquo;</blockquote>', 'plainText' => 'Zitat hier einfügen'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 44, 'fontWeight' => '400', 'color' => '#1a1a2e', 'textAlign' => 'center', 'lineHeight' => 1.5, 'fontStyle' => 'italic']],
                        ['id' => 'el_author', 'type' => 'text', 'zone' => 'author', 'x' => 200, 'y' => 720, 'width' => 1520, 'height' => 100, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<p>— Autor</p>', 'plainText' => '— Autor'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 28, 'fontWeight' => '500', 'color' => '#666666', 'textAlign' => 'center', 'lineHeight' => 1.4]],
                    ],
                ],
            ],
            [
                'name' => 'Kennzahlen',
                'description' => '3-4 Kennzahlen nebeneinander',
                'category' => 'content',
                'layout_key' => 'stats',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => 100, 'y' => 80, 'width' => 1720, 'height' => 140, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h2>Kennzahlen</h2>', 'plainText' => 'Kennzahlen'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 52, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'center', 'lineHeight' => 1.2]],
                        ['id' => 'el_stat_1', 'type' => 'text', 'zone' => 'stat_1', 'x' => 100, 'y' => 320, 'width' => 400, 'height' => 380, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<p class="stat-value">100+</p><p class="stat-label">Label</p>', 'plainText' => "100+\nLabel"], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 96, 'fontWeight' => '700', 'color' => '#0f3460', 'textAlign' => 'center', 'lineHeight' => 1.4]],
                        ['id' => 'el_stat_2', 'type' => 'text', 'zone' => 'stat_2', 'x' => 540, 'y' => 320, 'width' => 400, 'height' => 380, 'rotation' => 0, 'zIndex' => 3, 'locked' => false, 'content' => ['html' => '<p class="stat-value">50%</p><p class="stat-label">Label</p>', 'plainText' => "50%\nLabel"], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 96, 'fontWeight' => '700', 'color' => '#0f3460', 'textAlign' => 'center', 'lineHeight' => 1.4]],
                        ['id' => 'el_stat_3', 'type' => 'text', 'zone' => 'stat_3', 'x' => 980, 'y' => 320, 'width' => 400, 'height' => 380, 'rotation' => 0, 'zIndex' => 4, 'locked' => false, 'content' => ['html' => '<p class="stat-value">24/7</p><p class="stat-label">Label</p>', 'plainText' => "24/7\nLabel"], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 96, 'fontWeight' => '700', 'color' => '#0f3460', 'textAlign' => 'center', 'lineHeight' => 1.4]],
                        ['id' => 'el_stat_4', 'type' => 'text', 'zone' => 'stat_4', 'x' => 1420, 'y' => 320, 'width' => 400, 'height' => 380, 'rotation' => 0, 'zIndex' => 5, 'locked' => false, 'content' => ['html' => '<p class="stat-value">#1</p><p class="stat-label">Label</p>', 'plainText' => "#1\nLabel"], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 96, 'fontWeight' => '700', 'color' => '#0f3460', 'textAlign' => 'center', 'lineHeight' => 1.4]],
                    ],
                ],
            ],
            [
                'name' => 'Abschluss',
                'description' => 'Abschluss-Slide (Danke/Kontakt)',
                'category' => 'closing',
                'layout_key' => 'closing',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => 100, 'y' => 260, 'width' => 1720, 'height' => 260, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h1>Vielen Dank!</h1>', 'plainText' => 'Vielen Dank!'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 84, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'center', 'lineHeight' => 1.2]],
                        ['id' => 'el_subtitle', 'type' => 'text', 'zone' => 'subtitle', 'x' => 300, 'y' => 540, 'width' => 1320, 'height' => 100, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<p>Fragen?</p>', 'plainText' => 'Fragen?'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 36, 'fontWeight' => '400', 'color' => '#666666', 'textAlign' => 'center', 'lineHeight' => 1.4]],
                        ['id' => 'el_contact', 'type' => 'text', 'zone' => 'contact', 'x' => 300, 'y' => 700, 'width' => 1320, 'height' => 200, 'rotation' => 0, 'zIndex' => 3, 'locked' => false, 'content' => ['html' => '<p>name@example.com</p>', 'plainText' => 'name@example.com'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 26, 'fontWeight' => '400', 'color' => '#0f3460', 'textAlign' => 'center', 'lineHeight' => 1.6]],
                    ],
                ],
            ],
        ];
    }
}
