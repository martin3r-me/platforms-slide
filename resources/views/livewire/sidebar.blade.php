<div
    x-data="{
        init() {
            const expandedState = localStorage.getItem('slides.expandedFolders');
            if (expandedState) {
                try {
                    const expanded = JSON.parse(expandedState);
                    if (Array.isArray(expanded) && expanded.length > 0) {
                        @this.set('expandedFolders', expanded);
                    }
                } catch (e) {}
            }
        }
    }"
>
    {{-- Modul Header --}}
    <div x-show="!collapsed" class="p-2 text-xs italic text-[var(--ui-secondary)] uppercase border-b border-[var(--ui-border)] mb-1">
        Präsentationen
    </div>

    {{-- Allgemein --}}
    <x-ui-sidebar-list label="Allgemein">
        <x-ui-sidebar-item :href="route('slides.dashboard')">
            @svg('heroicon-o-home', 'w-3.5 h-3.5 text-[var(--ui-secondary)]')
            <span class="ml-1.5 text-xs">Dashboard</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Neuer Ordner / Neue Präsentation --}}
    <x-ui-sidebar-list>
        <x-ui-sidebar-item wire:click="createFolder">
            @svg('heroicon-o-plus-circle', 'w-3.5 h-3.5 text-[var(--ui-secondary)]')
            <span class="ml-1.5 text-xs">Neuer Ordner</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Collapsed: Icons-only --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--ui-border)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('slides.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                @svg('heroicon-o-home', 'w-5 h-5')
            </a>
        </div>
    </div>
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--ui-border)]">
        <button type="button" wire:click="createFolder" class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
            @svg('heroicon-o-plus-circle', 'w-5 h-5')
        </button>
    </div>

    {{-- Ordner-Baum --}}
    <div>
        <div class="mt-1" x-show="!collapsed">
            @if($rootFolders->isNotEmpty())
                <div x-show="!collapsed" class="px-1 py-1 border-b border-[var(--ui-border)]">
                    <div class="px-1 pb-1 text-[10px] uppercase tracking-wide text-[var(--ui-muted)]">Ordner</div>
                    <div class="flex flex-col gap-0.5">
                        @foreach($rootFolders as $folder)
                            @include('slides::livewire.partials.folder-tree-item', [
                                'folder' => $folder,
                                'level' => 0
                            ])
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Letzte Präsentationen ohne Ordner --}}
            @if($recentPresentations->isNotEmpty())
                <div class="px-1 py-1 border-b border-[var(--ui-border)]">
                    <div class="px-1 pb-1 text-[10px] uppercase tracking-wide text-[var(--ui-muted)]">Zuletzt bearbeitet</div>
                    <div class="flex flex-col gap-0.5">
                        @foreach($recentPresentations as $presentation)
                            <a
                                href="{{ route('slides.presentations.show', $presentation) }}"
                                wire:navigate
                                class="flex items-center flex-1 min-w-0 py-0.5 px-1 rounded-md hover:bg-[var(--ui-muted-5)] transition-colors"
                            >
                                @svg('heroicon-o-presentation-chart-bar', 'w-3.5 h-3.5 flex-shrink-0 text-[var(--ui-secondary)]')
                                <div class="flex-1 min-w-0 ml-1">
                                    <div class="truncate text-xs font-medium leading-tight">{{ $presentation->name }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($rootFolders->isEmpty() && $recentPresentations->isEmpty())
                <div class="px-3 py-1 text-xs text-[var(--ui-muted)]">
                    Keine Ordner oder Präsentationen vorhanden
                </div>
            @endif
        </div>
    </div>
</div>
