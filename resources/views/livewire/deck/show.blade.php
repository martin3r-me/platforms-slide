{{--
    Deck-Ansicht (nx): große ausgewählte Folie, Filmstreifen, Notizen; rechts Eckdaten, Verknüpfungen, Status.
    Folien sind per Inhalts-Hash verschlüsselt (wire:key) – bei Live-Änderungen werden nur geänderte Folien ersetzt.
--}}
<x-ui-page>
    <x-slot name="navbar"><x-ui-page-navbar :title="$deck->title" /></x-slot>
    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[['label' => 'Präsentationen', 'href' => route('slides.dashboard'), 'icon' => 'presentation-chart-bar'], ['label' => $deck->title]]">
            <div class="flex items-center gap-2">
                <x-nx-button variant="primary" size="sm" :href="route('slides.decks.present', $deck) . '?slide=' . (($currentIndex ?? 0) + 1)" target="_blank">
                    @svg('heroicon-o-play', 'w-4 h-4') Präsentieren
                </x-nx-button>
                <x-nx-dropdown label="Mehr">
                    <x-nx-dropdown-item :href="route('slides.decks.presenter', $deck)" target="_blank">@svg('heroicon-o-computer-desktop', 'w-4 h-4') Referentenansicht</x-nx-dropdown-item>
                    <x-nx-dropdown-item :href="route('slides.decks.pdf', $deck) . '?print=1'" target="_blank">@svg('heroicon-o-document-arrow-down', 'w-4 h-4') PDF / Drucken</x-nx-dropdown-item>
                    <x-nx-dropdown-item wire:click="duplicate">@svg('heroicon-o-document-duplicate', 'w-4 h-4') Duplizieren</x-nx-dropdown-item>
                </x-nx-dropdown>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="full" spacing="space-y-6">
        <div wire:poll.{{ $poll }}s="refreshIfChanged" class="mx-auto w-full max-w-[1440px]">
            {{-- Kopf --}}
            <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Präsentation</div>
                    <h1 class="m-0 mt-1 truncate text-3xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $deck->title }}</h1>
                    @if($deck->description)
                        <p class="mb-0 mt-2 max-w-3xl text-[15px] leading-relaxed text-[color:var(--nx-muted)]">{{ $deck->description }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-3 text-sm text-[color:var(--nx-muted)]"
                     x-data="{ at: @js($editedAt), name: @js($deck->lastEditor?->name), now: Date.now() }" x-init="setInterval(() => now = Date.now(), 10000)">
                    <x-nx-badge :variant="$deck->status->badgeVariant()" dot>{{ $deck->status->label() }}</x-nx-badge>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-1.5 w-1.5 rounded-full bg-[color:var(--nx-success)]" title="Live – Änderungen erscheinen automatisch"></span>
                        <span x-text="(() => { if (!at) return ''; const s = Math.max(0, Math.floor((now - new Date(at)) / 1000)); const t = s < 60 ? 'gerade eben' : s < 3600 ? 'vor ' + Math.floor(s / 60) + ' Min.' : s < 86400 ? 'vor ' + Math.floor(s / 3600) + ' Std.' : 'am ' + new Date(at).toLocaleDateString('de-DE'); return 'aktualisiert' + (name ? ' von ' + name : '') + ' ' + t; })()"></span>
                    </span>
                </div>
            </div>

            <div class="grid gap-10 xl:grid-cols-[minmax(0,1fr)_300px]">
                {{-- Hauptspalte --}}
                <div class="min-w-0 space-y-5">
                    @if($slides->isEmpty())
                        <div class="rounded-[12px] border border-dashed border-[color:var(--nx-line-strong)] p-10">
                            <div class="mx-auto max-w-2xl space-y-4">
                                <h2 class="m-0 text-xl font-semibold text-[color:var(--nx-text)]">Dieses Deck ist noch leer</h2>
                                <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-muted)]">
                                    Folien schreiben Agenten (oder du über einen Agenten) im HTML-Folienformat: genau ein <code>&lt;section&gt;</code> je Folie auf 1920 × 1080,
                                    gestaltet über die Klassen des Deck-Themes. Zuerst <code>slides.guide.GET</code> lesen – dort stehen Format, Designregeln und Theme-Klassen –,
                                    dann mehrere Folien in einem Aufruf mit <code>slides.slides.PUT</code> schreiben. Sie erscheinen hier und in einer laufenden Präsentation automatisch.
                                </p>
                                <pre class="m-0 overflow-x-auto rounded-[8px] bg-[color:var(--nx-bg)] p-4 text-xs leading-relaxed text-[color:var(--nx-text)]">slides.slides.PUT {
  "deck_id": {{ $deck->id }},
  "slides": [
    { "key": "cover", "html": "&lt;section class=\"title\"&gt;&lt;p class=\"eyebrow\"&gt;Strategiemeeting&lt;/p&gt;&lt;h1&gt;{{ $deck->title }}&lt;/h1&gt;&lt;aside&gt;Begrüßung&lt;/aside&gt;&lt;/section&gt;" },
    { "key": "agenda", "html": "&lt;section&gt;&lt;h2&gt;Agenda&lt;/h2&gt;&lt;ol&gt;&lt;li class=\"fragment\"&gt;Wo stehen wir?&lt;/li&gt;&lt;/ol&gt;&lt;/section&gt;" }
  ]
}</pre>
                            </div>
                        </div>
                    @else
                        {{-- Große Folie --}}
                        @if($current)
                            <div class="space-y-3">
                                <div wire:key="big-{{ $current->slide_key }}-{{ $hashes[$current->slide_key] }}"
                                     class="overflow-hidden rounded-[10px] border border-[color:var(--nx-line)] bg-black">
                                    @include('slides::livewire.partials.slide-frame', ['doc' => $docs[$current->slide_key], 'size' => $size, 'label' => 'Folie ' . ($currentIndex + 1)])
                                </div>
                                <div class="flex items-center justify-between text-xs text-[color:var(--nx-muted)]">
                                    <span>Folie {{ $currentIndex + 1 }} von {{ $slides->count() }} · <span class="font-mono text-[color:var(--nx-faint)]">{{ $current->slide_key }}</span>
                                        @if($current->is_hidden) · <x-nx-badge>ausgeblendet</x-nx-badge>@endif</span>
                                    <span class="flex items-center gap-1">
                                        @if($currentIndex > 0)
                                            <x-nx-button variant="ghost" size="sm" wire:click="select('{{ $slides[$currentIndex - 1]->slide_key }}')">@svg('heroicon-o-chevron-left', 'w-4 h-4')</x-nx-button>
                                        @endif
                                        @if($currentIndex < $slides->count() - 1)
                                            <x-nx-button variant="ghost" size="sm" wire:click="select('{{ $slides[$currentIndex + 1]->slide_key }}')">@svg('heroicon-o-chevron-right', 'w-4 h-4')</x-nx-button>
                                        @endif
                                    </span>
                                </div>
                                <div class="rounded-[8px] border border-[color:var(--nx-line)] px-4 py-3">
                                    <div class="mb-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Sprechernotizen</div>
                                    @if($current->notes)
                                        <p class="m-0 whitespace-pre-line text-sm leading-relaxed text-[color:var(--nx-text)]">{{ $current->notes }}</p>
                                    @else
                                        <p class="m-0 text-sm text-[color:var(--nx-faint)]">Keine Notizen zu dieser Folie.</p>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Filmstreifen / Raster --}}
                        <div>
                            <div class="mb-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Alle Folien</div>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5">
                                @foreach($slides as $i => $slide)
                                    <button type="button" wire:key="thumb-{{ $slide->slide_key }}-{{ $hashes[$slide->slide_key] }}" wire:click="select('{{ $slide->slide_key }}')"
                                            class="group block text-left">
                                        <div class="relative overflow-hidden rounded-[6px] border bg-black transition-colors {{ $current && $current->id === $slide->id ? 'border-[color:var(--nx-accent)] ring-1 ring-[color:var(--nx-accent)]' : 'border-[color:var(--nx-line)] group-hover:border-[color:var(--nx-line-strong)]' }} {{ $slide->is_hidden ? 'opacity-50' : '' }}">
                                            <div class="pointer-events-none">
                                                @include('slides::livewire.partials.slide-frame', ['doc' => $docs[$slide->slide_key], 'size' => $size, 'label' => 'Folie ' . ($i + 1)])
                                            </div>
                                        </div>
                                        <div class="mt-1 flex items-center justify-between text-[11px] text-[color:var(--nx-muted)]">
                                            <span>{{ $i + 1 }}</span>
                                            <span class="truncate font-mono text-[color:var(--nx-faint)]">{{ $slide->slide_key }}</span>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Seitenspalte --}}
                <aside class="space-y-6 text-sm xl:sticky xl:top-6 xl:self-start">
                    <div class="space-y-2">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Präsentieren</div>
                        <p class="m-0 leading-relaxed text-[color:var(--nx-muted)]">Öffne die Referentenansicht auf deinem Bildschirm und daraus die Präsentation auf dem Beamer – beide bleiben synchron. Änderungen am Deck erscheinen live.</p>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <x-nx-button size="sm" :href="route('slides.decks.presenter', $deck)" target="_blank">@svg('heroicon-o-computer-desktop', 'w-4 h-4') Referentenansicht</x-nx-button>
                            <x-nx-button size="sm" :href="route('slides.decks.pdf', $deck) . '?print=1'" target="_blank">@svg('heroicon-o-document-arrow-down', 'w-4 h-4') PDF</x-nx-button>
                        </div>
                        <p class="m-0 text-xs text-[color:var(--nx-faint)]">Tasten: → / Leertaste weiter · ← zurück · F Vollbild · B schwarz</p>
                    </div>

                    <div class="space-y-1.5 border-t border-[color:var(--nx-line)] pt-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Eckdaten</div>
                        <div class="flex justify-between gap-3"><span class="text-[color:var(--nx-muted)]">Folien</span><span>{{ $slides->where('is_hidden', false)->count() }}@if($slides->where('is_hidden', true)->count()) <span class="text-[color:var(--nx-faint)]">+ {{ $slides->where('is_hidden', true)->count() }} ausgeblendet</span>@endif</span></div>
                        <div class="flex justify-between gap-3"><span class="text-[color:var(--nx-muted)]">Format</span><span>{{ $size->value }}</span></div>
                        <div class="flex justify-between gap-3"><span class="text-[color:var(--nx-muted)]">Owner</span><span class="truncate">{{ $deck->owner?->name ?? '–' }}</span></div>
                        <div class="flex justify-between gap-3"><span class="text-[color:var(--nx-muted)]">Revision</span><span class="tabular-nums">{{ $deck->revision }}</span></div>
                    </div>

                    <div class="space-y-2 border-t border-[color:var(--nx-line)] pt-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Status</div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($statusOptions as $st)
                                @if($canEdit)
                                    <button type="button" wire:click="setStatus('{{ $st->value }}')"
                                            class="rounded-full border px-2.5 py-1 text-xs transition-colors {{ $deck->status === $st ? 'border-[color:var(--nx-accent)] bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]' : 'border-[color:var(--nx-line-strong)] text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' }}">{{ $st->label() }}</button>
                                @elseif($deck->status === $st)
                                    <x-nx-badge :variant="$st->badgeVariant()" dot>{{ $st->label() }}</x-nx-badge>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="space-y-2 border-t border-[color:var(--nx-line)] pt-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Verknüpft mit</div>
                        @forelse($links as $l)
                            <div class="flex items-start gap-2">
                                <x-nx-badge>{{ $l['label'] }}</x-nx-badge>
                                <div class="min-w-0">
                                    @if($l['url'])
                                        <a href="{{ $l['url'] }}" wire:navigate class="block truncate text-[color:var(--nx-text)] hover:underline">{{ $l['title'] }}</a>
                                    @else
                                        <span class="block truncate text-[color:var(--nx-text)]">{{ $l['title'] }}</span>
                                    @endif
                                    @if($l['role'])<span class="text-xs text-[color:var(--nx-faint)]">{{ $l['role'] }}</span>@endif
                                </div>
                            </div>
                        @empty
                            <p class="m-0 leading-relaxed text-[color:var(--nx-muted)]">Noch nirgends verknüpft. Agenten hängen das Deck mit <code>slides.links.POST</code> an ein Gremium, eine Sitzung oder ein Projekt.</p>
                        @endforelse
                    </div>
                </aside>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
