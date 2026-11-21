<div>
    @php $w = $size->width(); $h = $size->height(); @endphp
    <style>
        @page { size: {{ $w }}px {{ $h }}px; margin: 0; }
        .slides-print-page { width: {{ $w }}px; height: {{ $h }}px; page-break-after: always; break-after: page; }
        .slides-print-page iframe { width: {{ $w }}px; height: {{ $h }}px; border: 0; display: block; }
        @media screen { body { background: #2a2f36 !important; } .slides-print-page { margin: 24px auto; box-shadow: 0 2px 12px rgba(0,0,0,.4); } }
    </style>
    @foreach($docs as $i => $doc)
        <div class="slides-print-page"><iframe sandbox="allow-same-origin" srcdoc="{{ $doc }}" title="Folie {{ $i + 1 }}"></iframe></div>
    @endforeach
</div>
