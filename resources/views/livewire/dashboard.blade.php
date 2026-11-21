<x-ui-page>
    <x-slot name="navbar"><x-ui-page-navbar title="Präsentationen" /></x-slot>
    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[['label' => 'Präsentationen', 'icon' => 'presentation-chart-bar']]">
            <x-nx-button variant="primary" size="sm" wire:click="$set('showCreate', true)">@svg('heroicon-o-plus', 'w-4 h-4') Neues Deck</x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">
        <x-nx-section icon="heroicon-o-presentation-chart-bar" title="Decks" :hint="$decks->count()"
                      description="Präsentationen im HTML-Folienformat – geschrieben von Menschen und Agenten, live aktualisiert.">
            <x-slot name="action">
                <x-nx-input-select name="statusFilter" size="sm" :options="$statusOptions" nullable="true" nullLabel="Alle Status" wire:model.live="statusFilter" />
            </x-slot>
            @if($decks->isEmpty())
                <x-nx-card><x-nx-empty icon="heroicon-o-presentation-chart-bar">Noch keine Präsentationen. Agenten legen Decks über slides.decks.POST an.</x-nx-empty></x-nx-card>
            @else
                <x-nx-card flush>
                    <ul class="m-0 list-none divide-y divide-[color:var(--nx-line)] p-0">
                        @foreach($decks as $deck)
                            <li wire:key="deck-{{ $deck->id }}">
                                <x-nx-list-item icon="heroicon-o-presentation-chart-bar" :title="$deck->title"
                                                :subtitle="$deck->slides_count . ' Folien · ' . $deck->updated_at?->diffForHumans()"
                                                :href="route('slides.decks.show', $deck)">
                                    <x-slot name="trailing"><x-nx-badge :variant="$deck->status->badgeVariant()" dot>{{ $deck->status->label() }}</x-nx-badge></x-slot>
                                </x-nx-list-item>
                            </li>
                        @endforeach
                    </ul>
                </x-nx-card>
            @endif
        </x-nx-section>
    </x-ui-page-container>

    <x-nx-modal wire:model="showCreate" size="sm">
        <x-slot name="header">Neues Deck</x-slot>
        <x-nx-input-text name="title" label="Titel" wire:model="title" required />
        <x-slot name="footer">
            <x-nx-button variant="ghost" wire:click="$set('showCreate', false)">Abbrechen</x-nx-button>
            <x-nx-button variant="primary" wire:click="create">Anlegen</x-nx-button>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
