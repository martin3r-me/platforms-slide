<div>
    <a x-show="!collapsed" href="{{ route('slides.dashboard') }}" wire:navigate
       class="block p-3 text-sm italic text-[var(--nx-text)] uppercase border-b border-[var(--nx-line)] mb-2 hover:text-[var(--nx-accent)]">
        Präsentationen
    </a>
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--nx-line)]">
        <a href="{{ route('slides.dashboard') }}" wire:navigate title="Präsentationen"
           class="flex items-center justify-center p-2 rounded-md text-[var(--nx-text)] hover:bg-[var(--nx-bg)]">
            @svg('heroicon-o-presentation-chart-bar', 'w-5 h-5')
        </a>
    </div>
    <div class="mt-2" x-show="!collapsed">
        <x-ui-sidebar-list label="Zuletzt bearbeitet">
            @forelse($decks as $deck)
                <x-ui-sidebar-item :href="route('slides.decks.show', $deck)" wire:key="sb-deck-{{ $deck->id }}">
                    @svg('heroicon-o-presentation-chart-bar', 'w-4 h-4 text-[var(--nx-muted)]')
                    <span class="ml-2 truncate text-sm">{{ $deck->title }}</span>
                </x-ui-sidebar-item>
            @empty
                <div class="px-3 py-1 text-xs text-[var(--nx-muted)]">Noch keine Decks</div>
            @endforelse
        </x-ui-sidebar-list>
    </div>
</div>
