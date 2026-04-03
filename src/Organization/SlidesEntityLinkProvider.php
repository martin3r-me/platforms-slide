<?php

namespace Platform\Slides\Organization;

use Illuminate\Database\Eloquent\Builder;
use Platform\Organization\Contracts\EntityLinkProvider;

class SlidesEntityLinkProvider implements EntityLinkProvider
{
    public function morphAliases(): array
    {
        return ['slides_presentation'];
    }

    public function linkTypeConfig(): array
    {
        return [
            'slides_presentation' => ['label' => 'Präsentationen', 'icon' => 'presentation-chart-bar', 'route' => null],
        ];
    }

    public function applyEagerLoading(Builder $query, string $morphAlias, string $fqcn): void
    {
        $query->withCount('slides');
    }

    public function extractMetadata(string $morphAlias, mixed $model): array
    {
        return [
            'is_published' => (bool) ($model->is_published ?? false),
            'slide_count' => (int) ($model->slides_count ?? 0),
        ];
    }

    public function metadataDisplayRules(): array
    {
        return [
            'slides_presentation' => [
                ['field' => 'slide_count', 'format' => 'count', 'suffix' => 'Folien'],
                ['field' => 'is_published', 'format' => 'boolean_published'],
            ],
        ];
    }

    public function timeTrackableCascades(): array
    {
        return [];
    }

    public function metrics(string $morphAlias, array $linksByEntity): array
    {
        return [];
    }
}
