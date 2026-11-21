{{--
    Deck-Ansicht (nx): große ausgewählte Folie, Filmstreifen, Notizen; rechts Eckdaten, Verknüpfungen, Status.
    Folien sind per Inhalts-Hash verschlüsselt (wire:key) – bei Live-Änderungen werden nur geänderte Folien ersetzt.
    Seitenspalte zusätzlich: Layout-Prüfung, Versionen (festhalten/wiederherstellen), Bilder.
--}}
@php
    $levelVariant = fn ($l) => ['error' => 'danger', 'warning' => 'warning'][$l] ?? 'neutral';
    $levelLabel = fn ($l) => ['error' => 'Fehler', 'warning' => 'Hinweis'][$l] ?? 'Info';
    $checkSummary = $check === null ? null : [
        'errors' => collect($check)->sum(fn ($c) => collect($c['issues'])->where('level', 'error')->count()),
        'warnings' => collect($check)->sum(fn ($c) => collect($c['issues'])->where('level', 'warning')->count()),
    ];
@endphp
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
                    <x-nx-dropdown-item wire:click="runCheck">@svg('heroicon-o-magnifying-glass-circle', 'w-4 h-4') Layout prüfen</x-nx-dropdown-item>
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

            @if($notice)
                <div class="mb-5" wire:key="notice-{{ md5($notice) }}">
                    <x-nx-callout variant="info">{{ $notice }}</x-nx-callout>
                </div>
            @endif

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
                                @if($check !== null && isset($check[$current->slide_key]))
                                    @php $ci = $check[$current->slide_key]; @endphp
                                    <div class="rounded-[8px] border border-[color:var(--nx-line)] px-4 py-3">
                                        <div class="mb-2 flex items-center justify-between gap-3">
                                            <span class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Layout-Prüfung</span>
                                            @if($ci['fill'] !== null)
                                                <span class="text-xs text-[color:var(--nx-muted)]" title="Wie weit der Inhalt die nutzbare Höhe füllt">Füllgrad {{ $ci['fill'] }} %</span>
                                            @endif
                                        </div>
                                        @forelse($ci['issues'] as $issue)
                                            <div class="flex items-start gap-2 py-1 text-sm">
                                                <x-nx-badge :variant="$levelVariant($issue['level'])">{{ $levelLabel($issue['level']) }}</x-nx-badge>
                                                <div class="min-w-0 leading-relaxed text-[color:var(--nx-text)]">
                                                    {{ $issue['message'] }}
                                                    @if(! empty($issue['element']))<span class="block truncate font-mono text-xs text-[color:var(--nx-faint)]">{{ $issue['element'] }}</span>@endif
                                                </div>
                                            </div>
                                        @empty
                                            <p class="m-0 text-sm text-[color:var(--nx-success)]">Keine Probleme auf dieser Folie.</p>
                                        @endforelse
                                    </div>
                                @endif
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
                                            <span class="flex items-center gap-1.5">{{ $i + 1 }}
                                                @if($check !== null && isset($check[$slide->slide_key]))
                                                    @php $sl = collect($check[$slide->slide_key]['issues']); $e = $sl->where('level', 'error')->count(); $w = $sl->where('level', 'warning')->count(); @endphp
                                                    @if($e)<span class="h-2 w-2 rounded-full bg-[color:var(--nx-danger)]" title="{{ $e }} Fehler"></span>
                                                    @elseif($w)<span class="h-2 w-2 rounded-full bg-[color:var(--nx-warning)]" title="{{ $w }} Hinweise"></span>
                                                    @else<span class="h-2 w-2 rounded-full bg-[color:var(--nx-success)]" title="Keine Probleme"></span>@endif
                                                @endif
                                            </span>
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

                    @if($slides->isNotEmpty())
                    <div class="space-y-2 border-t border-[color:var(--nx-line)] pt-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Qualität</div>
                        @if($checkSummary === null)
                            <p class="m-0 leading-relaxed text-[color:var(--nx-muted)]">Rendert jede Folie im Browser und misst, was über den Rand ragt, abgeschnitten oder zu klein ist. Agenten machen das selbst mit <code>slides.preview.GET</code>.</p>
                        @else
                            <div class="flex flex-wrap items-center gap-1.5">
                                @if($checkSummary['errors'])<x-nx-badge variant="danger" dot>{{ $checkSummary['errors'] }} Fehler</x-nx-badge>@endif
                                @if($checkSummary['warnings'])<x-nx-badge variant="warning" dot>{{ $checkSummary['warnings'] }} Hinweise</x-nx-badge>@endif
                                @if(! $checkSummary['errors'] && ! $checkSummary['warnings'])<x-nx-badge :variant="$checkMeasured ? 'success' : 'neutral'" dot>{{ $checkMeasured ? 'Alles im Rahmen' : 'Inhalt ok' }}</x-nx-badge>@endif
                            </div>
                            @unless($checkMeasured)<p class="m-0 text-xs text-[color:var(--nx-faint)]">Nur Inhaltsregeln – das Layout wurde nicht gemessen (kein Headless-Browser auf dem Server).</p>@endunless
                            @if($checkStale)<p class="m-0 text-xs text-[color:var(--nx-warning)]">Das Deck wurde seit der Prüfung geändert.</p>@endif
                        @endif
                        <x-nx-button size="sm" wire:click="runCheck" wire:loading.attr="disabled" wire:target="runCheck">
                            <span wire:loading.remove wire:target="runCheck" class="inline-flex items-center gap-1.5">@svg('heroicon-o-magnifying-glass-circle', 'w-4 h-4') {{ $checkSummary === null ? 'Layout prüfen' : 'Erneut prüfen' }}</span>
                            <span wire:loading wire:target="runCheck">Prüfe …</span>
                        </x-nx-button>
                    </div>
                    @endif

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

                    <div class="space-y-2 border-t border-[color:var(--nx-line)] pt-4">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Versionen</span>
                            @if($versionCount > $versions->count())<span class="text-xs text-[color:var(--nx-faint)]">{{ $versions->count() }} von {{ $versionCount }}</span>@endif
                        </div>
                        <p class="m-0 leading-relaxed text-[color:var(--nx-muted)]">Vor jeder Änderungsrunde wird der bisherige Stand gesichert. Wiederherstellen ist selbst umkehrbar.</p>
                        @if($canEdit)
                            <form wire:submit="createVersion" class="flex gap-1.5">
                                <input type="text" wire:model="versionLabel" placeholder="Name, z. B. Vor Kapitel 2" maxlength="200"
                                       class="min-w-0 flex-1 rounded-[6px] border border-[color:var(--nx-line-strong)] bg-transparent px-2 py-1 text-xs text-[color:var(--nx-text)] placeholder:text-[color:var(--nx-faint)] focus:border-[color:var(--nx-accent)] focus:outline-none">
                                <x-nx-button size="sm" type="submit">Festhalten</x-nx-button>
                            </form>
                        @endif
                        @forelse($versions as $v)
                            <div wire:key="version-{{ $v->id }}" class="group flex items-start justify-between gap-2 rounded-[6px] px-2 py-1.5 hover:bg-[color:var(--nx-hover)]">
                                <div class="min-w-0">
                                    <div class="truncate text-[color:var(--nx-text)]">{{ $v->label ?: $v->kindLabel() }}</div>
                                    <div class="text-xs text-[color:var(--nx-faint)]">#{{ $v->id }} · {{ $v->created_at?->diffForHumans() }}@if($v->creator) · {{ $v->creator->name }}@endif · {{ $v->slide_count }} Folien</div>
                                </div>
                                @if($canEdit)
                                    <button type="button" wire:click="restoreVersion({{ $v->id }})"
                                            wire:confirm="Deck auf Version #{{ $v->id }} zurücksetzen? Der aktuelle Stand wird vorher gesichert."
                                            class="shrink-0 rounded-[6px] px-1.5 py-0.5 text-xs text-[color:var(--nx-muted)] opacity-0 transition-opacity hover:text-[color:var(--nx-text)] group-hover:opacity-100 focus:opacity-100"
                                            title="Diese Version wiederherstellen">@svg('heroicon-o-arrow-uturn-left', 'w-4 h-4')</button>
                                @endif
                            </div>
                        @empty
                            <p class="m-0 text-xs text-[color:var(--nx-faint)]">Noch keine Versionen.</p>
                        @endforelse
                    </div>

                    <div class="space-y-2 border-t border-[color:var(--nx-line)] pt-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[color:var(--nx-faint)]">Bilder</div>
                        <p class="m-0 leading-relaxed text-[color:var(--nx-muted)]">Logos, Fotos, Grafiken für die Folien. Agenten finden sie mit <code>slides.assets.GET</code> und setzen sie selbst ein.</p>
                        @if($canEdit)
                            <label class="flex cursor-pointer items-center justify-center gap-2 rounded-[8px] border border-dashed border-[color:var(--nx-line-strong)] px-3 py-3 text-xs text-[color:var(--nx-muted)] transition-colors hover:bg-[color:var(--nx-hover)]">
                                <input type="file" wire:model="upload" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml" class="sr-only">
                                <span wire:loading.remove wire:target="upload" class="inline-flex items-center gap-1.5">@svg('heroicon-o-photo', 'w-4 h-4') Bild hochladen</span>
                                <span wire:loading wire:target="upload">Lädt hoch …</span>
                            </label>
                            @error('upload')<p class="m-0 text-xs text-[color:var(--nx-danger)]">{{ $message }}</p>@enderror
                        @endif
                        @if($assets->isNotEmpty())
                            <div class="grid grid-cols-3 gap-2">
                                @foreach($assets as $a)
                                    <div wire:key="asset-{{ $a->id }}" class="group relative" x-data="{ copied: false }">
                                        <button type="button" title="{{ $a->original_name }} – Link kopieren"
                                                x-on:click="navigator.clipboard.writeText(@js($a->url())); copied = true; setTimeout(() => copied = false, 1500)"
                                                class="block aspect-square w-full overflow-hidden rounded-[6px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)]">
                                            <img src="{{ $a->url() }}" alt="{{ $a->alt ?? $a->original_name }}" loading="lazy" class="h-full w-full object-contain">
                                        </button>
                                        <span x-show="copied" x-cloak class="absolute inset-x-0 bottom-0 rounded-b-[6px] bg-[color:var(--nx-accent)] py-0.5 text-center text-[10px] text-[color:var(--nx-on-accent)]">kopiert</span>
                                        @if($canEdit)
                                            <button type="button" wire:click="deleteAsset({{ $a->id }})" wire:confirm="Bild löschen?"
                                                    class="absolute right-1 top-1 hidden rounded-full bg-[color:var(--nx-bg)] p-0.5 text-[color:var(--nx-muted)] hover:text-[color:var(--nx-danger)] group-hover:block"
                                                    title="Löschen">@svg('heroicon-o-x-mark', 'w-3.5 h-3.5')</button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </aside>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
