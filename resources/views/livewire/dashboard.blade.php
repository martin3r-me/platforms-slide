{{-- Präsentationen (nx): Karten mit Vorschau der ersten Folie, Suche und Statusfilter. --}}
<x-ui-page>
    <x-slot name="navbar"><x-ui-page-navbar title="Präsentationen" /></x-slot>
    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[['label' => 'Präsentationen', 'icon' => 'presentation-chart-bar']]">
            @if($canCreate)
                <x-nx-button variant="primary" size="sm" wire:click="$set('showCreate', true)">@svg('heroicon-o-plus', 'w-4 h-4') Neues Deck</x-nx-button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="full" spacing="space-y-8">
        <div class="mx-auto w-full max-w-[1440px] space-y-8">
            <div class="max-w-3xl">
                <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Präsentationen</div>
                <h1 class="m-0 mt-1 text-3xl font-semibold tracking-tight text-[color:var(--nx-text)]">Decks, die mitdenken</h1>
                <p class="mb-0 mt-2 text-[15px] leading-relaxed text-[color:var(--nx-muted)]">
                    Jede Folie ist ein gestalteter Abschnitt im festen Format. Agenten schreiben und aktualisieren Folien direkt – auch während du präsentierst.
                    Decks hängen an Gremien, Sitzungen oder Projekten und sind dort mit einem Klick präsentierbar.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="w-full max-w-xs">
                    <x-nx-input-text name="search" size="sm" placeholder="Suchen …" wire:model.live.debounce.300ms="search" />
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <button type="button" wire:click="$set('statusFilter', '')"
                            class="rounded-full border px-3 py-1 text-xs transition-colors {{ $statusFilter === '' ? 'border-[color:var(--nx-accent)] bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]' : 'border-[color:var(--nx-line-strong)] text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' }}">
                        Alle <span class="opacity-70">{{ $counts->sum() }}</span>
                    </button>
                    @foreach($statusOptions as $st)
                        <button type="button" wire:click="$set('statusFilter', '{{ $st->value }}')"
                                class="rounded-full border px-3 py-1 text-xs transition-colors {{ $statusFilter === $st->value ? 'border-[color:var(--nx-accent)] bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]' : 'border-[color:var(--nx-line-strong)] text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' }}">
                            {{ $st->label() }} <span class="opacity-70">{{ $counts[$st->value] ?? 0 }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            @if($decks->isEmpty())
                <div class="rounded-[12px] border border-dashed border-[color:var(--nx-line-strong)] p-10 text-center">
                    <div class="mx-auto max-w-xl space-y-3">
                        @svg('heroicon-o-presentation-chart-bar', 'mx-auto w-8 h-8 text-[color:var(--nx-faint)]')
                        <p class="m-0 text-base font-medium text-[color:var(--nx-text)]">{{ $search !== '' || $statusFilter !== '' ? 'Keine Decks für diese Auswahl.' : 'Noch keine Präsentationen.' }}</p>
                        <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">Lege ein Deck an oder lass einen Agenten eines bauen: <code>slides.decks.POST</code>, dann <code>slides.guide.GET</code> lesen und Folien mit <code>slides.slides.PUT</code> schreiben.</p>
                        @if($canCreate && $search === '' && $statusFilter === '')
                            <div class="pt-1"><x-nx-button variant="primary" size="sm" wire:click="$set('showCreate', true)">Erstes Deck anlegen</x-nx-button></div>
                        @endif
                    </div>
                </div>
            @else
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                    @foreach($decks as $deck)
                        <a href="{{ route('slides.decks.show', $deck) }}" wire:navigate wire:key="deck-{{ $deck->id }}-{{ $deck->revision }}" class="group block">
                            <div class="overflow-hidden rounded-[10px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] transition-colors group-hover:border-[color:var(--nx-line-strong)]">
                                @if($previews[$deck->id])
                                    <div class="pointer-events-none">
                                        @include('slides::livewire.partials.slide-frame', ['doc' => $previews[$deck->id], 'size' => $deck->size, 'label' => $deck->title])
                                    </div>
                                @else
                                    <div class="flex items-center justify-center text-xs text-[color:var(--nx-faint)]" style="aspect-ratio: {{ $deck->size->width() }} / {{ $deck->size->height() }};">Noch keine Folien</div>
                                @endif
                            </div>
                            <div class="mt-2.5 space-y-1">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="truncate text-sm font-medium text-[color:var(--nx-text)] group-hover:underline">{{ $deck->title }}</span>
                                    <x-nx-badge :variant="$deck->status->badgeVariant()" dot>{{ $deck->status->label() }}</x-nx-badge>
                                </div>
                                <div class="flex flex-wrap gap-x-3 text-xs text-[color:var(--nx-faint)]">
                                    <span>{{ $deck->slides_count }} {{ $deck->slides_count === 1 ? 'Folie' : 'Folien' }}</span>
                                    <span>{{ ($deck->last_edited_at ?? $deck->updated_at)?->diffForHumans() }}@if($deck->lastEditor) · {{ $deck->lastEditor->name }}@endif</span>
                                    @if($deck->links->isNotEmpty())<span>verknüpft ({{ $deck->links->count() }})</span>@endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </x-ui-page-container>

    <x-nx-modal wire:model="showCreate" size="sm">
        <x-slot name="header">Neues Deck</x-slot>
        <div class="space-y-3">
            <x-nx-input-text name="title" label="Titel" wire:model="title" required placeholder="z. B. Strategiemeeting 2027" />
            <p class="m-0 text-xs leading-relaxed text-[color:var(--nx-muted)]">Das Deck startet mit dem Standard-Theme „BHG“. Folien schreibt ein Agent über <code>slides.slides.PUT</code>.</p>
        </div>
        <x-slot name="footer">
            <x-nx-button variant="ghost" wire:click="$set('showCreate', false)">Abbrechen</x-nx-button>
            <x-nx-button variant="primary" wire:click="create">Anlegen</x-nx-button>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
