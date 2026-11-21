<!doctype html>
<html lang="de"><head><meta charset="utf-8"><title>{{ $title }}</title>
<style>
    @page { size: {{ $w }}px {{ $h }}px; margin: 0; }
    html, body { margin: 0; padding: 0; background: #fff; }
    .p { width: {{ $w }}px; height: {{ $h }}px; overflow: hidden; break-after: page; page-break-after: always; }
    .p:last-child { break-after: auto; page-break-after: auto; }
    .p iframe { width: {{ $w }}px; height: {{ $h }}px; border: 0; display: block; }
</style></head>
<body>
@foreach($docs as $doc)
    <div class="p"><iframe sandbox="allow-same-origin" srcdoc="{{ $doc }}"></iframe></div>
@endforeach
</body></html>
