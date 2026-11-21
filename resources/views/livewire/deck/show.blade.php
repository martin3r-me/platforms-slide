<x-ui-page>
    <x-slot name="navbar"><x-ui-page-navbar :title="$deck->title" /></x-slot>
    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[['label' => 'Präsentationen', 'href' => route('slides.dashboard'), 'icon' => 'presentation-chart-bar'], ['label' => $deck->title]]">
            <x-nx-dropdown label="Aktionen">
                <x-nx-dropdown-item :href="route('slides.decks.present', $deck)">@svg('heroicon-o-play', 'w-4 h-4') Präsentieren</x-nx-dropdown-item>
                <x-nx-dropdown-item :href="route('slides.decks.pdf', $deck)">@svg('heroicon-o-printer', 'w-4 h-4') Druckansicht</x-nx-dropdown-item>
            </x-nx-dropdown>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">
        <div wire:poll.{{ $poll }}s="refreshIfChanged" class="space-y-6">
            <div class="flex items-center gap-3 text-sm text-[color:var(--nx-muted)]">
                <x-nx-badge :variant="$deck->status->badgeVariant()" dot>{{ $deck->status->label() }}</x-nx-badge>
                <span>{{ $slides->count() }} Folien · {{ $size->value }} · Revision {{ $deck->revision }}</span>
            </div>
            @if($deck->description)
                <p class="m-0 max-w-3xl text-sm leading-relaxed text-[color:var(--nx-muted)]">{{ $deck->description }}</p>
            @endif

            @forelse($slides as $i => $slide)
                <div wire:key="slide-{{ $slide->id }}-{{ $deck->revision }}" class="space-y-2">
                    <div class="flex items-center gap-2 text-xs text-[color:var(--nx-faint)]">
                        <span class="font-medium text-[color:var(--nx-muted)]">{{ $i + 1 }}</span>
                        <span>{{ $slide->slide_key }}</span>
                        @if($slide->is_hidden)<x-nx-badge>ausgeblendet</x-nx-badge>@endif
                    </div>
                    <div class="overflow-hidden rounded-[8px] border border-[color:var(--nx-line)]">
                        @include('slides::livewire.partials.slide-frame', ['doc' => $docs[$slide->id], 'size' => $size, 'label' => 'Folie ' . ($i + 1)])
                    </div>
                    @if($slide->notes)
                        <p class="m-0 whitespace-pre-line text-xs leading-relaxed text-[color:var(--nx-muted)]">{{ $slide->notes }}</p>
                    @endif
                </div>
            @empty
                <x-nx-card><x-nx-empty icon="heroicon-o-presentation-chart-bar">Dieses Deck hat noch keine Folien. Agenten schreiben Folien über slides.slides.PUT.</x-nx-empty></x-nx-card>
            @endforelse
        </div>
    </x-ui-page-container>
</x-ui-page>
