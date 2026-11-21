{{--
    Referentenansicht: aktuelle Folie (mit Klickschritten), nächste Folie, Notizen, Uhr und Timer.
    Steuert ein Präsentationsfenster im selben Browser synchron (BroadcastChannel) – und folgt ihm.
--}}
<div wire:poll.{{ $poll }}s="refreshIfChanged"
     x-data="slidesPlayer(@js($config))"
     @keydown.window="key($event)"
     @slides-updated.window="update($event.detail)"
     style="position:fixed; inset:0; display:flex; flex-direction:column; background:var(--stage-bg);">

    {{-- Kopf --}}
    <div style="height:60px; flex-shrink:0; display:flex; align-items:center; justify-content:space-between; gap:16px; padding:0 20px; border-bottom:1px solid var(--stage-line);">
        <div style="display:flex; align-items:center; gap:12px; min-width:0;">
            <a href="{{ route('slides.decks.show', $deck) }}" class="st-btn icon" title="Zurück zum Deck"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 5l-5 5 5 5"/></svg></a>
            <div style="min-width:0;">
                <div style="font-size:15px; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $deck->title }}</div>
                <div class="st-faint" style="font-size:12px;" x-text="ago()"></div>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:18px;">
            <div style="text-align:right;">
                <div class="st-faint" style="font-size:11px; letter-spacing:.06em; text-transform:uppercase;">Uhrzeit</div>
                <div style="font-size:20px; font-weight:600; font-variant-numeric:tabular-nums;" x-text="clock()"></div>
            </div>
            <div style="text-align:right;">
                <div class="st-faint" style="font-size:11px; letter-spacing:.06em; text-transform:uppercase;">Dauer</div>
                <div style="font-size:20px; font-weight:600; font-variant-numeric:tabular-nums;" :style="{ color: paused && elapsed ? 'var(--stage-warn)' : '' }" x-text="fmt(elapsed)"></div>
            </div>
            <div style="display:flex; gap:6px;">
                <button type="button" class="st-btn" @click="toggleTimer()" x-text="paused ? (elapsed ? 'Fortsetzen' : 'Timer starten') : 'Pause'"></button>
                <button type="button" class="st-btn icon" @click="resetTimer()" title="Timer zurücksetzen"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 10a6 6 0 1 0 2-4.5M4 4v3h3"/></svg></button>
            </div>
            <button type="button" class="st-btn" @click="openPresent()" title="Präsentation in eigenem Fenster öffnen – auf den Beamer ziehen, dann F für Vollbild">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4" width="14" height="9" rx="1.5"/><path d="M10 13v3M7 16h6"/></svg>
                Präsentation öffnen
            </button>
        </div>
    </div>

    {{-- Inhalt --}}
    <div style="flex:1; min-height:0; display:grid; grid-template-columns:minmax(0,1.7fr) minmax(320px,1fr); gap:20px; padding:20px;">
        {{-- Aktuelle Folie --}}
        <div style="display:flex; flex-direction:column; min-height:0; gap:12px;">
            <div style="display:flex; align-items:baseline; justify-content:space-between;">
                <span class="st-faint" style="font-size:11px; letter-spacing:.06em; text-transform:uppercase;">Aktuell</span>
                <span class="st-muted" style="font-size:13px; font-variant-numeric:tabular-nums;">
                    Folie <span x-text="slides.length ? index + 1 : 0"></span> von <span x-text="slides.length"></span>
                    <template x-if="steps > 0"><span x-text="' · Schritt ' + step + ' von ' + steps"></span></template>
                </span>
            </div>
            <div style="flex:1; min-height:0; display:flex; align-items:flex-start; justify-content:center;">
                <div x-ref="stage" :style="{ width: `min(100%, calc((100vh - 250px) * ${w} / ${h}))`, aspectRatio: `${w} / ${h}` }"
                     style="position:relative; overflow:hidden; background:#000; border-radius:6px; outline:1px solid var(--stage-line); cursor:pointer;" @click="next()">
                    <div x-ref="stack" style="position:absolute; inset:0;">
                        <template x-for="(s, i) in slides" :key="s.key">
                            <iframe sandbox="allow-same-origin" tabindex="-1" :title="'Folie ' + (i + 1)" :data-key="s.key"
                                    x-init="$el.dataset.hash = s.hash; $el.srcdoc = s.doc" @load="loaded(i)"
                                    :style="{ visibility: i === index ? 'visible' : 'hidden', transform: `scale(${scale})` }"
                                    style="position:absolute; top:0; left:0; border:0; transform-origin:0 0; pointer-events:none;"
                                    :width="w" :height="h"></iframe>
                        </template>
                    </div>
                    <div x-show="black" x-cloak style="position:absolute; inset:0; background:#000; display:flex; align-items:center; justify-content:center;">
                        <span class="st-faint" style="font-size:14px;">Bildschirm schwarz (B)</span>
                    </div>
                </div>
            </div>
            <div style="display:flex; align-items:center; justify-content:center; gap:8px;">
                <button type="button" class="st-btn" @click="prev()"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 5l-5 5 5 5"/></svg> Zurück</button>
                <button type="button" class="st-btn" @click="next()">Weiter <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 5l5 5-5 5"/></svg></button>
                <button type="button" class="st-btn" @click="setBlack(!black)" x-text="black ? 'Bild zeigen' : 'Schwarz'"></button>
            </div>
        </div>

        {{-- Nächste Folie + Notizen --}}
        <div style="display:flex; flex-direction:column; min-height:0; gap:16px;">
            <div>
                <div class="st-faint" style="font-size:11px; letter-spacing:.06em; text-transform:uppercase; margin-bottom:8px;">Als Nächstes</div>
                <div x-ref="nextBox" :style="{ aspectRatio: `${w} / ${h}` }" style="position:relative; overflow:hidden; background:#000; border-radius:6px; outline:1px solid var(--stage-line);">
                    <iframe x-ref="next" sandbox="allow-same-origin" tabindex="-1" title="Nächste Folie"
                            :style="{ transform: `scale(${nextScale})`, visibility: slides[index + 1] ? 'visible' : 'hidden' }"
                            style="position:absolute; top:0; left:0; border:0; transform-origin:0 0; pointer-events:none;"
                            :width="w" :height="h"></iframe>
                    <div x-show="!slides[index + 1]" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center;">
                        <span class="st-faint" style="font-size:14px;">Ende der Präsentation</span>
                    </div>
                </div>
            </div>
            <div style="flex:1; min-height:0; display:flex; flex-direction:column; background:var(--stage-panel); border:1px solid var(--stage-line); border-radius:8px;">
                <div class="st-faint" style="font-size:11px; letter-spacing:.06em; text-transform:uppercase; padding:12px 16px 0;">Notizen</div>
                <div style="flex:1; overflow:auto; padding:8px 16px 16px; font-size:20px; line-height:1.55; white-space:pre-line;"
                     x-text="slides[index]?.notes || '–'" :class="slides[index]?.notes ? '' : 'st-faint'"></div>
            </div>
        </div>
    </div>

    <div x-show="toast" x-cloak x-transition.opacity
         style="position:absolute; top:72px; right:20px; display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:8px;
                background:rgba(23,26,31,.95); border:1px solid var(--stage-line); font-size:13px; color:var(--stage-muted);">
        <span style="width:7px; height:7px; border-radius:999px; background:var(--stage-live);"></span><span x-text="toast"></span>
    </div>

    @include('slides::livewire.partials.player-script')
</div>
