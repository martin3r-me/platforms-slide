<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Präsentationen" icon="heroicon-o-presentation-chart-bar" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Stats Grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
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
                <x-ui-panel title="Ordner" subtitle="Alle Ordner">
                    <div class="grid grid-cols-1 gap-3">
                        @foreach($folders as $folder)
                            <a href="{{ route('slides.folders.show', $folder) }}" wire:navigate class="flex items-center gap-3 p-3 rounded-md border border-[var(--ui-border)] bg-white hover:bg-[var(--ui-muted-5)] transition">
                                <div class="w-8 h-8 bg-[var(--ui-primary)] text-[var(--ui-on-primary)] rounded flex items-center justify-center">
                                    @svg('heroicon-o-folder', 'w-5 h-5')
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium truncate">{{ $folder->name }}</div>
                                    @if($folder->description)
                                        <div class="text-xs text-[var(--ui-muted)] truncate">{{ $folder->description }}</div>
                                    @endif
                                </div>
                                <div class="flex-shrink-0 text-sm text-[var(--ui-muted)]">
                                    {{ $folder->presentations()->count() }} Präsentationen
                                </div>
                            </a>
                        @endforeach
                    </div>
                </x-ui-panel>
            @endif

            {{-- Präsentationen ohne Ordner --}}
            <x-ui-panel title="Präsentationen" subtitle="Ohne Ordner">
                <div class="grid grid-cols-1 gap-3">
                    @forelse($presentations as $presentation)
                        <a href="{{ route('slides.presentations.show', $presentation) }}" wire:navigate class="flex items-center gap-3 p-3 rounded-md border border-[var(--ui-border)] bg-white hover:bg-[var(--ui-muted-5)] transition">
                            <div class="w-8 h-8 bg-[var(--ui-primary)] text-[var(--ui-on-primary)] rounded flex items-center justify-center">
                                @svg('heroicon-o-presentation-chart-bar', 'w-5 h-5')
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-medium truncate">{{ $presentation->name }}</div>
                                <div class="text-xs text-[var(--ui-muted)]">
                                    {{ $presentation->slides()->count() }} Slides &middot; {{ $presentation->updated_at->format('d.m.Y') }}
                                </div>
                            </div>
                            @if($presentation->is_published)
                                <span class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-[var(--ui-success-5)] text-[var(--ui-success)]">Veröffentlicht</span>
                            @endif
                        </a>
                    @empty
                        <div class="p-3 text-sm text-[var(--ui-muted)] bg-white rounded-md border border-[var(--ui-border)]">Keine Präsentationen vorhanden.</div>
                    @endforelse
                </div>
            </x-ui-panel>

            {{-- Letzte Präsentationen --}}
            @if($recentPresentations->isNotEmpty())
                <x-ui-panel title="Zuletzt bearbeitet" subtitle="Top 5">
                    <div class="grid grid-cols-1 gap-3">
                        @foreach($recentPresentations as $presentation)
                            <a href="{{ route('slides.presentations.show', $presentation) }}" wire:navigate class="flex items-center gap-3 p-3 rounded-md border border-[var(--ui-border)] bg-white hover:bg-[var(--ui-muted-5)] transition">
                                <div class="w-8 h-8 bg-[var(--ui-primary)] text-[var(--ui-on-primary)] rounded flex items-center justify-center">
                                    @svg('heroicon-o-presentation-chart-bar', 'w-5 h-5')
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium truncate">{{ $presentation->name }}</div>
                                    <div class="text-xs text-[var(--ui-muted)]">{{ $presentation->updated_at->diffForHumans() }}</div>
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
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Aktionen</h3>
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
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Statistiken</h3>
                    <div class="space-y-3">
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)]">Ordner</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $totalFolders }}</div>
                        </div>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)]">Präsentationen</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $totalPresentations }}</div>
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
