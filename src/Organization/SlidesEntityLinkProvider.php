<?php

namespace Platform\Slides\Organization;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Contracts\EntityLinkProvider;
use Platform\Organization\Contracts\HasMetricDefinitions;
use Platform\Slides\Models\SlidesPresentation;

class SlidesEntityLinkProvider implements EntityLinkProvider, HasMetricDefinitions
{
    public function morphAliases(): array
    {
        return ['slides_presentation'];
    }

    public function linkTypeConfig(): array
    {
        return [
            'slides_presentation' => ['label' => 'Präsentationen', 'singular' => 'Präsentation', 'icon' => 'presentation-chart-bar', 'route' => null],
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

    public function activityChildren(string $morphAlias, array $linkableIds): array
    {
        return [];
    }

    public function metrics(string $morphAlias, array $linksByEntity): array
    {
        if ($morphAlias !== 'slides_presentation') {
            return [];
        }

        $allIds = [];
        foreach ($linksByEntity as $ids) {
            $allIds = array_merge($allIds, $ids);
        }
        $allIds = array_values(array_unique($allIds));

        if (empty($allIds)) {
            return [];
        }

        $presentations = SlidesPresentation::whereIn('id', $allIds)
            ->withCount('slides')
            ->select('id', 'is_published')
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($linksByEntity as $entityId => $ids) {
            $total = 0;
            $published = 0;
            $draft = 0;
            $slidesSum = 0;

            foreach ($ids as $id) {
                $p = $presentations[$id] ?? null;
                if (! $p) {
                    continue;
                }
                $total++;
                if ($p->is_published) {
                    $published++;
                } else {
                    $draft++;
                }
                $slidesSum += (int) ($p->slides_count ?? 0);
            }

            $result[$entityId] = [
                'slides_presentations_total' => $total,
                'slides_presentations_published' => $published,
                'slides_presentations_draft' => $draft,
                'slides_slides_total' => $slidesSum,
            ];
        }

        return $result;
    }

    public function metricDefinitions(): array
    {
        return [
            'slides_presentations_total'     => ['label' => 'Präsentationen (gesamt)', 'group' => 'slides', 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'org_capital', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'slides_presentations_published' => ['label' => 'Präsentationen (veröffentlicht)', 'group' => 'slides', 'direction' => 'up', 'unit' => 'count', 'pair' => 'slides_presentations_total', 'dimension' => 'org_capital', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'slides_presentations_draft'     => ['label' => 'Präsentationen (Entwurf)', 'group' => 'slides', 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'slides_slides_total'            => ['label' => 'Folien (gesamt)', 'group' => 'slides', 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
        ];
    }
}
