<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Präsentationen" icon="heroicon-o-presentation-chart-bar" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-8">
            {{-- Hero Stats --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <x-ui-dashboard-tile
                    title="Ordner"
                    :count="$totalFolders"
                    subtitle="Gesamt"
                    icon="folder"
                    variant="secondary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Präsentationen"
                    :count="$totalPresentations"
                    subtitle="Gesamt"
                    icon="presentation-chart-bar"
                    variant="secondary"
                    size="lg"
                />
            </div>

            {{-- Ordner --}}
            @if($folders->isNotEmpty())
                <x-ui-panel title="Ordner" subtitle="Deine Ordner">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($folders as $folder)
                            <a href="{{ route('slides.folders.show', $folder) }}" wire:navigate
                               class="group flex items-start gap-4 p-4 rounded-xl border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] hover:border-[var(--ui-primary)]/40 hover:shadow-md transition-all duration-200">
                                <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-[var(--ui-primary)] to-[var(--ui-primary)]/70 text-[var(--ui-on-primary)] rounded-lg flex items-center justify-center shadow-sm">
                                    @svg('heroicon-o-folder', 'w-5 h-5')
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold text-[var(--ui-secondary)] group-hover:text-[var(--ui-primary)] transition-colors truncate">{{ $folder->name }}</div>
                                    @if($folder->description)
                                        <div class="text-xs text-[var(--ui-muted)] mt-0.5 truncate">{{ $folder->description }}</div>
                                    @endif
                                    <div class="text-xs text-[var(--ui-muted)] mt-1.5 flex items-center gap-1">
                                        @svg('heroicon-o-rectangle-stack', 'w-3 h-3')
                                        <span>{{ $folder->presentations()->count() }} Präsentationen</span>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </x-ui-panel>
            @endif

            {{-- Präsentationen ohne Ordner --}}
            <x-ui-panel title="Präsentationen" subtitle="Ohne Ordner">
                @if($presentations->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($presentations as $presentation)
                            <a href="{{ route('slides.presentations.show', $presentation) }}" wire:navigate
                               class="group rounded-xl border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] hover:border-[var(--ui-primary)]/40 hover:shadow-md transition-all duration-200 overflow-hidden">
                                {{-- Slide preview thumbnail --}}
                                @php
                                    $firstSlide = $presentation->slides()->orderBy('sort_order')->first();
                                    $bgPreview = $firstSlide?->background ?? ['type' => 'color', 'value' => '#f8fafc'];
                                @endphp
                                <div class="aspect-video relative overflow-hidden"
                                    @if(($bgPreview['type'] ?? 'color') === 'color')
                                        style="background-color: {{ $bgPreview['value'] ?? '#f8fafc' }};"
                                    @elseif(($bgPreview['type'] ?? '') === 'gradient')
                                        @php
                                            $dir = str_replace(['to-', 'br', 'bl', 'tr', 'tl', 'r', 'l', 'b', 't'], ['to ', 'bottom right', 'bottom left', 'top right', 'top left', 'right', 'left', 'bottom', 'top'], $bgPreview['value']['direction'] ?? 'to-br');
                                            $stops = implode(', ', $bgPreview['value']['stops'] ?? ['#667eea', '#764ba2']);
                                        @endphp
                                        style="background: linear-gradient({{ $dir }}, {{ $stops }});"
                                    @endif
                                >
                                    @if($firstSlide && isset($firstSlide->content['elements']))
                                        <div class="absolute inset-0 overflow-hidden" style="transform: scale(0.13); transform-origin: top left; width: 1920px; height: 1080px;">
                                            @foreach($firstSlide->content['elements'] as $element)
                                                @if($element['type'] === 'text')
                                                    <div style="position: absolute; left: {{ $element['x'] }}px; top: {{ $element['y'] }}px; width: {{ $element['width'] }}px; height: {{ $element['height'] }}px; font-size: {{ $element['style']['fontSize'] ?? 24 }}px; font-weight: {{ $element['style']['fontWeight'] ?? '400' }}; color: {{ $element['style']['color'] ?? '#333' }}; text-align: {{ $element['style']['textAlign'] ?? 'left' }}; line-height: {{ $element['style']['lineHeight'] ?? 1.4 }}; overflow: hidden;">
                                                        {!! $element['content']['html'] ?? '' !!}
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            @svg('heroicon-o-presentation-chart-bar', 'w-10 h-10 text-[var(--ui-muted)]/30')
                                        </div>
                                    @endif

                                    @if($presentation->is_published)
                                        <div class="absolute top-2 right-2">
                                            <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full bg-[var(--ui-success)]/90 text-white shadow-sm">
                                                Veröffentlicht
                                            </span>
                                        </div>
                                    @endif
                                </div>

                                <div class="p-3">
                                    <div class="font-semibold text-sm text-[var(--ui-secondary)] group-hover:text-[var(--ui-primary)] transition-colors truncate">{{ $presentation->name }}</div>
                                    <div class="text-xs text-[var(--ui-muted)] mt-1 flex items-center gap-2">
                                        <span class="flex items-center gap-0.5">
                                            @svg('heroicon-o-rectangle-stack', 'w-3 h-3')
                                            {{ $presentation->slides()->count() }}
                                        </span>
                                        <span>&middot;</span>
                                        <span>{{ $presentation->updated_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="py-12 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-[var(--ui-muted-5)] mb-4">
                            @svg('heroicon-o-presentation-chart-bar', 'w-8 h-8 text-[var(--ui-muted)]')
                        </div>
                        <p class="text-sm text-[var(--ui-muted)] mb-4">Noch keine Präsentationen vorhanden.</p>
                        <button wire:click="createPresentation" class="inline-flex items-center gap-2 px-4 py-2 text-sm rounded-lg bg-[var(--ui-primary)] text-[var(--ui-on-primary)] hover:opacity-90 transition-opacity">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            <span>Erste Präsentation erstellen</span>
                        </button>
                    </div>
                @endif
            </x-ui-panel>

            {{-- Zuletzt bearbeitet --}}
            @if($recentPresentations->isNotEmpty())
                <x-ui-panel title="Zuletzt bearbeitet" subtitle="Deine neuesten Änderungen">
                    <div class="divide-y divide-[var(--ui-border)]/40">
                        @foreach($recentPresentations as $presentation)
                            <a href="{{ route('slides.presentations.show', $presentation) }}" wire:navigate
                               class="group flex items-center gap-4 py-3 px-1 hover:bg-[var(--ui-muted-5)]/50 -mx-1 rounded-lg transition-colors">
                                @php
                                    $rFirstSlide = $presentation->slides()->orderBy('sort_order')->first();
                                    $rBg = $rFirstSlide?->background ?? ['type' => 'color', 'value' => '#f8fafc'];
                                @endphp
                                <div class="flex-shrink-0 w-16 aspect-video rounded-md border border-[var(--ui-border)]/40 overflow-hidden shadow-sm"
                                    @if(($rBg['type'] ?? 'color') === 'color')
                                        style="background-color: {{ $rBg['value'] ?? '#f8fafc' }};"
                                    @endif
                                ></div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-sm text-[var(--ui-secondary)] group-hover:text-[var(--ui-primary)] transition-colors truncate">{{ $presentation->name }}</div>
                                    <div class="text-xs text-[var(--ui-muted)] mt-0.5">{{ $presentation->updated_at->diffForHumans() }}</div>
                                </div>
                                <div class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                    @svg('heroicon-o-arrow-right', 'w-4 h-4 text-[var(--ui-muted)]')
                                </div>
                            </a>
                        @endforeach
                    </div>
                </x-ui-panel>
            @endif
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true">
            <div class="p-5 space-y-6">
                <div>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-3">Erstellen</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="secondary-outline" size="sm" wire:click="createPresentation" class="w-full">
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                <span>Neue Präsentation</span>
                            </span>
                        </x-ui-button>
                        <x-ui-button variant="secondary-outline" size="sm" wire:click="createFolder" class="w-full">
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-folder-plus', 'w-4 h-4')
                                <span>Neuer Ordner</span>
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                <div>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-3">Statistiken</h3>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="flex items-center gap-2">
                                @svg('heroicon-o-folder', 'w-4 h-4 text-[var(--ui-muted)]')
                                <span class="text-xs text-[var(--ui-muted)]">Ordner</span>
                            </div>
                            <span class="text-sm font-bold text-[var(--ui-secondary)]">{{ $totalFolders }}</span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="flex items-center gap-2">
                                @svg('heroicon-o-presentation-chart-bar', 'w-4 h-4 text-[var(--ui-muted)]')
                                <span class="text-xs text-[var(--ui-muted)]">Präsentationen</span>
                            </div>
                            <span class="text-sm font-bold text-[var(--ui-secondary)]">{{ $totalPresentations }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                <div class="py-8 text-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-[var(--ui-muted-5)] mb-3">
                        @svg('heroicon-o-clock', 'w-6 h-6 text-[var(--ui-muted)]')
                    </div>
                    <p class="text-sm text-[var(--ui-muted)]">Noch keine Aktivitäten</p>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
