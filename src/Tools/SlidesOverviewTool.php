<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;

/**
 * Overview-Tool für das Slides-Modul.
 * Gibt eine strukturierte Übersicht über alle Konzepte zurück.
 */
class SlidesOverviewTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'slides.overview.GET';
    }

    public function getDescription(): string
    {
        return 'GET /slides/overview - Zeigt Übersicht über Slides-Konzepte und Beziehungen. EMPFOHLEN: Nutze dieses Tool, wenn du die Struktur des Slides-Moduls verstehen möchtest (Decks/Präsentationen, Slides, Platzhalter, Templates). REST-Parameter: keine.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            return ToolResult::success([
                'module' => 'slides',
                'concepts' => [
                    'decks' => [
                        'description' => 'Decks (Präsentationen) sind die Haupteinheit. Sie enthalten Slides und gehören einem Team.',
                        'attributes' => [
                            'name' => 'Name der Präsentation',
                            'description' => 'Beschreibung (optional)',
                            'theme' => 'Farben, Schriften und Schriftgrößen (JSON)',
                            'slide_width' => 'Breite in Pixel (Standard: 1920)',
                            'slide_height' => 'Höhe in Pixel (Standard: 1080)',
                            'is_published' => 'Veröffentlicht (öffentlich zugänglich)',
                            'folder_id' => 'Optional: Ordner-Zuordnung',
                        ],
                        'theme_structure' => [
                            'colors' => 'Farbschema: primary, accent, text, background',
                            'fonts' => 'Schriftarten: heading, body',
                            'fontSizes' => [
                                'description' => 'Schriftgrößen in px pro Zone. Werden als Default für neue Slides verwendet. Min: 12px, Max: 200px.',
                                'keys' => [
                                    'title' => 'Titel-Text (Standard: 80)',
                                    'subtitle' => 'Untertitel-Text (Standard: 40)',
                                    'body' => 'Fließtext/Beschreibung (Standard: 32)',
                                    'bullets' => 'Aufzählungsliste (Standard: 30)',
                                    'quote' => 'Zitat-Text (Standard: 42)',
                                    'stats_number' => 'Kennzahl-Wert (Standard: 96)',
                                    'stats_label' => 'Kennzahl-Label (Standard: 24)',
                                    'section_title' => 'Kapitelüberschrift (Standard: 72)',
                                    'contact' => 'Kontaktinfo (Standard: 24)',
                                ],
                            ],
                        ],
                    ],
                    'slides' => [
                        'description' => 'Einzelne Slides innerhalb eines Decks. Jeder Slide hat ein Layout mit Elementen.',
                        'attributes' => [
                            'layout_key' => 'Layout-Template (z.B. title-center, content-text, content-bullets)',
                            'content' => 'JSON mit Elements-Array (Text, Bilder, etc.)',
                            'background' => 'Hintergrund (Farbe oder Gradient)',
                            'transition' => 'Übergangs-Effekt',
                            'notes' => 'Speaker Notes',
                            'sort_order' => 'Reihenfolge im Deck',
                            'is_hidden' => 'Ausgeblendet in Präsentation',
                        ],
                    ],
                    'placeholders' => [
                        'description' => 'Platzhalter (Zones) in Slide-Templates, die dynamisch befüllt werden können.',
                        'how_it_works' => 'Jedes Element mit einer "zone"-Eigenschaft ist ein befüllbarer Platzhalter. Nutze slides.slide.content.PUT um Werte einzusetzen.',
                        'value_formats' => [
                            'string' => 'Einfacher String-Wert: {"title": "Mein Titel"}',
                            'object' => 'Objekt mit Style-Overrides: {"title": {"value": "Mein Titel", "fontSize": 96, "color": "#FF0000", "align": "center"}}',
                        ],
                        'style_overrides' => [
                            'description' => 'Pro Platzhalter können individuelle Styles gesetzt werden, die Theme/Template-Defaults überschreiben.',
                            'allowed_properties' => [
                                'fontSize' => 'Schriftgröße in px (z.B. 96)',
                                'color' => 'Textfarbe als Hex (z.B. #FF0000)',
                                'align' => 'Textausrichtung (left, center, right)',
                                'fontWeight' => 'Schriftstärke (z.B. 400, 700, bold)',
                                'fontStyle' => 'Schriftstil (normal, italic)',
                                'letterSpacing' => 'Buchstabenabstand in px (z.B. 2)',
                                'lineHeight' => 'Zeilenhöhe (z.B. 1.2, 1.5)',
                            ],
                        ],
                        'common_zones' => [
                            'title' => 'Titel-Text',
                            'subtitle' => 'Untertitel-Text',
                            'body' => 'Haupttext/Beschreibung',
                            'bullets' => 'Aufzählungsliste',
                            'quote' => 'Zitat-Text',
                            'author' => 'Autor-Text',
                            'media' => 'Bild (URL)',
                            'col_left' => 'Linke Spalte (Two-Column)',
                            'col_right' => 'Rechte Spalte (Two-Column)',
                            'stat_1_value' => 'Kennzahl 1 – Wert (groß, fett)',
                            'stat_1_label' => 'Kennzahl 1 – Label (klein)',
                            'stat_2_value' => 'Kennzahl 2 – Wert (groß, fett)',
                            'stat_2_label' => 'Kennzahl 2 – Label (klein)',
                            'stat_3_value' => 'Kennzahl 3 – Wert (groß, fett)',
                            'stat_3_label' => 'Kennzahl 3 – Label (klein)',
                            'stat_4_value' => 'Kennzahl 4 – Wert (groß, fett)',
                            'stat_4_label' => 'Kennzahl 4 – Label (klein)',
                            'contact' => 'Kontaktinformationen',
                        ],
                    ],
                    'templates' => [
                        'description' => '12 System-Templates in 4 Kategorien',
                        'categories' => [
                            'title' => ['title-center', 'title-left', 'section-break'],
                            'content' => ['content-text', 'content-bullets', 'two-column', 'quote', 'stats'],
                            'media' => ['image-right', 'image-left', 'image-full'],
                            'closing' => ['closing'],
                        ],
                    ],
                ],
                'workflows' => [
                    'create_deck_with_slides' => [
                        'step_1' => 'Erstelle Deck (slides.decks.POST)',
                        'step_2' => 'Erstelle Slides mit Layout-Templates (slides.slides.POST)',
                        'step_3' => 'Befülle Platzhalter (slides.slide.content.PUT)',
                    ],
                    'fill_existing_slide' => [
                        'step_1' => 'Lade Deck mit Slides (slides.deck.GET)',
                        'step_2' => 'Platzhalter identifizieren (zone-Felder in Elements)',
                        'step_3' => 'Befülle Platzhalter (slides.slide.content.PUT)',
                    ],
                ],
                'related_tools' => [
                    'decks' => [
                        'list' => 'slides.decks.GET - Listet alle Decks/Präsentationen auf',
                        'get' => 'slides.deck.GET - Ruft einzelnes Deck mit allen Slides ab',
                        'create' => 'slides.decks.POST - Erstellt neues Deck',
                        'update' => 'slides.decks.PUT - Aktualisiert Deck',
                        'delete' => 'slides.decks.DELETE - Löscht Deck',
                    ],
                    'slides' => [
                        'list' => 'slides.slides.GET - Listet Slides eines Decks auf',
                        'create' => 'slides.slides.POST - Erstellt neuen Slide',
                        'update' => 'slides.slides.PUT - Aktualisiert Slide',
                        'delete' => 'slides.slides.DELETE - Löscht Slide',
                        'reorder' => 'slides.slides.SORT - Sortiert Slides innerhalb eines Decks',
                    ],
                    'content' => [
                        'fill' => 'slides.slide.content.PUT - Befüllt Platzhalter eines Slides mit Werten',
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Slides-Übersicht: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'overview',
            'tags' => ['slides', 'overview', 'help', 'concepts'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
