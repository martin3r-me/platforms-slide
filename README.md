# platforms-slides

Präsentationen (Decks) im **HTML-Folienformat** – geschrieben von Menschen und Agenten, sicher gerendert, live aktualisiert.
Paket `martin3r/platform-slides`, Namespace `Platform\Slides`, Modul-Key `slides`.

## Format

- Eine Folie = **genau ein `<section>`** auf 1920×1080 px (Deck-Größe `4:3`: 1440×1080). Das Theme setzt 128px Innenrand.
- Sprechernotizen: `<aside>` als letztes Kind der Section oder Feld `notes`.
- Gestaltung über das Deck-Theme (`theme_css`, Standard „BHG“, Klassen siehe `slides.guide.GET`) statt Inline-Styles.
- Regeln für Agenten (Ablauf, erlaubte Elemente, Design): `src/Support/Guide.php` bzw. MCP `slides.guide.GET`.

## Sicherheit

- **Sanitizer beim Speichern** (`src/Security/HtmlSanitizer.php`, `CssSanitizer.php`): Allowlist für Elemente/Attribute, entfernt `script`, `iframe`, `object`, `embed`, Formulare, `on*`-Attribute, `javascript:`-URLs, `@import`, `expression()`, `behavior`, `url()` auf fremde Schemata. Unbekannte Elemente werden aufgelöst (Inhalt bleibt). Was entfernt wurde, meldet das Tool als `removed`.
- **Isoliertes Rendering** (`src/Rendering/SlideDocument.php`): jede Folie in `<iframe sandbox="allow-same-origin" srcdoc>` (ohne `allow-scripts`) mit eigener CSP (`default-src 'none'`, Bilder https/eigene/data, Schriften nur Google Fonts). Skalierung per CSS `transform` im Eltern-Layout (`partials/slide-frame`).

## Datenmodell

| Tabelle | Inhalt |
|---|---|
| `slides_decks` | uuid, team_id, title, description, size (`16:9`/`4:3`), theme_css, fonts (json), status (`draft`/`ready`/`archived`), **revision**, owner_user_id, created_by, soft deletes |
| `slides_deck_slides` | uuid, deck_id, slide_key (`[A-Za-z0-9_-]{1,64}`, eindeutig je Deck), position, html, notes, is_hidden, soft deletes |
| `slides_deck_links` | deck_id, linkable_type (Morph-Alias), linkable_id, role – lose Kopplung an beliebige Objekte (z. B. `management_book_meeting`) |

`revision` steigt bei jeder Änderung an Deck oder Folie (`SlidesDeck::bumpRevision()`); offene Ansichten pollen sie (`config('slides.live_poll_seconds')`, Default 3 s) und rendern nur bei Änderung neu.

## MCP-Tools (Präfix `slides.`)

| Tool | Zweck |
|---|---|
| `slides.guide.GET` | Format- und Designregeln, Theme-Klassen, Beispiele – vor dem ersten Schreiben lesen |
| `slides.decks.GET` | Decks des Teams; `id` für ein Deck inkl. Folienübersicht und Links; Filter `linkable_type`+`linkable_id` |
| `slides.decks.POST` | Deck anlegen (optional direkt verknüpfen) |
| `slides.decks.PUT` | Titel, Status, Größe, Theme, Schriften, Owner |
| `slides.decks.DELETE` | Soft-Delete |
| `slides.decks.duplicate.POST` | Deck samt Folien kopieren (Vorlage für die nächste Sitzung) |
| `slides.slides.GET` | Folien mit html und notes |
| `slides.slides.PUT` | **Batch-Upsert** `[{key, html?, notes?, position?, is_hidden?}]` (max. 40) |
| `slides.slides.DELETE` | Folien löschen (keys) |
| `slides.slides.order.PUT` | Reihenfolge setzen |
| `slides.links.GET/POST/DELETE` | Deck ↔ Objekt verknüpfen |

## UI und Routen (Andockpunkte für den UI-Ausbau)

| Route | Komponente | Stand |
|---|---|---|
| `slides.dashboard` (`/slides`) | `slides.dashboard` (`Livewire\Dashboard`) | Liste + „Neues Deck“ |
| `slides.decks.show` (`/slides/decks/{deck}`) | `slides.deck.show` | alle Folien skaliert, Notizen, Live-Poll |
| `slides.decks.present` (`/slides/decks/{deck}/present?slide=n`) | `slides.deck.present` | eine Folie bildschirmfüllend, Pfeiltasten/Leertaste, Live-Poll; Layout `slides::layouts.bare` |
| `slides.decks.pdf` (`/slides/decks/{deck}/pdf`) | `slides.deck.print-view` | Druckansicht (`@page` im Folienformat) |

Offen für den UI-Ausbau: Referentenansicht (Notizen, nächste Folie, Timer), Fragmente/Klickschritte, Editor, serverseitiger PDF-Export, Echtzeit per Broadcast statt Poll, Einbettung in andere Module (Partial `slides::livewire.partials.slide-frame` + `SlideDocument::render()`).

## Tests

`tests/run-in-taiste.sh tests/Feature/SanitizerTest.php tests/Feature/DeckToolsTest.php` (sqlite `:memory:` in der lokalen taiste-Kopie, PHP 8.4).

## Präsentieren (UI)

| Route | Komponente | Inhalt |
|---|---|---|
| `slides.dashboard` (`/slides`) | `slides.dashboard` | Karten mit Vorschau der ersten Folie, Suche (`?q=`), Statusfilter (`?status=`) |
| `slides.decks.show` (`/slides/decks/{deck}?slide=<key>`) | `slides.deck.show` | große Folie, Filmstreifen, Notizen, Eckdaten, Status, Verknüpfungen, Duplizieren |
| `slides.decks.present` (`/slides/decks/{deck}/present?slide=n`) | `slides.deck.present` | Vollbild-Bühne, ←/→/Leertaste/Klick, Klickschritte, F Vollbild, B schwarz, Fortschritt |
| `slides.decks.presenter` (`/slides/decks/{deck}/presenter`) | `slides.deck.presenter` | aktuelle + nächste Folie, Notizen, Uhr, Timer, „Präsentation öffnen“ |
| `slides.decks.pdf` (`/slides/decks/{deck}/pdf?print=1`) | `slides.deck.print-view` | eine Folie pro Seite im Folienformat, `?print=1` öffnet den Druckdialog |
| `slides.decks.pdf.download` | `DeckPdfController` | serverseitiges PDF via spatie/browsershot – nur mit `SLIDES_PDF_SERVER=true`, sonst Weiterleitung auf die Druckansicht |

- **Player** (`partials/player-script`): alle Folien als gestapelte iframes (sandbox ohne Skripte), Navigation im Browser ohne Server-Roundtrip. `?slide=n` ist 1-basiert und wird beim Blättern aktualisiert.
- **Klickschritte**: `.fragment` (Zustand vor dem Klick), `.visible`, `.current-fragment`; Reihenfolge `data-fragment-index`, sonst Dokumentreihenfolge. Das Eltern-Fenster setzt die Klassen über `iframe.contentDocument` (`allow-same-origin`), die Folie selbst führt kein Skript aus. Übersicht/Druck rendern statisch (`SlideDocument::render(..., static: true)`), dort sind alle Schritte sichtbar.
- **Sync**: `BroadcastChannel('slides-deck-<uuid>')` – Präsentation und Referentenansicht folgen einander (Navigation, schwarzer Bildschirm); die Referentenansicht fragt beim Öffnen den Stand ab.
- **Live**: `wire:poll` prüft `revision`; Präsentation/Referentenansicht bekommen das Event `slides-updated` (Folien mit Inhalts-Hash) und setzen nur geänderte iframes neu – Position und Klickschritt bleiben. Die Deck-Ansicht verschlüsselt Folien per Hash (`wire:key`). `last_editor_user_id`/`last_edited_at` zeigen „aktualisiert von … vor …“; Tools setzen den User aus dem ToolContext.
