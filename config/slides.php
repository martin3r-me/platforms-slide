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
            'dynamic' => [
                'model'     => \Platform\Slides\Models\SlidesFolder::class,
                'team_based' => true,
                'order_by'  => 'name',
                'route'     => 'slides.folders.show',
                'icon'      => 'heroicon-o-folder',
                'label_key' => 'name',
            ],
        ],
    ],
];
