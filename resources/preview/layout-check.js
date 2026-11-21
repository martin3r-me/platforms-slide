// Layout-Prüfung im Headless-Chrome (PreviewService::inspect). Läuft in der Host-Seite, die jede Folie als
// <iframe data-key srcdoc> in Originalgröße enthält, und liefert je Folie gemessene Probleme als JSON.
// Muss ein einzelner Ausdruck sein (Browsershot evaluate).
(async () => {
  const MIN_FONT = 22;          // Theme-Untergrenze (Fußzeile, Tags); Fließtext soll ≥ 24px sein
  const MAX_PER_CODE = 4;
  const out = {};
  const frames = Array.from(document.querySelectorAll('iframe[data-key]'));
  for (const f of frames) {
    const key = f.dataset.key;
    const issues = [];
    const counts = {};
    const add = (level, code, message, el) => {
      counts[code] = (counts[code] || 0) + 1;
      if (counts[code] <= MAX_PER_CODE) issues.push({ level, code, message, element: el ? describe(el) : null });
    };
    const d = f.contentDocument;
    if (!d) { out[key] = [{ level: 'error', code: 'render_failed', message: 'Folie konnte nicht gerendert werden.' }]; continue; }
    try { if (d.fonts && d.fonts.ready) await d.fonts.ready; } catch (e) {}
    const win = d.defaultView;
    const s = d.querySelector('body > section');
    if (!s) { out[key] = [{ level: 'error', code: 'no_section', message: 'Kein <section> gefunden.' }]; continue; }

    const sr = s.getBoundingClientRect();
    const cs = win.getComputedStyle(s);
    const box = {
      l: sr.left + parseFloat(cs.paddingLeft), r: sr.right - parseFloat(cs.paddingRight),
      t: sr.top + parseFloat(cs.paddingTop), b: sr.bottom - parseFloat(cs.paddingBottom),
    };

    function describe(el) {
      const tag = el.tagName.toLowerCase();
      const cls = typeof el.className === 'string' && el.className.trim() ? '.' + el.className.trim().split(/\s+/).join('.') : '';
      let t = (el.textContent || el.getAttribute('aria-label') || el.getAttribute('alt') || '').trim().replace(/\s+/g, ' ');
      if (t.length > 60) t = t.slice(0, 60) + '…';
      return tag + cls + (t ? ' „' + t + '“' : '');
    }
    const visible = el => {
      const st = win.getComputedStyle(el);
      if (st.display === 'none' || st.visibility === 'hidden' || parseFloat(st.opacity) === 0) return false;
      const r = el.getBoundingClientRect();
      return r.width > 0 && r.height > 0;
    };
    const positioned = el => {
      for (let e = el; e && e !== s; e = e.parentElement) {
        const p = win.getComputedStyle(e).position;
        if (p === 'absolute' || p === 'fixed') return true;
      }
      return false;
    };
    const inFooter = el => !!el.closest('.footer, footer');
    const ownText = el => Array.from(el.childNodes).some(n => n.nodeType === 3 && n.textContent.trim() !== '');
    const media = el => ['img', 'svg', 'table', 'figure'].includes(el.tagName.toLowerCase());
    const els = Array.from(s.querySelectorAll('*')).filter(el => !el.closest('aside') && (el.tagName.toLowerCase() === 'svg' || !el.closest('svg')) && visible(el));

    // 1. Über den Folienrand hinaus (nur das äußerste betroffene Element melden)
    const over = el => { const r = el.getBoundingClientRect(); return Math.max(r.right - sr.right, r.bottom - sr.bottom, sr.left - r.left, sr.top - r.top); };
    for (const el of els) {
      const o = over(el);
      if (o > 2 && !(el.parentElement && el.parentElement !== s && over(el.parentElement) > 2)) {
        const r = el.getBoundingClientRect();
        const side = r.bottom - sr.bottom > 2 ? 'unten' : r.right - sr.right > 2 ? 'rechts' : r.left < sr.left ? 'links' : 'oben';
        add('error', 'overflow', `Ragt ${Math.round(o)} px ${side} über die Folie hinaus – wird abgeschnitten. Inhalt kürzen oder Folie teilen.`, el);
      }
    }
    // 2. Außerhalb des Satzspiegels (Innenrand), Fußzeile und absolut positionierte Elemente ausgenommen
    for (const el of els) {
      if (!(ownText(el) || media(el)) || inFooter(el) || positioned(el) || over(el) > 2) continue;
      const r = el.getBoundingClientRect();
      const o = Math.max(r.right - box.r, r.bottom - box.b, box.l - r.left, box.t - r.top);
      if (o > 4) add('warning', 'outside_safe_area', `Liegt ${Math.round(o)} px außerhalb des Innenrands (Satzspiegel).`, el);
    }
    // 3. Abgeschnittener Inhalt in Containern mit overflow:hidden
    for (const el of [s, ...els]) {
      const st = win.getComputedStyle(el);
      if (!['hidden', 'clip'].includes(st.overflowY) && !['hidden', 'clip'].includes(st.overflowX)) continue;
      if (!el.textContent.trim()) continue;
      const dy = el.scrollHeight - el.clientHeight, dx = el.scrollWidth - el.clientWidth;
      if (dy > 2 || dx > 2) add('error', 'clipped', `Inhalt passt nicht in den Rahmen (${Math.round(Math.max(dy, dx))} px abgeschnitten).`, el);
    }
    // 4. Zu kleine Schrift
    for (const el of els) {
      if (!ownText(el)) continue;
      const fs = parseFloat(win.getComputedStyle(el).fontSize);
      if (fs < MIN_FONT) add('warning', 'small_text', `Schrift ${Math.round(fs)} px – auf dem Beamer kaum lesbar (mind. 24 px).`, el);
    }
    // 5. Bilder, die nicht laden
    for (const img of Array.from(s.querySelectorAll('img'))) {
      if (!img.complete || img.naturalWidth === 0) add('error', 'image_broken', 'Bild lädt nicht (URL prüfen oder Bild über slides.assets.POST hochladen).', img);
    }
    // 6. Überlappende Textblöcke (Geschwister im Fluss)
    const blocks = els.filter(el => (ownText(el) || media(el)) && !positioned(el) && !inFooter(el));
    for (let i = 0; i < blocks.length; i++) {
      for (let j = i + 1; j < blocks.length; j++) {
        const a = blocks[i], b = blocks[j];
        if (a.contains(b) || b.contains(a)) continue;
        const ra = a.getBoundingClientRect(), rb = b.getBoundingClientRect();
        const ix = Math.min(ra.right, rb.right) - Math.max(ra.left, rb.left);
        const iy = Math.min(ra.bottom, rb.bottom) - Math.max(ra.top, rb.top);
        if (ix > 8 && iy > 8) add('warning', 'overlap', `Überlappt mit ${describe(b)}.`, a);
      }
    }
    // 7. Leere Folie
    if (!s.textContent.replace(/\s+/g, '') && !s.querySelector('img, svg')) add('warning', 'empty', 'Folie ist leer.', null);

    // Füllgrad: wie weit reicht der Inhalt nach unten (für „zu voll“ / „viel Luft“)
    let bottom = box.t;
    for (const el of blocks) bottom = Math.max(bottom, el.getBoundingClientRect().bottom);
    const fill = Math.round(100 * (bottom - box.t) / Math.max(1, box.b - box.t));
    for (const [code, n] of Object.entries(counts)) {
      if (n > MAX_PER_CODE) issues.push({ level: 'info', code, message: `… und ${n - MAX_PER_CODE} weitere Stellen (${code}).`, element: null });
    }
    out[key] = { issues, fill };
  }
  return JSON.stringify(out);
})()
