{{--
    Präsentationsmodus. Navigation, Klickschritte, URL (?slide=n), Vollbild und Sync laufen im Browser (partials.player-script).
    wire:poll prüft nur die revision; Änderungen kommen als Event „slides-updated“, die Seite rendert nicht neu.
--}}
<div wire:poll.{{ $poll }}s="refreshIfChanged"
     x-data="slidesPlayer(@js($config))"
     @keydown.window="key($event)" @mousemove.window="touch()"
     @slides-updated.window="update($event.detail)"
     :style="{ cursor: cursorIdle ? 'none' : '' }"
     style="position:fixed; inset:0; display:flex; flex-direction:column; background:var(--stage-bg); user-select:none;">

    {{-- Bühne --}}
    <div style="flex:1; min-height:0; display:flex; align-items:center; justify-content:center;" @click="next()">
        <template x-if="slides.length === 0">
            <div style="max-width:520px; text-align:center; line-height:1.6;">
                <p style="font-size:20px; margin:0 0 8px;">Dieses Deck hat noch keine sichtbaren Folien.</p>
                <p class="st-muted" style="margin:0;">Sobald ein Agent oder du Folien schreibt, erscheinen sie hier automatisch.</p>
            </div>
        </template>
        <div x-ref="stage" x-show="slides.length > 0"
             :style="{ width: `min(100vw, calc(100vh * ${w} / ${h}))`, aspectRatio: `${w} / ${h}` }"
             style="position:relative; overflow:hidden; background:#000;">
            <div x-ref="stack" style="position:absolute; inset:0;">
                <template x-for="(s, i) in slides" :key="s.key">
                    <iframe sandbox="allow-same-origin" tabindex="-1" :title="'Folie ' + (i + 1)" :data-key="s.key"
                            x-init="$el.dataset.hash = s.hash; $el.srcdoc = s.doc" @load="loaded(i)"
                            :style="{ visibility: i === index ? 'visible' : 'hidden', transform: `scale(${scale})` }"
                            style="position:absolute; top:0; left:0; border:0; transform-origin:0 0; pointer-events:none;"
                            :width="w" :height="h"></iframe>
                </template>
            </div>
            <div x-show="black" x-cloak style="position:absolute; inset:0; background:#000;"></div>
        </div>
    </div>

    {{-- Fortschritt --}}
    <div style="height:3px; background:rgba(255,255,255,.06);">
        <div :style="{ width: `${slides.length ? ((index + 1) / slides.length) * 100 : 0}%` }" style="height:100%; background:var(--stage-accent); opacity:.55; transition:width .25s ease;"></div>
    </div>

    {{-- Steuerleiste: blendet mit dem Mauszeiger aus --}}
    <div :style="{ opacity: cursorIdle ? 0 : 1, pointerEvents: cursorIdle ? 'none' : '' }"
         style="position:absolute; left:0; right:0; bottom:3px; height:56px; display:flex; align-items:center; justify-content:space-between; padding:0 18px;
                background:linear-gradient(to top, rgba(14,16,19,.92), rgba(14,16,19,0)); transition:opacity .3s ease;">
        <a href="{{ route('slides.decks.show', $deck) }}" class="st-btn" title="Zurück zum Deck">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 5l-5 5 5 5"/></svg>
            <span style="max-width:36vw; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $deck->title }}</span>
        </a>
        <div style="display:flex; align-items:center; gap:8px;">
            <button type="button" class="st-btn icon" @click.stop="prev()" title="Zurück (←)"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 5l-5 5 5 5"/></svg></button>
            <span class="st-muted" style="font-size:13px; min-width:88px; text-align:center; font-variant-numeric:tabular-nums;">
                <span x-text="slides.length ? index + 1 : 0"></span> / <span x-text="slides.length"></span>
                <template x-if="steps > 0"><span class="st-faint" x-text="' · ' + step + '/' + steps"></span></template>
            </span>
            <button type="button" class="st-btn icon" @click.stop="next()" title="Weiter (→, Leertaste)"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 5l5 5-5 5"/></svg></button>
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
            <a href="{{ route('slides.decks.presenter', $deck) }}" target="slides-presenter-{{ $deck->uuid }}" class="st-btn" title="Referentenansicht in neuem Fenster">Referentenansicht</a>
            <button type="button" class="st-btn icon" @click.stop="setBlack(!black)" title="Bildschirm schwarz (B)"><svg viewBox="0 0 20 20" fill="currentColor"><rect x="4" y="5" width="12" height="10" rx="1.5"/></svg></button>
            <button type="button" class="st-btn icon" @click.stop="fullscreen()" title="Vollbild (F)"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 8V4h4M16 8V4h-4M4 12v4h4M16 12v4h-4"/></svg></button>
        </div>
    </div>

    {{-- Hinweis bei Live-Änderung --}}
    <div x-show="toast" x-cloak x-transition.opacity
         style="position:absolute; top:16px; right:16px; display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:8px;
                background:rgba(23,26,31,.92); border:1px solid var(--stage-line); font-size:13px; color:var(--stage-muted);">
        <span style="width:7px; height:7px; border-radius:999px; background:var(--stage-live);"></span><span x-text="toast"></span>
    </div>

    @include('slides::livewire.partials.player-script')
</div>
