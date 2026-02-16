<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$presentation->name" icon="heroicon-o-presentation-chart-bar" />
    </x-slot>

    <x-ui-page-container class="max-w-6xl mx-auto">
        {{-- Header --}}
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <input
                        type="text"
                        value="{{ $presentation->name }}"
                        wire:change="updateName($event.target.value)"
                        class="text-3xl font-bold text-[var(--ui-secondary)] bg-transparent border-none outline-none focus:ring-0 w-full"
                        style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;"
                    />
                </div>
                <div class="flex items-center gap-2">
                    @can('update', $presentation)
                        <a
                            href="{{ route('slides.presentations.settings', $presentation) }}"
                            wire:navigate
                            class="px-3 py-2 text-sm rounded-lg border border-[var(--ui-border)] hover:bg-[var(--ui-muted-5)] transition-colors flex items-center gap-2"
                        >
                            @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                            <span>Einstellungen</span>
                        </a>
                    @endcan
                    <a
                        href="{{ route('slides.presentations.present', $presentation) }}"
                        target="_blank"
                        class="px-3 py-2 text-sm rounded-lg bg-[var(--ui-primary)] text-[var(--ui-on-primary)] hover:opacity-90 transition-opacity flex items-center gap-2"
                    >
                        @svg('heroicon-o-play', 'w-4 h-4')
                        <span>Präsentieren</span>
                    </a>
                </div>
            </div>
            <div class="mt-2 text-sm text-[var(--ui-muted)]">
                {{ $slides->count() }} Slides &middot; {{ $presentation->slide_width }}x{{ $presentation->slide_height }}
                @if($presentation->folder)
                    &middot; <a href="{{ route('slides.folders.show', $presentation->folder) }}" wire:navigate class="text-[var(--ui-primary)] hover:underline">{{ $presentation->folder->name }}</a>
                @endif
            </div>
        </div>

        {{-- Slide Grid / Sorter --}}
        <div
            x-data="{
                dragging: null,
                dragOver: null,
                reorder(fromId, toIndex) {
                    // Collect current order
                    const items = [...document.querySelectorAll('[data-slide-id]')];
                    const ids = items.map(el => parseInt(el.dataset.slideId));

                    // Move the dragged item
                    const fromIndex = ids.indexOf(parseInt(fromId));
                    if (fromIndex === -1) return;
                    const [moved] = ids.splice(fromIndex, 1);
                    ids.splice(toIndex, 0, moved);

                    $wire.reorderSlides(ids);
                }
            }"
            class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"
        >
            @foreach($slides as $index => $slide)
                <div
                    data-slide-id="{{ $slide->id }}"
                    draggable="true"
                    x-on:dragstart="dragging = {{ $slide->id }}"
                    x-on:dragend="dragging = null; dragOver = null"
                    x-on:dragover.prevent="dragOver = {{ $index }}"
                    x-on:drop.prevent="reorder(dragging, {{ $index }})"
                    class="group relative rounded-lg border-2 transition-all cursor-grab active:cursor-grabbing"
                    :class="dragOver === {{ $index }} ? 'border-[var(--ui-primary)] bg-[var(--ui-primary)]/5' : 'border-[var(--ui-border)]/40 hover:border-[var(--ui-primary)]/40'"
                >
                    {{-- Slide Number Badge --}}
                    <div class="absolute top-2 left-2 z-10 px-1.5 py-0.5 text-[10px] font-bold rounded bg-black/60 text-white">
                        {{ $index + 1 }}
                    </div>

                    {{-- Hidden Badge --}}
                    @if($slide->is_hidden)
                        <div class="absolute top-2 right-2 z-10 px-1.5 py-0.5 text-[10px] font-medium rounded bg-yellow-500/80 text-white">
                            Versteckt
                        </div>
                    @endif

                    {{-- Slide Preview (16:9 aspect ratio) --}}
                    <a href="{{ route('slides.presentations.slides.edit', [$presentation, $slide]) }}" wire:navigate>
                        <div class="aspect-video rounded-t-lg overflow-hidden bg-white relative"
                            @php
                                $bg = $slide->background ?? ['type' => 'color', 'value' => '#ffffff'];
                            @endphp
                            @if($bg['type'] === 'color')
                                style="background-color: {{ $bg['value'] }};"
                            @elseif($bg['type'] === 'gradient')
                                @php
                                    $dir = str_replace(['to-', 'br', 'bl', 'tr', 'tl', 'r', 'l', 'b', 't'], ['to ', 'bottom right', 'bottom left', 'top right', 'top left', 'right', 'left', 'bottom', 'top'], $bg['value']['direction'] ?? 'to-br');
                                    $stops = implode(', ', $bg['value']['stops'] ?? ['#667eea', '#764ba2']);
                                @endphp
                                style="background: linear-gradient({{ $dir }}, {{ $stops }});"
                            @endif
                        >
                            {{-- Mini slide content preview --}}
                            <div class="absolute inset-0 p-3 overflow-hidden" style="transform: scale(0.15); transform-origin: top left; width: 1920px; height: 1080px;">
                                @if($slide->content && isset($slide->content['elements']))
                                    @foreach($slide->content['elements'] as $element)
                                        @if($element['type'] === 'text')
                                            <div style="
                                                position: absolute;
                                                left: {{ $element['x'] }}px;
                                                top: {{ $element['y'] }}px;
                                                width: {{ $element['width'] }}px;
                                                height: {{ $element['height'] }}px;
                                                font-size: {{ $element['style']['fontSize'] ?? 24 }}px;
                                                font-weight: {{ $element['style']['fontWeight'] ?? '400' }};
                                                color: {{ $element['style']['color'] ?? '#333' }};
                                                text-align: {{ $element['style']['textAlign'] ?? 'left' }};
                                                line-height: {{ $element['style']['lineHeight'] ?? 1.4 }};
                                                overflow: hidden;
                                            ">
                                                {!! $element['content']['html'] ?? '' !!}
                                            </div>
                                        @elseif($element['type'] === 'image' && !empty($element['content']['src']))
                                            <div style="
                                                position: absolute;
                                                left: {{ $element['x'] }}px;
                                                top: {{ $element['y'] }}px;
                                                width: {{ $element['width'] }}px;
                                                height: {{ $element['height'] }}px;
                                                background: #e5e7eb;
                                                border-radius: {{ $element['style']['borderRadius'] ?? 0 }}px;
                                                overflow: hidden;
                                            ">
                                                <img src="{{ $element['content']['src'] }}" alt="" style="width: 100%; height: 100%; object-fit: {{ $element['style']['objectFit'] ?? 'cover' }};">
                                            </div>
                                        @endif
                                    @endforeach
                                @endif
                            </div>

                            {{-- Layout key badge --}}
                            @if($slide->layout_key)
                                <div class="absolute bottom-1 right-1 text-[8px] text-[var(--ui-muted)] opacity-0 group-hover:opacity-100 transition-opacity">
                                    {{ $slide->layout_key }}
                                </div>
                            @endif
                        </div>
                    </a>

                    {{-- Slide Actions --}}
                    @can('update', $presentation)
                        <div class="flex items-center justify-between px-2 py-1.5 border-t border-[var(--ui-border)]/40 opacity-0 group-hover:opacity-100 transition-opacity">
                            <a href="{{ route('slides.presentations.slides.edit', [$presentation, $slide]) }}" wire:navigate class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors" title="Bearbeiten">
                                @svg('heroicon-o-pencil', 'w-3.5 h-3.5')
                            </a>
                            <button wire:click="duplicateSlide({{ $slide->id }})" class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors" title="Duplizieren">
                                @svg('heroicon-o-document-duplicate', 'w-3.5 h-3.5')
                            </button>
                            <button wire:click="toggleHideSlide({{ $slide->id }})" class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors" title="{{ $slide->is_hidden ? 'Einblenden' : 'Ausblenden' }}">
                                @if($slide->is_hidden)
                                    @svg('heroicon-o-eye', 'w-3.5 h-3.5')
                                @else
                                    @svg('heroicon-o-eye-slash', 'w-3.5 h-3.5')
                                @endif
                            </button>
                            <button wire:click="deleteSlide({{ $slide->id }})" wire:confirm="Diesen Slide wirklich löschen?" class="p-1 text-[var(--ui-muted)] hover:text-red-500 transition-colors" title="Löschen">
                                @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                            </button>
                        </div>
                    @endcan
                </div>
            @endforeach

            {{-- Add Slide Card --}}
            @can('update', $presentation)
                <div
                    class="rounded-lg border-2 border-dashed border-[var(--ui-border)] hover:border-[var(--ui-primary)]/40 transition-all cursor-pointer flex items-center justify-center aspect-video"
                    x-data="{ open: false }"
                >
                    <button
                        x-on:click="open = !open"
                        class="flex flex-col items-center gap-2 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors"
                    >
                        @svg('heroicon-o-plus-circle', 'w-8 h-8')
                        <span class="text-xs font-medium">Slide hinzufügen</span>
                    </button>

                    {{-- Template Dropdown --}}
                    <div
                        x-show="open"
                        x-on:click.outside="open = false"
                        x-transition
                        class="absolute z-50 mt-2 w-56 bg-white rounded-lg shadow-lg border border-[var(--ui-border)] py-1 max-h-64 overflow-y-auto"
                    >
                        @php
                            $categories = [
                                'title' => 'Titel',
                                'content' => 'Inhalt',
                                'media' => 'Medien',
                                'closing' => 'Abschluss',
                            ];
                            $layouts = \Platform\Slides\Models\SlidesSlideTemplate::systemLayouts();
                        @endphp
                        @foreach($categories as $catKey => $catLabel)
                            <div class="px-3 py-1 text-[10px] uppercase tracking-wide text-[var(--ui-muted)] font-semibold">{{ $catLabel }}</div>
                            @foreach(collect($layouts)->where('category', $catKey) as $layout)
                                <button
                                    wire:click="addSlide('{{ $layout['layout_key'] }}')"
                                    x-on:click="open = false"
                                    class="w-full text-left px-3 py-1.5 text-xs hover:bg-[var(--ui-muted-5)] transition-colors flex items-center gap-2"
                                >
                                    @svg('heroicon-o-squares-2x2', 'w-3.5 h-3.5 text-[var(--ui-muted)]')
                                    <span>{{ $layout['name'] }}</span>
                                </button>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            @endcan
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Präsentation" width="w-80" :defaultOpen="true">
            <div class="p-4 space-y-4">
                {{-- Aktionen --}}
                @can('update', $presentation)
                    <div>
                        <h3 class="text-[10px] font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2">Aktionen</h3>
                        <div class="flex flex-col gap-1.5">
                            <x-ui-button variant="secondary-outline" size="sm" wire:click="addSlide('content-text')" class="w-full text-xs py-1.5">
                                <span class="inline-flex items-center gap-1.5">
                                    @svg('heroicon-o-plus','w-3.5 h-3.5')
                                    <span>Neuer Slide</span>
                                </span>
                            </x-ui-button>
                            <x-ui-button variant="secondary-outline" size="sm" :href="route('slides.presentations.settings', $presentation)" wire:navigate class="w-full text-xs py-1.5">
                                <span class="inline-flex items-center gap-1.5">
                                    @svg('heroicon-o-cog-6-tooth','w-3.5 h-3.5')
                                    <span>Einstellungen</span>
                                </span>
                            </x-ui-button>
                        </div>
                    </div>
                @endcan

                {{-- Präsentieren --}}
                <div>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2">Präsentieren</h3>
                    <div class="flex flex-col gap-1.5">
                        <a href="{{ route('slides.presentations.present', $presentation) }}" target="_blank" class="w-full px-3 py-1.5 text-xs rounded-md bg-[var(--ui-primary)] text-[var(--ui-on-primary)] hover:opacity-90 transition-opacity flex items-center justify-center gap-1.5">
                            @svg('heroicon-o-play','w-3.5 h-3.5')
                            <span>Fullscreen</span>
                        </a>
                        <a href="{{ route('slides.presentations.presenter', $presentation) }}" target="_blank" class="w-full px-3 py-1.5 text-xs rounded-md border border-[var(--ui-border)] hover:bg-[var(--ui-muted-5)] transition-colors flex items-center justify-center gap-1.5">
                            @svg('heroicon-o-computer-desktop','w-3.5 h-3.5')
                            <span>Referentenansicht</span>
                        </a>
                    </div>
                </div>

                {{-- Details --}}
                <div>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2">Details</h3>
                    <div class="space-y-1.5">
                        <div class="flex justify-between items-center py-1.5 px-2.5 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded-md">
                            <span class="text-xs text-[var(--ui-muted)]">Slides</span>
                            <span class="text-xs text-[var(--ui-secondary)] font-medium">{{ $slides->count() }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1.5 px-2.5 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded-md">
                            <span class="text-xs text-[var(--ui-muted)]">Auflösung</span>
                            <span class="text-xs text-[var(--ui-secondary)] font-medium">{{ $presentation->slide_width }}x{{ $presentation->slide_height }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1.5 px-2.5 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded-md">
                            <span class="text-xs text-[var(--ui-muted)]">Erstellt</span>
                            <span class="text-xs text-[var(--ui-secondary)] font-medium">{{ $presentation->created_at->format('d.m.Y') }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1.5 px-2.5 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded-md">
                            <span class="text-xs text-[var(--ui-muted)]">Status</span>
                            <span class="text-[10px] font-medium px-1.5 py-0.5 rounded {{ $presentation->is_published ? 'bg-[var(--ui-success-5)] text-[var(--ui-success)]' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)]' }}">
                                {{ $presentation->is_published ? 'Veröffentlicht' : 'Entwurf' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Löschen --}}
                @can('delete', $presentation)
                    <div>
                        <h3 class="text-[10px] font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2">Gefährlich</h3>
                        <button
                            wire:click="deletePresentation"
                            wire:confirm="Möchten Sie diese Präsentation wirklich löschen?"
                            class="w-full px-3 py-1.5 text-xs rounded-md border border-red-500/30 bg-red-500/10 text-red-600 hover:bg-red-500/20 transition-colors flex items-center justify-center gap-1.5"
                        >
                            @svg('heroicon-o-trash','w-3.5 h-3.5')
                            <span>Präsentation löschen</span>
                        </button>
                    </div>
                @endcan
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
