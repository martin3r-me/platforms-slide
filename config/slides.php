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
];
