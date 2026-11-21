{{--
    Druckansicht: eine Folie pro Seite im exakten Folienformat (Browser „Als PDF sichern“).
    ?print=1 öffnet den Druckdialog automatisch, sobald alle Folien geladen sind. Klickschritte sind hier alle sichtbar.
--}}
@php $w = $size->width(); $h = $size->height(); @endphp
<div x-data="{ left: {{ count($docs) }}, auto: {{ request()->boolean('print') ? 'true' : 'false' }}, z: 1,
               fit() { this.z = Math.min(1, (window.innerWidth - 48) / {{ $w }}); },
               printed: false,
               go() { if (this.auto && !this.printed) { this.printed = true; setTimeout(() => window.print(), 400); } },
               done() { if (--this.left <= 0) this.go(); } }"
     x-init="fit(); setTimeout(() => go(), 5000)" @resize.window="fit()">
    <style>
        @page { size: {{ $w }}px {{ $h }}px; margin: 0; }
        html, body { background: var(--stage-bg); }
        .print-bar { position: sticky; top: 0; z-index: 2; display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 12px 20px; background: rgba(14,16,19,.94); border-bottom: 1px solid var(--stage-line); }
        .print-pages { padding: 24px 0 48px; }
        .print-page { width: {{ $w }}px; height: {{ $h }}px; margin: 0 auto 24px; overflow: hidden; background: #fff;
            transform-origin: top center; }
        .print-page iframe { width: {{ $w }}px; height: {{ $h }}px; border: 0; display: block; }
        @media print {
            html, body { background: #fff !important; }
            .print-bar { display: none !important; }
            .print-pages { padding: 0; }
            .print-page { margin: 0; zoom: 1 !important; break-after: page; page-break-after: always; }
            .print-page:last-child { break-after: auto; page-break-after: auto; }
        }
    </style>
    <div class="print-bar">
        <a href="{{ route('slides.decks.show', $deck) }}" class="st-btn">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 5l-5 5 5 5"/></svg> {{ $deck->title }}
        </a>
        <span class="st-muted" style="font-size:13px;">{{ count($docs) }} Seiten · {{ $size->value }} · Im Druckdialog „Als PDF sichern“ wählen, Ränder: keine</span>
        <button type="button" class="st-btn" @click="window.print()">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 8V3h8v5M6 14H4V8h12v6h-2M6 12h8v5H6z"/></svg> Drucken / PDF
        </button>
    </div>
    <div class="print-pages">
        @forelse($docs as $i => $doc)
            <div class="print-page" :style="{ zoom: z }"><iframe sandbox="allow-same-origin" srcdoc="{{ $doc }}" title="Folie {{ $i + 1 }}" @load="done()"></iframe></div>
        @empty
            <p class="st-muted" style="text-align:center;">Dieses Deck hat noch keine sichtbaren Folien.</p>
        @endforelse
    </div>
</div>
