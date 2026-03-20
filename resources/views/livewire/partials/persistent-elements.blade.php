{{-- Persistent Elements: Logo, Slide Numbers, Footer --}}
{{-- Expects: $presentationSettings (array), uses Alpine.js context for currentIndex/slides.length --}}
@php
    $settings = $presentationSettings ?? [];
    $logo = $settings['logo'] ?? [];
    $slideNumber = $settings['slideNumber'] ?? [];
    $footer = $settings['footer'] ?? [];

    $positionStyles = [
        'top-left' => 'top: 30px; left: 40px;',
        'top-right' => 'top: 30px; right: 40px;',
        'bottom-left' => 'bottom: 30px; left: 40px;',
        'bottom-right' => 'bottom: 30px; right: 40px;',
        'bottom-center' => 'bottom: 30px; left: 50%; transform: translateX(-50%);',
    ];
@endphp

{{-- Logo --}}
@if(!empty($logo['src']))
    <div class="absolute z-50 pointer-events-none" style="{{ $positionStyles[$logo['position'] ?? 'top-right'] ?? $positionStyles['top-right'] }} opacity: {{ $logo['opacity'] ?? 1 }};">
        <img src="{{ $logo['src'] }}" alt="Logo" style="width: {{ $logo['width'] ?? 120 }}px; height: auto;" />
    </div>
@endif

{{-- Slide Number --}}
@if(!empty($slideNumber['enabled']))
    <div class="absolute z-50 pointer-events-none" style="{{ $positionStyles[$slideNumber['position'] ?? 'bottom-right'] ?? $positionStyles['bottom-right'] }}">
        <span class="text-sm font-medium" style="color: rgba(0,0,0,0.4); font-family: sans-serif; font-size: 18px;" x-text="(currentIndex + 1) + ' / ' + slides.length"></span>
    </div>
@endif

{{-- Footer Text --}}
@if(!empty($footer['enabled']) && !empty($footer['text']))
    <div class="absolute z-50 pointer-events-none" style="{{ $positionStyles[$footer['position'] ?? 'bottom-center'] ?? $positionStyles['bottom-center'] }}">
        <span style="color: rgba(0,0,0,0.4); font-family: sans-serif; font-size: 16px;">{{ $footer['text'] }}</span>
    </div>
@endif
