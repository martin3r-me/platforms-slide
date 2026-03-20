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
    // --- Spacing Constants (1920x1080 slide) ---
    public const MARGIN_X = 100;
    public const MARGIN_Y = 80;
    public const CONTENT_WIDTH = 1720; // 1920 - 2*MARGIN_X
    public const CONTENT_HEIGHT = 920; // 1080 - 2*MARGIN_Y
    public const GAP = 40;
    public const TITLE_HEIGHT = 100;
    public const TITLE_Y = 60;
    public const BODY_Y = 200; // TITLE_Y + TITLE_HEIGHT + GAP
    public const BODY_HEIGHT = 820; // 1080 - BODY_Y - MARGIN_Y

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
        'stat_1_value' => 'stats_number',
        'stat_1_label' => 'stats_label',
        'stat_2_value' => 'stats_number',
        'stat_2_label' => 'stats_label',
        'stat_3_value' => 'stats_number',
        'stat_3_label' => 'stats_label',
        'stat_4_value' => 'stats_number',
        'stat_4_label' => 'stats_label',
        'contact' => 'contact',
        'card_left' => 'body',
        'card_right' => 'body',
        'agenda' => 'body',
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
     * Parse a col_ratio string like "60:40" into a [left, right] percentage array.
     * Returns null if the format is invalid.
     */
    public static function parseColRatio(string $ratio): ?array
    {
        if (!preg_match('/^(\d{1,2}|100):(\d{1,2}|100)$/', $ratio, $matches)) {
            return null;
        }

        $left = (int) $matches[1];
        $right = (int) $matches[2];

        if ($left + $right !== 100 || $left < 10 || $right < 10) {
            return null;
        }

        return [$left, $right];
    }

    /**
     * Apply a column ratio to a two-column layout content structure.
     * Recalculates x, width for col_left and col_right elements.
     * Stores the ratio in content['col_ratio'] for reference.
     *
     * Layout constants (1920px slide width):
     *   - Content margin: 80px each side
     *   - Total content width: 1760px
     *   - Gap between columns: 40px
     *   - Available column space: 1720px (1760 - 40)
     */
    public static function applyColRatio(array $content, string $ratio): array
    {
        $parsed = self::parseColRatio($ratio);
        if (!$parsed || empty($content['elements'])) {
            return $content;
        }

        [$leftPct, $rightPct] = $parsed;

        $marginLeft = 80;
        $totalWidth = 1760;
        $gap = 40;
        $availableWidth = $totalWidth - $gap;

        $leftWidth = (int) round($availableWidth * $leftPct / 100);
        $rightWidth = $availableWidth - $leftWidth;

        $leftX = $marginLeft;
        $rightX = $marginLeft + $leftWidth + $gap;

        foreach ($content['elements'] as &$element) {
            $zone = $element['zone'] ?? null;
            if ($zone === 'col_left') {
                $element['x'] = $leftX;
                $element['width'] = $leftWidth;
            } elseif ($zone === 'col_right') {
                $element['x'] = $rightX;
                $element['width'] = $rightWidth;
            }
        }

        $content['col_ratio'] = $ratio;

        return $content;
    }

    /**
     * Returns all 16 system layout definitions.
     * Uses spacing constants for consistent margins/gaps across templates.
     */
    public static function systemLayouts(): array
    {
        $M = self::MARGIN_X;  // 100
        $W = self::CONTENT_WIDTH; // 1720
        $G = self::GAP; // 40

        return [
            // --- TITLE SLIDES ---
            [
                'name' => 'Titel zentriert',
                'description' => 'Titel zentriert + Untertitel',
                'category' => 'title',
                'layout_key' => 'title-center',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => $M, 'y' => 300, 'width' => $W, 'height' => 260, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h1>Titel</h1>', 'plainText' => 'Titel'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 84, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'center', 'lineHeight' => 1.2, 'letterSpacing' => -1]],
                        ['id' => 'el_subtitle', 'type' => 'text', 'zone' => 'subtitle', 'x' => 300, 'y' => 580, 'width' => 1320, 'height' => 120, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<p>Untertitel</p>', 'plainText' => 'Untertitel'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 36, 'fontWeight' => '400', 'color' => '#666666', 'textAlign' => 'center', 'lineHeight' => 1.4]],
                    ],
                ],
            ],
            [
                'name' => 'Titel zentriert (dunkel)',
                'description' => 'Titel zentriert, dunkler Hintergrund',
                'category' => 'title',
                'layout_key' => 'title-center-dark',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => $M, 'y' => 300, 'width' => $W, 'height' => 260, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h1>Titel</h1>', 'plainText' => 'Titel'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 84, 'fontWeight' => '700', 'color' => '#ffffff', 'textAlign' => 'center', 'lineHeight' => 1.2, 'letterSpacing' => -1]],
                        ['id' => 'el_subtitle', 'type' => 'text', 'zone' => 'subtitle', 'x' => 300, 'y' => 580, 'width' => 1320, 'height' => 120, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<p>Untertitel</p>', 'plainText' => 'Untertitel'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 36, 'fontWeight' => '400', 'color' => '#ffffffaa', 'textAlign' => 'center', 'lineHeight' => 1.4]],
                    ],
                ],
                'background' => ['type' => 'color', 'value' => '#1a1a2e'],
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
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => $M, 'y' => 280, 'width' => 800, 'height' => 300, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h1>Titel</h1>', 'plainText' => 'Titel'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 80, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'left', 'lineHeight' => 1.2, 'letterSpacing' => -1]],
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
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => $M, 'y' => 360, 'width' => $W, 'height' => 240, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h1>Kapitel</h1>', 'plainText' => 'Kapitel'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 80, 'fontWeight' => '800', 'color' => '#ffffff', 'textAlign' => 'center', 'lineHeight' => 1.1, 'textShadow' => '0 2px 12px rgba(0,0,0,0.3)']],
                    ],
                ],
                'background' => ['type' => 'gradient', 'value' => ['direction' => 'to-br', 'stops' => ['#667eea', '#764ba2']]],
            ],

            // --- CONTENT SLIDES ---
            [
                'name' => 'Text-Inhalt',
                'description' => 'Überschrift mit Akzentlinie + Textblock',
                'category' => 'content',
                'layout_key' => 'content-text',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => $M, 'y' => self::TITLE_Y, 'width' => $W, 'height' => self::TITLE_HEIGHT, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h2>Überschrift</h2>', 'plainText' => 'Überschrift'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 52, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'left', 'lineHeight' => 1.2]],
                        // Accent bar under title
                        ['id' => 'el_accent', 'type' => 'text', 'zone' => null, 'x' => $M, 'y' => 168, 'width' => 80, 'height' => 6, 'rotation' => 0, 'zIndex' => 2, 'locked' => true, 'content' => ['html' => '', 'plainText' => ''], 'style' => ['backgroundColor' => '#0f3460', 'borderRadius' => 3]],
                        ['id' => 'el_body', 'type' => 'text', 'zone' => 'body', 'x' => $M, 'y' => self::BODY_Y, 'width' => $W, 'height' => self::BODY_HEIGHT, 'rotation' => 0, 'zIndex' => 3, 'locked' => false, 'content' => ['html' => '<p>Textinhalt</p>', 'plainText' => 'Textinhalt'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 32, 'fontWeight' => '400', 'color' => '#333333', 'textAlign' => 'left', 'lineHeight' => 1.6]],
                    ],
                ],
            ],
            [
                'name' => 'Aufzählung',
                'description' => 'Überschrift mit Akzentlinie + Aufzählungsliste',
                'category' => 'content',
                'layout_key' => 'content-bullets',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => $M, 'y' => self::TITLE_Y, 'width' => $W, 'height' => self::TITLE_HEIGHT, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h2>Überschrift</h2>', 'plainText' => 'Überschrift'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 52, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'left', 'lineHeight' => 1.2]],
                        ['id' => 'el_accent', 'type' => 'text', 'zone' => null, 'x' => $M, 'y' => 168, 'width' => 80, 'height' => 6, 'rotation' => 0, 'zIndex' => 2, 'locked' => true, 'content' => ['html' => '', 'plainText' => ''], 'style' => ['backgroundColor' => '#0f3460', 'borderRadius' => 3]],
                        ['id' => 'el_bullets', 'type' => 'text', 'zone' => 'bullets', 'x' => $M, 'y' => self::BODY_Y, 'width' => $W, 'height' => self::BODY_HEIGHT, 'rotation' => 0, 'zIndex' => 3, 'locked' => false, 'content' => ['html' => '<ul><li>Punkt 1</li><li>Punkt 2</li><li>Punkt 3</li></ul>', 'plainText' => "Punkt 1\nPunkt 2\nPunkt 3"], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 30, 'fontWeight' => '400', 'color' => '#333333', 'textAlign' => 'left', 'lineHeight' => 1.8]],
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
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => $M, 'y' => self::TITLE_Y, 'width' => $W, 'height' => self::TITLE_HEIGHT, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h2>Überschrift</h2>', 'plainText' => 'Überschrift'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 52, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'left', 'lineHeight' => 1.2]],
                        ['id' => 'el_col_left', 'type' => 'text', 'zone' => 'col_left', 'x' => $M, 'y' => self::BODY_Y, 'width' => 840, 'height' => self::BODY_HEIGHT, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<p>Linke Spalte</p>', 'plainText' => 'Linke Spalte'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 28, 'fontWeight' => '400', 'color' => '#333333', 'textAlign' => 'left', 'lineHeight' => 1.6]],
                        ['id' => 'el_col_right', 'type' => 'text', 'zone' => 'col_right', 'x' => $M + 840 + $G, 'y' => self::BODY_Y, 'width' => 840, 'height' => self::BODY_HEIGHT, 'rotation' => 0, 'zIndex' => 3, 'locked' => false, 'content' => ['html' => '<p>Rechte Spalte</p>', 'plainText' => 'Rechte Spalte'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 28, 'fontWeight' => '400', 'color' => '#333333', 'textAlign' => 'left', 'lineHeight' => 1.6]],
                    ],
                ],
            ],
            [
                'name' => 'Vergleich',
                'description' => 'Zwei Spalten mit eigenen Überschriften',
                'category' => 'content',
                'layout_key' => 'comparison',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => $M, 'y' => self::TITLE_Y, 'width' => $W, 'height' => self::TITLE_HEIGHT, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h2>Vergleich</h2>', 'plainText' => 'Vergleich'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 52, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'center', 'lineHeight' => 1.2]],
                        ['id' => 'el_col_left', 'type' => 'text', 'zone' => 'col_left', 'x' => $M, 'y' => self::BODY_Y, 'width' => 840, 'height' => self::BODY_HEIGHT, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<h2>Option A</h2><p>Beschreibung</p>', 'plainText' => "Option A\nBeschreibung"], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 28, 'fontWeight' => '400', 'color' => '#333333', 'textAlign' => 'left', 'lineHeight' => 1.6, 'backgroundColor' => '#f5f7fa', 'padding' => 40, 'borderRadius' => 16]],
                        ['id' => 'el_col_right', 'type' => 'text', 'zone' => 'col_right', 'x' => $M + 840 + $G, 'y' => self::BODY_Y, 'width' => 840, 'height' => self::BODY_HEIGHT, 'rotation' => 0, 'zIndex' => 3, 'locked' => false, 'content' => ['html' => '<h2>Option B</h2><p>Beschreibung</p>', 'plainText' => "Option B\nBeschreibung"], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 28, 'fontWeight' => '400', 'color' => '#333333', 'textAlign' => 'left', 'lineHeight' => 1.6, 'backgroundColor' => '#f5f7fa', 'padding' => 40, 'borderRadius' => 16]],
                    ],
                ],
            ],
            [
                'name' => 'Cards',
                'description' => 'Zwei Cards nebeneinander',
                'category' => 'content',
                'layout_key' => 'content-cards',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => $M, 'y' => self::TITLE_Y, 'width' => $W, 'height' => self::TITLE_HEIGHT, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h2>Überschrift</h2>', 'plainText' => 'Überschrift'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 52, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'left', 'lineHeight' => 1.2]],
                        ['id' => 'el_card_left', 'type' => 'text', 'zone' => 'card_left', 'x' => $M, 'y' => self::BODY_Y, 'width' => 840, 'height' => self::BODY_HEIGHT, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<h2>Card 1</h2><p>Inhalt der ersten Card</p>', 'plainText' => "Card 1\nInhalt der ersten Card"], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 28, 'fontWeight' => '400', 'color' => '#1a1a2e', 'textAlign' => 'left', 'lineHeight' => 1.6, 'backgroundColor' => '#f0f4ff', 'padding' => 48, 'borderRadius' => 20]],
                        ['id' => 'el_card_right', 'type' => 'text', 'zone' => 'card_right', 'x' => $M + 840 + $G, 'y' => self::BODY_Y, 'width' => 840, 'height' => self::BODY_HEIGHT, 'rotation' => 0, 'zIndex' => 3, 'locked' => false, 'content' => ['html' => '<h2>Card 2</h2><p>Inhalt der zweiten Card</p>', 'plainText' => "Card 2\nInhalt der zweiten Card"], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 28, 'fontWeight' => '400', 'color' => '#1a1a2e', 'textAlign' => 'left', 'lineHeight' => 1.6, 'backgroundColor' => '#f0f4ff', 'padding' => 48, 'borderRadius' => 20]],
                    ],
                ],
            ],
            [
                'name' => 'Agenda',
                'description' => 'Nummerierte Agenda mit Akzentlinie',
                'category' => 'content',
                'layout_key' => 'agenda',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => $M, 'y' => self::TITLE_Y, 'width' => $W, 'height' => self::TITLE_HEIGHT, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h2>Agenda</h2>', 'plainText' => 'Agenda'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 52, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'left', 'lineHeight' => 1.2]],
                        // Accent bar left of agenda list
                        ['id' => 'el_accent', 'type' => 'text', 'zone' => null, 'x' => $M, 'y' => self::BODY_Y, 'width' => 6, 'height' => self::BODY_HEIGHT, 'rotation' => 0, 'zIndex' => 2, 'locked' => true, 'content' => ['html' => '', 'plainText' => ''], 'style' => ['backgroundColor' => '#0f3460', 'borderRadius' => 3]],
                        ['id' => 'el_agenda', 'type' => 'text', 'zone' => 'agenda', 'x' => $M + 40, 'y' => self::BODY_Y, 'width' => $W - 40, 'height' => self::BODY_HEIGHT, 'rotation' => 0, 'zIndex' => 3, 'locked' => false, 'content' => ['html' => '<ul><li>Einleitung</li><li>Hauptteil</li><li>Zusammenfassung</li><li>Diskussion</li></ul>', 'plainText' => "Einleitung\nHauptteil\nZusammenfassung\nDiskussion"], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 36, 'fontWeight' => '400', 'color' => '#333333', 'textAlign' => 'left', 'lineHeight' => 2.2]],
                    ],
                ],
            ],

            // --- MEDIA SLIDES ---
            [
                'name' => 'Bild rechts',
                'description' => 'Text links, Bild rechts',
                'category' => 'media',
                'layout_key' => 'image-right',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => $M, 'y' => self::MARGIN_Y, 'width' => 840, 'height' => 140, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h2>Überschrift</h2>', 'plainText' => 'Überschrift'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 48, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'left', 'lineHeight' => 1.2]],
                        ['id' => 'el_body', 'type' => 'text', 'zone' => 'body', 'x' => $M, 'y' => 260, 'width' => 840, 'height' => 700, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<p>Text</p>', 'plainText' => 'Text'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 28, 'fontWeight' => '400', 'color' => '#333333', 'textAlign' => 'left', 'lineHeight' => 1.6]],
                        ['id' => 'el_media', 'type' => 'image', 'zone' => 'media', 'x' => $M + 840 + $G, 'y' => self::MARGIN_Y, 'width' => 840, 'height' => self::CONTENT_HEIGHT, 'rotation' => 0, 'zIndex' => 3, 'locked' => false, 'content' => ['src' => '', 'alt' => 'Bild', 'mediaId' => null], 'style' => ['objectFit' => 'cover', 'borderRadius' => 12, 'opacity' => 1]],
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
                        ['id' => 'el_media', 'type' => 'image', 'zone' => 'media', 'x' => $M, 'y' => self::MARGIN_Y, 'width' => 840, 'height' => self::CONTENT_HEIGHT, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['src' => '', 'alt' => 'Bild', 'mediaId' => null], 'style' => ['objectFit' => 'cover', 'borderRadius' => 12, 'opacity' => 1]],
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => $M + 840 + $G, 'y' => self::MARGIN_Y, 'width' => 840, 'height' => 140, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<h2>Überschrift</h2>', 'plainText' => 'Überschrift'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 48, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'left', 'lineHeight' => 1.2]],
                        ['id' => 'el_body', 'type' => 'text', 'zone' => 'body', 'x' => $M + 840 + $G, 'y' => 260, 'width' => 840, 'height' => 700, 'rotation' => 0, 'zIndex' => 3, 'locked' => false, 'content' => ['html' => '<p>Text</p>', 'plainText' => 'Text'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 28, 'fontWeight' => '400', 'color' => '#333333', 'textAlign' => 'left', 'lineHeight' => 1.6]],
                    ],
                ],
            ],
            [
                'name' => 'Vollbild',
                'description' => 'Vollbild-Bild + Textoverlay mit Schatten',
                'category' => 'media',
                'layout_key' => 'image-full',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_media', 'type' => 'image', 'zone' => 'media', 'x' => 0, 'y' => 0, 'width' => 1920, 'height' => 1080, 'rotation' => 0, 'zIndex' => 1, 'locked' => true, 'content' => ['src' => '', 'alt' => 'Hintergrundbild', 'mediaId' => null], 'style' => ['objectFit' => 'cover', 'borderRadius' => 0, 'opacity' => 1]],
                        // Semi-transparent overlay for text readability
                        ['id' => 'el_overlay_bg', 'type' => 'text', 'zone' => null, 'x' => 0, 'y' => 600, 'width' => 1920, 'height' => 480, 'rotation' => 0, 'zIndex' => 2, 'locked' => true, 'content' => ['html' => '', 'plainText' => ''], 'style' => ['backgroundColor' => 'rgba(0,0,0,0.5)']],
                        ['id' => 'el_overlay_title', 'type' => 'text', 'zone' => 'overlay_title', 'x' => $M, 'y' => 660, 'width' => $W, 'height' => 180, 'rotation' => 0, 'zIndex' => 3, 'locked' => false, 'content' => ['html' => '<h1>Titel</h1>', 'plainText' => 'Titel'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 72, 'fontWeight' => '700', 'color' => '#ffffff', 'textAlign' => 'left', 'lineHeight' => 1.2, 'textShadow' => '0 2px 8px rgba(0,0,0,0.5)']],
                        ['id' => 'el_overlay_text', 'type' => 'text', 'zone' => 'overlay_text', 'x' => $M, 'y' => 860, 'width' => $W, 'height' => 120, 'rotation' => 0, 'zIndex' => 4, 'locked' => false, 'content' => ['html' => '<p>Beschreibung</p>', 'plainText' => 'Beschreibung'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 32, 'fontWeight' => '400', 'color' => '#ffffffcc', 'textAlign' => 'left', 'lineHeight' => 1.4, 'textShadow' => '0 1px 4px rgba(0,0,0,0.4)']],
                    ],
                ],
                'background' => ['type' => 'color', 'value' => '#1a1a2e'],
            ],

            // --- SPECIAL CONTENT SLIDES ---
            [
                'name' => 'Zitat',
                'description' => 'Zitat mit dekorativem Anführungszeichen',
                'category' => 'content',
                'layout_key' => 'quote',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        // Decorative quote mark
                        ['id' => 'el_deco', 'type' => 'text', 'zone' => null, 'x' => 140, 'y' => 140, 'width' => 200, 'height' => 200, 'rotation' => 0, 'zIndex' => 1, 'locked' => true, 'content' => ['html' => '<p>&ldquo;</p>', 'plainText' => "\u{201C}"], 'style' => ['fontFamily' => 'Georgia', 'fontSize' => 200, 'fontWeight' => '700', 'color' => '#0f346020', 'textAlign' => 'left', 'lineHeight' => 1]],
                        ['id' => 'el_quote', 'type' => 'text', 'zone' => 'quote', 'x' => 200, 'y' => 260, 'width' => 1520, 'height' => 400, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<blockquote>Zitat hier einfügen</blockquote>', 'plainText' => 'Zitat hier einfügen'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 44, 'fontWeight' => '400', 'color' => '#1a1a2e', 'textAlign' => 'center', 'lineHeight' => 1.5, 'fontStyle' => 'italic']],
                        ['id' => 'el_author', 'type' => 'text', 'zone' => 'author', 'x' => 200, 'y' => 720, 'width' => 1520, 'height' => 100, 'rotation' => 0, 'zIndex' => 3, 'locked' => false, 'content' => ['html' => '<p>— Autor</p>', 'plainText' => '— Autor'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 28, 'fontWeight' => '500', 'color' => '#666666', 'textAlign' => 'center', 'lineHeight' => 1.4]],
                    ],
                ],
            ],
            [
                'name' => 'Kennzahlen',
                'description' => '4 Kennzahlen mit Trennlinien',
                'category' => 'content',
                'layout_key' => 'stats',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => $M, 'y' => self::MARGIN_Y, 'width' => $W, 'height' => 140, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h2>Kennzahlen</h2>', 'plainText' => 'Kennzahlen'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 52, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'center', 'lineHeight' => 1.2]],
                        // Stat columns with vertical dividers
                        ['id' => 'el_stat_1_value', 'type' => 'text', 'zone' => 'stat_1_value', 'x' => $M, 'y' => 320, 'width' => 400, 'height' => 180, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<p>100+</p>', 'plainText' => '100+'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 96, 'fontWeight' => '700', 'color' => '#0f3460', 'textAlign' => 'center', 'lineHeight' => 1.2]],
                        ['id' => 'el_stat_1_label', 'type' => 'text', 'zone' => 'stat_1_label', 'x' => $M, 'y' => 510, 'width' => 400, 'height' => 80, 'rotation' => 0, 'zIndex' => 3, 'locked' => false, 'content' => ['html' => '<p>Projekte</p>', 'plainText' => 'Projekte'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 24, 'fontWeight' => '400', 'color' => '#666666', 'textAlign' => 'center', 'lineHeight' => 1.4]],
                        // Divider 1
                        ['id' => 'el_div_1', 'type' => 'text', 'zone' => null, 'x' => 520, 'y' => 340, 'width' => 2, 'height' => 230, 'rotation' => 0, 'zIndex' => 10, 'locked' => true, 'content' => ['html' => '', 'plainText' => ''], 'style' => ['backgroundColor' => '#e0e0e0']],
                        ['id' => 'el_stat_2_value', 'type' => 'text', 'zone' => 'stat_2_value', 'x' => 540, 'y' => 320, 'width' => 400, 'height' => 180, 'rotation' => 0, 'zIndex' => 4, 'locked' => false, 'content' => ['html' => '<p>50%</p>', 'plainText' => '50%'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 96, 'fontWeight' => '700', 'color' => '#0f3460', 'textAlign' => 'center', 'lineHeight' => 1.2]],
                        ['id' => 'el_stat_2_label', 'type' => 'text', 'zone' => 'stat_2_label', 'x' => 540, 'y' => 510, 'width' => 400, 'height' => 80, 'rotation' => 0, 'zIndex' => 5, 'locked' => false, 'content' => ['html' => '<p>Wachstum</p>', 'plainText' => 'Wachstum'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 24, 'fontWeight' => '400', 'color' => '#666666', 'textAlign' => 'center', 'lineHeight' => 1.4]],
                        // Divider 2
                        ['id' => 'el_div_2', 'type' => 'text', 'zone' => null, 'x' => 960, 'y' => 340, 'width' => 2, 'height' => 230, 'rotation' => 0, 'zIndex' => 10, 'locked' => true, 'content' => ['html' => '', 'plainText' => ''], 'style' => ['backgroundColor' => '#e0e0e0']],
                        ['id' => 'el_stat_3_value', 'type' => 'text', 'zone' => 'stat_3_value', 'x' => 980, 'y' => 320, 'width' => 400, 'height' => 180, 'rotation' => 0, 'zIndex' => 6, 'locked' => false, 'content' => ['html' => '<p>24/7</p>', 'plainText' => '24/7'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 96, 'fontWeight' => '700', 'color' => '#0f3460', 'textAlign' => 'center', 'lineHeight' => 1.2]],
                        ['id' => 'el_stat_3_label', 'type' => 'text', 'zone' => 'stat_3_label', 'x' => 980, 'y' => 510, 'width' => 400, 'height' => 80, 'rotation' => 0, 'zIndex' => 7, 'locked' => false, 'content' => ['html' => '<p>Verfügbarkeit</p>', 'plainText' => 'Verfügbarkeit'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 24, 'fontWeight' => '400', 'color' => '#666666', 'textAlign' => 'center', 'lineHeight' => 1.4]],
                        // Divider 3
                        ['id' => 'el_div_3', 'type' => 'text', 'zone' => null, 'x' => 1400, 'y' => 340, 'width' => 2, 'height' => 230, 'rotation' => 0, 'zIndex' => 10, 'locked' => true, 'content' => ['html' => '', 'plainText' => ''], 'style' => ['backgroundColor' => '#e0e0e0']],
                        ['id' => 'el_stat_4_value', 'type' => 'text', 'zone' => 'stat_4_value', 'x' => 1420, 'y' => 320, 'width' => 400, 'height' => 180, 'rotation' => 0, 'zIndex' => 8, 'locked' => false, 'content' => ['html' => '<p>#1</p>', 'plainText' => '#1'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 96, 'fontWeight' => '700', 'color' => '#0f3460', 'textAlign' => 'center', 'lineHeight' => 1.2]],
                        ['id' => 'el_stat_4_label', 'type' => 'text', 'zone' => 'stat_4_label', 'x' => 1420, 'y' => 510, 'width' => 400, 'height' => 80, 'rotation' => 0, 'zIndex' => 9, 'locked' => false, 'content' => ['html' => '<p>Marktführer</p>', 'plainText' => 'Marktführer'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 24, 'fontWeight' => '400', 'color' => '#666666', 'textAlign' => 'center', 'lineHeight' => 1.4]],
                    ],
                ],
            ],

            // --- CLOSING ---
            [
                'name' => 'Abschluss',
                'description' => 'Abschluss-Slide mit Trennlinie',
                'category' => 'closing',
                'layout_key' => 'closing',
                'content' => [
                    'version' => 1,
                    'mode' => 'layout',
                    'elements' => [
                        ['id' => 'el_title', 'type' => 'text', 'zone' => 'title', 'x' => $M, 'y' => 260, 'width' => $W, 'height' => 260, 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'content' => ['html' => '<h1>Vielen Dank!</h1>', 'plainText' => 'Vielen Dank!'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 84, 'fontWeight' => '700', 'color' => '#1a1a2e', 'textAlign' => 'center', 'lineHeight' => 1.2]],
                        ['id' => 'el_subtitle', 'type' => 'text', 'zone' => 'subtitle', 'x' => 300, 'y' => 540, 'width' => 1320, 'height' => 100, 'rotation' => 0, 'zIndex' => 2, 'locked' => false, 'content' => ['html' => '<p>Fragen?</p>', 'plainText' => 'Fragen?'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 36, 'fontWeight' => '400', 'color' => '#666666', 'textAlign' => 'center', 'lineHeight' => 1.4]],
                        // Horizontal divider
                        ['id' => 'el_divider', 'type' => 'text', 'zone' => null, 'x' => 660, 'y' => 670, 'width' => 600, 'height' => 2, 'rotation' => 0, 'zIndex' => 3, 'locked' => true, 'content' => ['html' => '', 'plainText' => ''], 'style' => ['backgroundColor' => '#e0e0e0']],
                        ['id' => 'el_contact', 'type' => 'text', 'zone' => 'contact', 'x' => 300, 'y' => 700, 'width' => 1320, 'height' => 200, 'rotation' => 0, 'zIndex' => 4, 'locked' => false, 'content' => ['html' => '<p>name@example.com</p>', 'plainText' => 'name@example.com'], 'style' => ['fontFamily' => 'Open Sans', 'fontSize' => 26, 'fontWeight' => '400', 'color' => '#0f3460', 'textAlign' => 'center', 'lineHeight' => 1.6]],
                    ],
                ],
            ],
        ];
    }
}
