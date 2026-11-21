<?php

namespace Platform\Slides\Security;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Bereinigt Folien-HTML (Agenten-HTML ist nicht vertrauenswürdig) nach einer Allowlist.
 *
 * Ergebnis ist immer genau ein <section id="<slide_key>">. Ein <aside> direkt in der Section wird als
 * Sprechernotiz (Klartext) herausgelöst. Alles Entfernte wird als Hinweis gemeldet, damit Agenten lernen.
 * Zweite Verteidigungslinie: Folien rendern in einem iframe ohne allow-scripts mit restriktiver CSP.
 */
class HtmlSanitizer
{
    public const MAX_HTML_BYTES = 200_000;

    public const MAX_DATA_URI_BYTES = 512_000;

    /** Elemente samt Inhalt entfernen. */
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button',
        'textarea', 'select', 'option', 'link', 'meta', 'base', 'template', 'noscript', 'foreignobject', 'audio',
        'video', 'source', 'track', 'canvas', 'dialog', 'portal', 'head', 'title-html',
    ];

    private const HTML_ELEMENTS = [
        'section', 'div', 'span', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'br', 'b', 'i', 'u',
        'em', 'strong', 'small', 'sup', 'sub', 'mark', 'code', 'pre', 'a', 'img', 'table', 'thead', 'tbody',
        'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col', 'hr', 'figure', 'figcaption', 'blockquote', 'cite',
        'header', 'footer', 'aside',
    ];

    /** SVG-Elemente (vom HTML-Parser kleingeschrieben). */
    private const SVG_ELEMENTS = [
        'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'tspan', 'defs',
        'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask', 'pattern', 'marker', 'title', 'desc', 'use',
        'symbol', 'animate', 'animatetransform', 'animatemotion', 'mpath',
    ];

    private const GLOBAL_ATTRS = ['class', 'style', 'role', 'title', 'lang', 'dir'];

    private const ELEMENT_ATTRS = [
        'a' => ['href', 'target'],
        'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
        'col' => ['span'],
        'colgroup' => ['span'],
        'ol' => ['start', 'reversed', 'type'],
        'section' => ['id'],
    ];

    private const SVG_ATTRS = [
        'id', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'fx', 'fy', 'd', 'points', 'width',
        'height', 'viewbox', 'preserveaspectratio', 'transform', 'fill', 'fill-opacity', 'fill-rule', 'stroke',
        'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray', 'stroke-dashoffset',
        'stroke-opacity', 'stroke-miterlimit', 'opacity', 'font-size', 'font-family', 'font-weight', 'font-style',
        'text-anchor', 'dominant-baseline', 'alignment-baseline', 'letter-spacing', 'offset', 'stop-color',
        'stop-opacity', 'gradientunits', 'gradienttransform', 'spreadmethod', 'clip-path', 'clip-rule', 'mask',
        'marker-start', 'marker-mid', 'marker-end', 'markerwidth', 'markerheight', 'markerunits', 'refx', 'refy',
        'orient', 'dx', 'dy', 'rotate', 'patternunits', 'patterntransform', 'href', 'xlink:href', 'xmlns',
        'xmlns:xlink', 'version', 'attributename', 'attributetype', 'begin', 'dur', 'end', 'repeatcount',
        'repeatdur', 'values', 'from', 'to', 'by', 'keytimes', 'keysplines', 'keypoints', 'calcmode', 'path',
        'type', 'additive', 'accumulate', 'restart', 'pathlength', 'vector-effect', 'paint-order', 'visibility',
        'display', 'overflow', 'textlength', 'lengthadjust',
    ];

    /** libxml schreibt alles klein – für SVG wieder in die korrekte Schreibweise bringen. */
    private const SVG_CASE = [
        'viewbox' => 'viewBox', 'preserveaspectratio' => 'preserveAspectRatio', 'gradientunits' => 'gradientUnits',
        'gradienttransform' => 'gradientTransform', 'spreadmethod' => 'spreadMethod', 'markerwidth' => 'markerWidth',
        'markerheight' => 'markerHeight', 'markerunits' => 'markerUnits', 'refx' => 'refX', 'refy' => 'refY',
        'patternunits' => 'patternUnits', 'patterntransform' => 'patternTransform', 'attributename' => 'attributeName',
        'attributetype' => 'attributeType', 'repeatcount' => 'repeatCount', 'repeatdur' => 'repeatDur',
        'keytimes' => 'keyTimes', 'keysplines' => 'keySplines', 'keypoints' => 'keyPoints', 'calcmode' => 'calcMode',
        'pathlength' => 'pathLength', 'textlength' => 'textLength', 'lengthadjust' => 'lengthAdjust',
    ];

    private const SVG_TAG_CASE = [
        'lineargradient' => 'linearGradient', 'radialgradient' => 'radialGradient', 'clippath' => 'clipPath',
        'animatetransform' => 'animateTransform', 'animatemotion' => 'animateMotion',
    ];

    /** @var string[] */
    private array $removed = [];

    /**
     * @return array{html:string, notes:?string, removed:string[]}
     */
    public function sanitizeSlide(string $html, string $slideKey): array
    {
        $this->removed = [];

        if (strlen($html) > self::MAX_HTML_BYTES) {
            throw new \InvalidArgumentException('Folien-HTML ist größer als ' . (self::MAX_HTML_BYTES / 1000) . ' KB.');
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="utf-8"?><div id="__slides_root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET | LIBXML_COMPACT
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = (new DOMXPath($doc))->query('//div[@id="__slides_root"]')->item(0);
        if (! $root instanceof DOMElement) {
            throw new \InvalidArgumentException('Folien-HTML konnte nicht gelesen werden.');
        }

        // Genau ein <section>: erstes Section-Kind nehmen, sonst alles in eine Section packen.
        $sections = [];
        foreach ($root->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->nodeName) === 'section') {
                $sections[] = $child;
            }
        }
        if ($sections === []) {
            $section = $doc->createElement('section');
            while ($root->firstChild) {
                $section->appendChild($root->firstChild);
            }
            $root->appendChild($section);
        } else {
            $section = $sections[0];
            if (count($sections) > 1) {
                $this->removed[] = (count($sections) - 1) . ' weitere <section> entfernt (eine Folie = genau ein <section>; mehrere Folien per slides.slides.PUT als eigene Einträge).';
            }
            foreach (iterator_to_array($root->childNodes) as $child) {
                if ($child !== $section) {
                    if ($child instanceof DOMElement || trim($child->textContent) !== '') {
                        $this->removed[] = 'Inhalt außerhalb des <section> entfernt.';
                    }
                    $root->removeChild($child);
                }
            }
        }

        // Sprechernotizen: <aside> direkt in der Section
        $notes = null;
        foreach (iterator_to_array($section->childNodes) as $child) {
            if ($child instanceof DOMElement && strtolower($child->nodeName) === 'aside') {
                $text = trim(preg_replace("/[ \t]+/", ' ', $child->textContent));
                $notes = $notes === null ? $text : $notes . "\n\n" . $text;
                $section->removeChild($child);
            }
        }

        $this->cleanElement($section, false);
        $section->setAttribute('id', $slideKey);

        $out = $doc->saveHTML($section);
        $out = $this->restoreSvgCase($out);

        return [
            'html' => $out,
            'notes' => $notes !== null && $notes !== '' ? mb_substr($notes, 0, 8000) : null,
            'removed' => array_values(array_unique($this->removed)),
        ];
    }

    private function cleanElement(DOMElement $el, bool $inSvg): void
    {
        foreach (iterator_to_array($el->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $name = strtolower($child->nodeName);
                $childInSvg = $inSvg || $name === 'svg';

                if (in_array($name, self::DROP_WITH_CONTENT, true)) {
                    $this->removed[] = "Element <{$name}> entfernt.";
                    $el->removeChild($child);
                    continue;
                }

                $allowed = $childInSvg ? in_array($name, self::SVG_ELEMENTS, true) : (in_array($name, self::HTML_ELEMENTS, true) && $name !== 'aside');
                if (! $allowed) {
                    // Unbekanntes Element: Inhalt behalten, Hülle auflösen
                    $this->removed[] = "Element <{$name}> aufgelöst (nicht erlaubt, Inhalt bleibt).";
                    $this->cleanElement($child, $childInSvg);
                    while ($child->firstChild) {
                        $el->insertBefore($child->firstChild, $child);
                    }
                    $el->removeChild($child);
                    continue;
                }

                $this->cleanAttributes($child, $name, $childInSvg);
                if ($name === 'section') {
                    // Verschachtelte Section wird zum <div>
                    $div = $el->ownerDocument->createElement('div');
                    foreach (iterator_to_array($child->attributes) as $attr) {
                        if ($attr->nodeName !== 'id') {
                            $div->setAttribute($attr->nodeName, $attr->nodeValue);
                        }
                    }
                    while ($child->firstChild) {
                        $div->appendChild($child->firstChild);
                    }
                    $el->replaceChild($div, $child);
                    $this->cleanElement($div, $childInSvg);
                    continue;
                }
                $this->cleanElement($child, $childInSvg);
            } elseif ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $el->removeChild($child);
            }
        }

        if ($el->nodeName === 'section') {
            $this->cleanAttributes($el, 'section', false);
        }
    }

    private function cleanAttributes(DOMElement $el, string $name, bool $inSvg): void
    {
        foreach (iterator_to_array($el->attributes) as $attr) {
            $attrName = strtolower($attr->nodeName);
            $value = (string) $attr->nodeValue;

            $permitted = in_array($attrName, self::GLOBAL_ATTRS, true)
                || str_starts_with($attrName, 'data-')
                || str_starts_with($attrName, 'aria-')
                || in_array($attrName, self::ELEMENT_ATTRS[$name] ?? [], true)
                || ($inSvg && in_array($attrName, self::SVG_ATTRS, true));

            if (str_starts_with($attrName, 'on')) {
                $permitted = false;
            }

            if (! $permitted) {
                $this->removed[] = "Attribut {$attrName} an <{$name}> entfernt.";
                $el->removeAttribute($attr->nodeName);
                continue;
            }

            $clean = $this->cleanAttributeValue($name, $attrName, $value, $inSvg);
            if ($clean === null) {
                $this->removed[] = "Wert von {$attrName} an <{$name}> entfernt (nicht erlaubt).";
                $el->removeAttribute($attr->nodeName);
            } elseif ($clean !== $value) {
                $el->setAttribute($attr->nodeName, $clean);
            }
        }

        if ($name === 'a' && $el->hasAttribute('href')) {
            $el->setAttribute('rel', 'noopener noreferrer');
            if ($el->getAttribute('target') !== '' && $el->getAttribute('target') !== '_blank') {
                $el->removeAttribute('target');
            }
        }

        // SMIL darf keine Links/Event-Attribute verändern
        if (in_array($name, ['animate', 'animatetransform', 'animatemotion'], true)) {
            $target = strtolower($el->getAttribute('attributename'));
            if ($target !== '' && (str_contains($target, 'href') || str_starts_with($target, 'on'))) {
                $this->removed[] = "Animation von {$target} entfernt.";
                $el->parentNode?->removeChild($el);
            }
        }
    }

    private function cleanAttributeValue(string $name, string $attr, string $value, bool $inSvg): ?string
    {
        $lower = strtolower(preg_replace('/[\x00-\x20]+/', '', $value));

        if ($attr === 'style') {
            [$css, $removed] = CssSanitizer::sanitizeDeclarations($value);
            foreach ($removed as $r) {
                $this->removed[] = "style an <{$name}>: {$r}";
            }

            return $css === '' ? null : $css;
        }

        if ($attr === 'href' || $attr === 'xlink:href') {
            if ($inSvg && $name !== 'a') {
                return str_starts_with($value, '#') ? $value : null; // <use>/<mpath> nur auf interne Ids
            }

            return preg_match('#^(https?:|mailto:|tel:|\#)#i', $lower) ? $value : null;
        }

        if ($attr === 'src') {
            return $this->isAllowedImageSource($value) ? $value : null;
        }

        if (in_array($attr, ['values', 'from', 'to', 'by'], true) && str_contains($lower, 'javascript:')) {
            return null;
        }

        if (str_contains($lower, 'javascript:') || str_contains($lower, 'vbscript:')) {
            return null;
        }

        return $value;
    }

    private function isAllowedImageSource(string $src): bool
    {
        $s = trim($src);
        if (preg_match('#^https://#i', $s)) {
            return true;
        }
        if (preg_match('#^/(?!/)#', $s)) {
            return true; // eigene Assets (relativ zur Plattform)
        }
        if (preg_match('#^data:image/(png|jpe?g|webp|gif|svg\+xml)(;base64)?,#i', $s)) {
            return strlen($s) <= self::MAX_DATA_URI_BYTES;
        }

        return false;
    }

    private function restoreSvgCase(string $html): string
    {
        $attrPattern = '/(\s)(' . implode('|', array_keys(self::SVG_CASE)) . ')(=)/';

        return preg_replace_callback('/<(\/?)([a-z]+)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/', function ($m) use ($attrPattern) {
            $tag = self::SVG_TAG_CASE[$m[2]] ?? $m[2];
            // Attributnamen nur außerhalb von Werten anpassen: Werte stehen in Anführungszeichen
            $attrs = preg_replace_callback('/("[^"]*"|\'[^\']*\')|' . substr($attrPattern, 1, -1) . '/', function ($a) {
                if (($a[1] ?? '') !== '') {
                    return $a[1];
                }

                return $a[2] . self::SVG_CASE[$a[3]] . $a[4];
            }, $m[3]);

            return '<' . $m[1] . $tag . $attrs . '>';
        }, $html);
    }
}
