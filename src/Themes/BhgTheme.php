<?php

namespace Platform\Slides\Themes;

/**
 * Standard-Theme „BHG“: ruhig, hochwertig, gut lesbar. Warmes Weiß, tiefes Anthrazit, ein gedecktes Akzent-Blau.
 * Folien nutzen Klassen statt Inline-Styles; die Klassen sind in classes() für Agenten dokumentiert.
 */
class BhgTheme
{
    public const KEY = 'bhg';

    /** @return string[] Google-Fonts-Familien */
    public static function fonts(): array
    {
        return ['Inter', 'Source Serif 4'];
    }

    /** @return array<string,string> Klasse → wann nutzen */
    public static function classes(): array
    {
        return [
            'section.title' => 'Titelfolie: <p class="eyebrow">, <h1>, <p class="lead">. Dunkler Grund.',
            'section.chapter' => 'Kapitel-/Abschnittstrenner: <p class="eyebrow">Teil 2</p><h1>…</h1>. Akzent-Grund.',
            'section.statement' => 'Eine Aussage groß in der Mitte: <h1> (optional <p class="lead">).',
            'section (ohne Klasse)' => 'Inhaltsfolie: <h2> oben, darunter Inhalt.',
            '.eyebrow' => 'Kleine Überzeile über einer Überschrift (Versalien, gesperrt).',
            '.lead' => 'Einleitender Satz unter der Überschrift, größer als Fließtext.',
            '.muted' => 'Gedämpfter Text (Quellen, Nebeninfo).',
            '.accent' => 'Text in Akzentfarbe (sparsam, nur für das Wichtigste).',
            '.cols-2 / .cols-3' => 'Spalten-Raster; Kinder sind Blöcke (z. B. <div class="card">).',
            '.card' => 'Kachel mit Haarlinie; <h3> als Titel, darunter <p>.',
            '.kpis + .kpi' => 'Kennzahlenreihe: <div class="kpis"><div class="kpi"><p class="value">800 T€</p><p class="label">Umsatzziel 2027</p></div>…</div>',
            '.quote' => 'Zitat: <blockquote class="quote">…<cite>Name, Rolle</cite></blockquote>',
            'table.data' => 'Tabelle mit Kopfzeile, Zahlen rechtsbündig über <td class="num">.',
            '.footer' => 'Fußzeile unten (Quelle, Seitenzahl): <p class="footer">Quelle: …</p>.',
            '.list-check' => 'Liste mit Haken statt Punkten (Ergebnisse, Vereinbarungen).',
            '.tag / .tag.good / .tag.warn / .tag.bad' => 'Kleine Statusmarke (Ampel nur bedeutungstragend).',
        ];
    }

    public static function css(): string
    {
        return <<<'CSS'
:root {
  --ink: #1f2328;
  --ink-soft: #4b525b;
  --muted: #6b7280;
  --paper: #f7f5f1;
  --paper-2: #eeebe4;
  --line: #d9d4ca;
  --accent: #2f5d8a;
  --accent-soft: #dfe8f1;
  --dark: #1c2430;
  --good: #2f7d4f;
  --warn: #b26a12;
  --bad: #b23b3b;
  --font-text: "Inter", system-ui, sans-serif;
  --font-display: "Source Serif 4", Georgia, serif;
}
section {
  background: var(--paper);
  color: var(--ink);
  font-family: var(--font-text);
  font-size: 32px;
  line-height: 1.45;
  padding: 128px;
  display: flex;
  flex-direction: column;
  gap: 40px;
}
h1, h2, h3 { margin: 0; color: var(--ink); font-weight: 600; letter-spacing: -0.01em; }
h1 { font-family: var(--font-display); font-size: 96px; line-height: 1.05; font-weight: 500; }
h2 { font-family: var(--font-display); font-size: 64px; line-height: 1.1; font-weight: 500; }
h3 { font-size: 36px; line-height: 1.25; }
p { margin: 0; }
ul, ol { margin: 0; padding-left: 1.2em; display: flex; flex-direction: column; gap: 14px; }
li::marker { color: var(--accent); }
.eyebrow { font-size: 24px; font-weight: 600; letter-spacing: 0.14em; text-transform: uppercase; color: var(--accent); }
.lead { font-size: 40px; line-height: 1.35; color: var(--ink-soft); max-width: 1400px; }
.muted { color: var(--muted); }
.accent { color: var(--accent); }
section.title { background: var(--dark); color: #f3f1ec; justify-content: flex-end; }
section.title h1 { color: #f7f5f1; font-size: 112px; }
section.title .lead { color: #c9ced6; }
section.title .eyebrow { color: #9fb7d1; }
section.chapter { background: var(--accent); color: #f7f5f1; justify-content: center; }
section.chapter h1 { color: #ffffff; }
section.chapter .eyebrow { color: #d7e3ef; }
section.statement { justify-content: center; }
section.statement h1 { font-size: 88px; max-width: 1500px; }
.cols-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 48px; }
.cols-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px; }
.card { background: #fbfaf7; border: 1px solid var(--line); border-radius: 14px; padding: 40px; display: flex; flex-direction: column; gap: 16px; }
.card h3 { font-size: 34px; }
.card p { font-size: 28px; color: var(--ink-soft); }
.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 40px; }
.kpi { border-top: 3px solid var(--accent); padding-top: 24px; }
.kpi .value { font-family: var(--font-display); font-size: 88px; line-height: 1; color: var(--ink); }
.kpi .label { margin-top: 12px; font-size: 26px; color: var(--muted); }
.quote { margin: 0; font-family: var(--font-display); font-size: 56px; line-height: 1.25; color: var(--ink); border-left: 6px solid var(--accent); padding-left: 48px; }
.quote cite { display: block; margin-top: 24px; font-family: var(--font-text); font-style: normal; font-size: 26px; color: var(--muted); }
table.data { border-collapse: collapse; width: 100%; font-size: 28px; }
table.data th { text-align: left; font-size: 22px; letter-spacing: 0.06em; text-transform: uppercase; color: var(--muted); border-bottom: 2px solid var(--ink); padding: 12px 16px; }
table.data td { border-bottom: 1px solid var(--line); padding: 16px; }
table.data .num { text-align: right; font-variant-numeric: tabular-nums; }
.footer { position: absolute; left: 128px; right: 128px; bottom: 56px; font-size: 22px; color: var(--muted); display: flex; justify-content: space-between; }
.list-check { list-style: none; padding-left: 0; }
.list-check li { padding-left: 52px; position: relative; }
.list-check li::before { content: "✓"; position: absolute; left: 0; color: var(--good); font-weight: 700; }
.tag { display: inline-block; font-size: 22px; font-weight: 600; padding: 4px 14px; border-radius: 999px; background: var(--paper-2); color: var(--ink-soft); }
.tag.good { background: #e2efe6; color: var(--good); }
.tag.warn { background: #f6ead8; color: var(--warn); }
.tag.bad { background: #f4e0e0; color: var(--bad); }
CSS;
    }
}
