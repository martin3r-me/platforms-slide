{{--
    Eine Folie skaliert anzeigen: iframe ohne Skripte (sandbox), Inhalt per srcdoc, Skalierung per transform.
    @include('slides::livewire.partials.slide-frame', ['doc' => $srcdoc, 'size' => $deck->size, 'label' => 'Folie 3'])
--}}
@php $w = $size->width(); $h = $size->height(); @endphp
<div class="relative w-full overflow-hidden" style="aspect-ratio: {{ $w }} / {{ $h }};"
     x-data="{ s: 1 }" x-init="const r = () => s = $el.clientWidth / {{ $w }}; r(); new ResizeObserver(r).observe($el)">
    <iframe sandbox="allow-same-origin" srcdoc="{{ $doc }}" title="{{ $label ?? 'Folie' }}" loading="lazy"
            style="position:absolute; top:0; left:0; width: {{ $w }}px; height: {{ $h }}px; border:0; transform-origin: 0 0;"
            :style="{ transform: `scale(${s})` }"></iframe>
</div>
