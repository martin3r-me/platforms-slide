{{--
    Gemeinsamer Folien-Player (Alpine) für Präsentation und Referentenansicht.
    - Alle Folien liegen als gestapelte iframes vor (sandbox ohne Skripte); Blättern blendet nur um → kein Neuladen, kein Flackern.
    - Klickschritte: Das Eltern-Fenster setzt .visible/.current-fragment im iframe-DOM (allow-same-origin, keine Skripte in der Folie nötig).
    - Live: Livewire schickt „slides-updated“; nur Folien mit geändertem Hash bekommen neues srcdoc, Position und Schritt bleiben.
    - Sync: BroadcastChannel je Deck – Präsentation und Referentenansicht folgen einander.
--}}
@once
<script>
window.slidesPlayer = function (cfg) {
    return {
        slides: cfg.slides,
        index: Math.min(Math.max(0, cfg.index || 0), Math.max(0, cfg.slides.length - 1)),
        step: 0,
        steps: 0,
        mode: cfg.mode,
        w: cfg.w, h: cfg.h,
        scale: 1,
        nextScale: 1,
        editor: cfg.editor || {},
        toast: null,
        cursorIdle: false,
        black: false,
        now: new Date(),
        startedAt: null,
        elapsed: 0,
        paused: true,
        _ch: null, _me: Math.random().toString(36).slice(2), _idle: null, _tick: null,

        init() {
            try {
                this._ch = new BroadcastChannel(cfg.channel);
                this._ch.onmessage = (e) => this.onRemote(e.data);
                // Referentenansicht fragt ein offenes Präsentationsfenster nach dessen Stand
                if (this.mode === 'presenter') this._ch.postMessage({ type: 'hello', from: this._me });
            } catch (e) {}
            this._tick = setInterval(() => {
                this.now = new Date();
                if (!this.paused && this.startedAt) this.elapsed = Math.floor((Date.now() - this.startedAt) / 1000);
            }, 1000);
            this.$nextTick(() => { this.fit(); this.syncUrl(); });
            new ResizeObserver(() => this.fit()).observe(this.$refs.stage);
            if (this.$refs.nextBox) new ResizeObserver(() => { this.nextScale = this.$refs.nextBox.clientWidth / this.w; }).observe(this.$refs.nextBox);
            if (this.mode === 'presenter') this.$watch('index', () => this.loadNext());
            this.loadNext();
            this.touch();
        },

        // ── Darstellung ──────────────────────────────────────────
        fit() { const el = this.$refs.stage; if (el) this.scale = el.clientWidth / this.w; },
        frame(i) { return this.$refs.stack ? this.$refs.stack.querySelector('iframe[data-key="' + (this.slides[i]?.key || '') + '"]') : null; },
        groups(i) {
            const f = this.frame(i); const doc = f && f.contentDocument; if (!doc || !doc.body) return [];
            let auto = 0; const map = new Map();
            doc.querySelectorAll('.fragment').forEach((el) => {
                const raw = el.getAttribute('data-fragment-index');
                const k = raw !== null && raw !== '' && !isNaN(+raw) ? +raw : auto++;
                if (!map.has(k)) map.set(k, []); map.get(k).push(el);
            });
            return [...map.keys()].sort((a, b) => a - b).map((k) => map.get(k));
        },
        apply(i, step) {
            const g = this.groups(i);
            g.forEach((els, n) => els.forEach((el) => {
                el.classList.toggle('visible', n < step);
                el.classList.toggle('current-fragment', n === step - 1);
            }));
            if (i === this.index) this.steps = g.length;
        },
        loaded(i) {
            // iframe (neu) geladen: Klickschritte für diese Folie wiederherstellen
            if (i === this.index) { this.step = Math.min(this.step, this.groups(i).length); this.apply(i, this.step); }
            else this.apply(i, i < this.index ? 999 : 0);
        },
        loadNext() {
            if (this.mode !== 'presenter' || !this.$refs.next) return;
            const n = this.slides[this.index + 1];
            const f = this.$refs.next;
            if (!n) { f.removeAttribute('srcdoc'); f.dataset.hash = ''; return; }
            if (f.dataset.hash !== n.hash) { f.dataset.hash = n.hash; f.srcdoc = n.doc; }
        },

        // ── Navigation ───────────────────────────────────────────
        next() {
            const total = this.groups(this.index).length;
            if (this.step < total) { this.step++; this.apply(this.index, this.step); }
            else if (this.index < this.slides.length - 1) { this.index++; this.step = 0; this.apply(this.index, 0); }
            this.after();
        },
        prev() {
            if (this.step > 0) { this.step--; this.apply(this.index, this.step); }
            else if (this.index > 0) { this.index--; this.step = this.groups(this.index).length; this.apply(this.index, this.step); }
            this.after();
        },
        go(i, step = 0) {
            this.index = Math.max(0, Math.min(i, this.slides.length - 1));
            this.step = Math.min(step, this.groups(this.index).length);
            this.apply(this.index, this.step);
            this.after();
        },
        after(remote = false) {
            this.steps = this.groups(this.index).length;
            this.syncUrl();
            if (!remote && this._ch) this._ch.postMessage({ type: 'nav', index: this.index, step: this.step, from: this._me });
            if (!this.paused && !this.startedAt) this.startedAt = Date.now();
        },
        onRemote(m) {
            if (!m || m.from === this._me) return;
            if (m.type === 'nav') {
                this.index = Math.max(0, Math.min(m.index, this.slides.length - 1));
                this.step = Math.min(m.step || 0, this.groups(this.index).length);
                this.apply(this.index, this.step);
                this.after(true);
            } else if (m.type === 'black') { this.black = !!m.on; }
            else if (m.type === 'hello' && this.mode === 'present') {
                // Referentenansicht kam dazu: aktuellen Stand melden
                this._ch.postMessage({ type: 'nav', index: this.index, step: this.step, from: this._me });
            }
        },
        syncUrl() {
            try { const u = new URL(location.href); u.searchParams.set('slide', this.index + 1); history.replaceState(history.state, '', u); } catch (e) {}
        },
        key(e) {
            if (e.target && /input|textarea|select/i.test(e.target.tagName)) return;
            const k = e.key;
            if (['ArrowRight', 'ArrowDown', 'PageDown', ' ', 'Enter'].includes(k)) { e.preventDefault(); this.next(); }
            else if (['ArrowLeft', 'ArrowUp', 'PageUp', 'Backspace'].includes(k)) { e.preventDefault(); this.prev(); }
            else if (k === 'Home') { e.preventDefault(); this.go(0); }
            else if (k === 'End') { e.preventDefault(); this.go(this.slides.length - 1, 999); }
            else if (k === 'f' || k === 'F') { this.fullscreen(); }
            else if (k === 'b' || k === 'B' || k === '.') { this.setBlack(!this.black); }
            this.touch();
        },
        setBlack(on) { this.black = on; if (this._ch) this._ch.postMessage({ type: 'black', on, from: this._me }); },
        fullscreen() {
            const el = document.documentElement;
            if (!document.fullscreenElement) { el.requestFullscreen?.(); } else { document.exitFullscreen?.(); }
        },
        touch() {
            this.cursorIdle = false; clearTimeout(this._idle);
            this._idle = setTimeout(() => { this.cursorIdle = true; }, 2200);
        },
        openPresent() {
            window.open(cfg.presentUrl + '?slide=' + (this.index + 1), 'slides-present-' + cfg.channel);
            setTimeout(() => this._ch && this._ch.postMessage({ type: 'nav', index: this.index, step: this.step, from: this._me }), 1500);
        },

        // ── Timer (Referentenansicht) ────────────────────────────
        toggleTimer() {
            if (this.paused) { this.startedAt = Date.now() - this.elapsed * 1000; this.paused = false; }
            else { this.paused = true; }
        },
        resetTimer() { this.elapsed = 0; this.startedAt = this.paused ? null : Date.now(); },
        fmt(s) { const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), x = s % 60; return (h ? h + ':' : '') + String(m).padStart(2, '0') + ':' + String(x).padStart(2, '0'); },
        clock() { return this.now.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' }); },
        ago() {
            if (!this.editor.at) return '';
            const s = Math.max(0, Math.floor((this.now - new Date(this.editor.at)) / 1000));
            const t = s < 60 ? 'vor ' + s + ' s' : s < 3600 ? 'vor ' + Math.floor(s / 60) + ' Min.' : s < 86400 ? 'vor ' + Math.floor(s / 3600) + ' Std.' : 'am ' + new Date(this.editor.at).toLocaleDateString('de-DE');
            return 'aktualisiert' + (this.editor.name ? ' von ' + this.editor.name : '') + ' ' + t;
        },

        // ── Live-Aktualisierung ──────────────────────────────────
        update(d) {
            const curKey = this.slides[this.index]?.key;
            const old = new Map(this.slides.map((s) => [s.key, s]));
            const changed = [];
            const next = (d.slides || []).map((s) => {
                const o = old.get(s.key);
                if (o && o.hash === s.hash) { o.notes = s.notes; return o; }
                changed.push(s.key);
                return s;
            });
            this.slides = next;
            this.editor = d.editor || this.editor;
            const ni = next.findIndex((s) => s.key === curKey);
            this.index = ni >= 0 ? ni : Math.min(this.index, Math.max(0, next.length - 1));
            this.$nextTick(() => {
                changed.forEach((k) => {
                    const f = this.$refs.stack && this.$refs.stack.querySelector('iframe[data-key="' + k + '"]');
                    const s = this.slides.find((x) => x.key === k);
                    if (f && s && f.dataset.hash !== s.hash) { f.dataset.hash = s.hash; f.srcdoc = s.doc; }
                });
                this.loadNext();
                this.steps = this.groups(this.index).length;
                this.syncUrl();
            });
            if (changed.length) {
                this.toast = this.ago() || 'aktualisiert';
                clearTimeout(this._toastT); this._toastT = setTimeout(() => { this.toast = null; }, 5000);
            }
        },
    };
};
</script>
@endonce
