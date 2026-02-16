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
                            'theme' => 'Farben und Schriften (JSON)',
                            'slide_width' => 'Breite in Pixel (Standard: 1920)',
                            'slide_height' => 'Höhe in Pixel (Standard: 1080)',
                            'is_published' => 'Veröffentlicht (öffentlich zugänglich)',
                            'folder_id' => 'Optional: Ordner-Zuordnung',
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
                            'stat_1' => 'Kennzahl 1',
                            'stat_2' => 'Kennzahl 2',
                            'stat_3' => 'Kennzahl 3',
                            'stat_4' => 'Kennzahl 4',
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
