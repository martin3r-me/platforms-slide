<?php

return [
    'routing' => [
        'mode' => env('SLIDES_MODE', 'path'),
        'prefix' => 'slides',
    ],
    'guard' => 'web',

    'navigation' => [
        'route' => 'slides.dashboard',
        'icon'  => 'heroicon-o-presentation-chart-bar',
        'order' => 50,
    ],

    'sidebar' => [
        [
            'group' => 'Präsentationen',
            'items' => [
                [
                    'label' => 'Alle Decks',
                    'route' => 'slides.dashboard',
                    'icon'  => 'heroicon-o-presentation-chart-bar',
                ],
            ],
        ],
    ],

    /*
    | Live-Aktualisierung: Wie oft offene Ansichten die revision des Decks prüfen (Sekunden).
    */
    'live_poll_seconds' => env('SLIDES_LIVE_POLL_SECONDS', 3),

    /*
    | PDF: Standard ist die Druckansicht (Browser „Als PDF sichern“). Serverseitiger Export über spatie/browsershot
    | nur, wenn auf dem Server Chrome/Node verfügbar sind: SLIDES_PDF_SERVER=true.
    */
    'pdf' => [
        'server' => env('SLIDES_PDF_SERVER', false),
        'timeout' => env('SLIDES_PDF_TIMEOUT', 60),
    ],

    /*
    | Versionen: Vor einer Änderung wird der bisherige Stand gesichert, wenn eine neue Änderungsrunde beginnt
    | (letzte Änderung älter als session_minutes) oder der letzte Schnappschuss älter als auto_minutes ist.
    | keep_auto = wie viele automatische Versionen je Deck bleiben (festgehaltene bleiben immer).
    */
    'versions' => [
        'session_minutes' => env('SLIDES_VERSION_SESSION_MINUTES', 3),
        'auto_minutes' => env('SLIDES_VERSION_AUTO_MINUTES', 15),
        'keep_auto' => env('SLIDES_VERSION_KEEP_AUTO', 50),
    ],

    /*
    | Headless-Chrome (spatie/browsershot) für Layout-Prüfung, Vorschaubilder und serverseitiges PDF.
    | Pfade fallen auf platform.documents.* (BROWSERSHOT_CHROMIUM_PATH / BROWSERSHOT_NODE_PATH) zurück.
    */
    'browser' => [
        'enabled' => env('SLIDES_BROWSER_ENABLED', true),
        'chrome_path' => env('SLIDES_CHROME_PATH'),
        'node_path' => env('SLIDES_NODE_PATH'),
        'node_modules_path' => env('SLIDES_NODE_MODULES_PATH'),
        'no_sandbox' => env('SLIDES_BROWSER_NO_SANDBOX', false),
        'timeout' => env('SLIDES_BROWSER_TIMEOUT', 60),
    ],

    'preview' => [
        'disk' => env('SLIDES_PREVIEW_DISK', 'local'),
        'url_minutes' => env('SLIDES_PREVIEW_URL_MINUTES', 60),
        'max_screenshots' => 8,
        'max_inline_images' => 6, // direkt an die MCP-Antwort gehängt (Core-Limit 6)
    ],

    /*
    | Bilder für Folien (Upload in der Deck-Ansicht oder slides.assets.POST).
    */
    'assets' => [
        'disk' => env('SLIDES_ASSETS_DISK', 'local'),
        'max_kb' => env('SLIDES_ASSETS_MAX_KB', 10240),
    ],
];
