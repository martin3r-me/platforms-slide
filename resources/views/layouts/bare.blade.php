<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Präsentation' }}</title>
    <style>
        /* Bühne für Präsentation, Referentenansicht und Druck – bewusst neutral-dunkel, unabhängig vom App-Theme */
        :root {
            --stage-bg: #0e1013; --stage-panel: #171a1f; --stage-line: #2a2f37; --stage-text: #ecebe7;
            --stage-muted: #a2a8b1; --stage-faint: #6c727c; --stage-accent: #e8e3d6; --stage-live: #5fb37a; --stage-warn: #e0a64a;
        }
        html, body { margin: 0; padding: 0; height: 100%; background: var(--stage-bg); color: var(--stage-text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; -webkit-font-smoothing: antialiased; }
        button { font: inherit; }
        [x-cloak] { display: none !important; }
        .st-btn { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 12px; border-radius: 8px;
            background: transparent; border: 1px solid var(--stage-line); color: var(--stage-text); cursor: pointer; font-size: 13px;
            text-decoration: none; transition: background .15s, border-color .15s; }
        .st-btn:hover { background: rgba(255,255,255,.06); border-color: #3a414b; }
        .st-btn.icon { width: 34px; padding: 0; justify-content: center; }
        .st-btn svg { width: 16px; height: 16px; }
        .st-muted { color: var(--stage-muted); }
        .st-faint { color: var(--stage-faint); }
    </style>
</head>
<body>
    {{ $slot }}
</body>
</html>
