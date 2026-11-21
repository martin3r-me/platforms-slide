<?php

namespace Platform\Slides\Support;

use Platform\Slides\Themes\BhgTheme;

/**
 * Format- und Designregeln für Agenten, die Decks schreiben (slides.guide.GET).
 */
class Guide
{
    public static function forAgents(): array
    {
        return [
            'workflow' => [
                '1. Deck anlegen: slides.decks.POST (title; Theme „BHG“ ist Standard). Optional mit Gremium/Sitzung verknüpfen: slides.links.POST.',
                '2. Gliederung festlegen: eine Aussage pro Folie, Abschnitte mit Kapitelfolien, Reihenfolge = Erzählung.',
                '3. Folien schreiben: slides.slides.PUT mit mehreren Folien pro Aufruf ({key, html, notes}). key ist stabil (z. B. "cover", "agenda", "kpis-2027").',
                '4. Ändern: dieselbe key erneut senden (nur html oder nur notes genügt). Reihenfolge: slides.slides.order.PUT. Löschen: slides.slides.DELETE.',
                '5. Die Antwort nennt je Folie, was der Sanitizer entfernt hat – beim nächsten Mal vermeiden.',
            ],
            'format' => [
                'Eine Folie = genau ein <section> auf 1920×1080 px (Deck-Größe 4:3: 1440×1080). Das Theme setzt 128px Innenrand (nutzbar 1664×824).',
                'Sprechernotizen als Klartext in einem <aside> als letztes Kind der Section ODER im Feld notes.',
                'Gestalten über Theme-Klassen, nicht über Inline-Styles. Inline-style nur für Einzelfälle (z. B. Breite einer Spalte).',
                'Kinder fließen (Flex-Spalte mit 40px Abstand). position:absolute nur für Fußzeile oder Dekor, mit fester Breite.',
                'Keine Skripte, keine Formulare, keine iframes/object/embed, keine Event-Attribute – sie werden samt Inhalt entfernt (bei embed/object auch nachfolgender Inhalt); die Folie rendert ohne JavaScript.',
                'Bilder: <img src="https://…" alt="…" width height> (oder kleine data:image-URIs). Diagramme als inline <svg aria-label="…">.',
            ],
            'allowed_elements' => 'section, div, span, p, h1–h6, ul, ol, li, br, b, i, u, em, strong, small, sup, sub, mark, code, pre, a (http/https/mailto/tel/#), img, table/thead/tbody/tfoot/tr/th/td/caption/col/colgroup, hr, figure, figcaption, blockquote, cite, header, footer, aside (Notizen), svg (+ g, path, rect, circle, ellipse, line, polyline, polygon, text, tspan, defs, linearGradient, radialGradient, stop, clipPath, mask, pattern, marker, title, desc, use (#ids), symbol, animate*, mpath).',
            'allowed_attributes' => 'class, style, role, title, lang, dir, data-*, aria-*; a: href,target; img: src,alt,width,height,loading; td/th: colspan,rowspan,scope; section: id (wird auf key gesetzt); SVG: Geometrie- und Präsentationsattribute.',
            'design' => [
                'Eine Idee pro Folie. Lieber eine klare Aussage als fünf Spiegelstriche; Listen in Tabellen, Kacheln, große Zahlen oder Zitate verwandeln.',
                'Überschriften in einer Grammatik: entweder alle als Thema („Planzahlen 2027“) oder alle als Aussage („Wir investieren 2027 in Wachstum“).',
                'Typo-Skala des Themes nutzen (h1 96px, h2 64px, h3 36px, Fließtext 32px, .lead 40px); nichts unter 24px.',
                'Platz: 824px Höhe innerhalb der Ränder. Passt es nicht, Folie teilen – nichts verkleinern.',
                'Farbe nur bedeutungstragend (Akzent für das Wichtigste, .tag.good/.warn/.bad für Ampeln). Kontrast mindestens 4.5:1.',
                'Wiederkehrende Folienarten teilen Markup und Position (Überschrift immer oben links), damit nichts springt.',
                'Fußzeile (Quelle, Seitenzahl) als <p class="footer"> – nichts anderes unterhalb von y=920.',
                'Zahlen, Namen und Zitate nur aus Quellen übernehmen – nie erfinden. Fehlt etwas: Platzhalter in eckigen Klammern, z. B. [Umsatz 2026].',
            ],
            'theme' => [
                'key' => BhgTheme::KEY,
                'fonts' => BhgTheme::fonts(),
                'classes' => BhgTheme::classes(),
            ],
            'example' => '<section class="title"><p class="eyebrow">Strategiemeeting · 17.11.2026</p><h1>BHG.DIGITAL 2027</h1><p class="lead">Wo wir stehen, wohin wir gehen</p><aside>Begrüßung, Ziel des Termins: Richtung für 2027 vereinbaren.</aside></section>',
            'example_kpis' => '<section><p class="eyebrow">Planzahlen 2027</p><h2>Wir investieren in Wachstum</h2><div class="kpis"><div class="kpi"><p class="value">800 T€</p><p class="label">Umsatzziel</p></div><div class="kpi"><p class="value">670 T€</p><p class="label">Kosten</p></div></div><p class="footer"><span>Quelle: Planung 2027</span><span>7</span></p></section>',
        ];
    }
}
