<?php

namespace Platform\Slides\Organization;

use Illuminate\Database\Eloquent\Builder;
use Platform\Organization\Contracts\EntityLinkProvider;
use Platform\Organization\Contracts\HasMetricDefinitions;
use Platform\Slides\Enums\DeckStatus;
use Platform\Slides\Models\SlidesDeck;

/** Decks lassen sich an Knoten der Organisation hängen (DimensionLinks); Kennzahlen je Knoten. */
class SlidesEntityLinkProvider implements EntityLinkProvider, HasMetricDefinitions
{
    public function morphAliases(): array
    {
        return [SlidesDeck::MORPH_ALIAS];
    }

    public function linkTypeConfig(): array
    {
        return [
            SlidesDeck::MORPH_ALIAS => ['label' => 'Präsentationen', 'singular' => 'Präsentation', 'icon' => 'presentation-chart-bar', 'route' => 'slides.decks.show'],
        ];
    }

    public function applyEagerLoading(Builder $query, string $morphAlias, string $fqcn): void
    {
        $query->withCount('slides');
    }

    public function extractMetadata(string $morphAlias, mixed $model): array
    {
        return [
            'status' => $model->status?->value,
            'slide_count' => (int) ($model->slides_count ?? 0),
        ];
    }

    public function metadataDisplayRules(): array
    {
        return [
            SlidesDeck::MORPH_ALIAS => [
                ['field' => 'slide_count', 'format' => 'count', 'suffix' => 'Folien'],
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
        if ($morphAlias !== SlidesDeck::MORPH_ALIAS) {
            return [];
        }
        $ids = array_values(array_unique(array_merge([], ...array_values($linksByEntity))));
        if ($ids === []) {
            return [];
        }
        $decks = SlidesDeck::whereIn('id', $ids)->withCount('slides')->get(['id', 'status'])->keyBy('id');

        $result = [];
        foreach ($linksByEntity as $entityId => $deckIds) {
            $total = $ready = $slides = 0;
            foreach ($deckIds as $id) {
                $d = $decks[$id] ?? null;
                if (! $d) {
                    continue;
                }
                $total++;
                $ready += $d->status === DeckStatus::READY ? 1 : 0;
                $slides += (int) $d->slides_count;
            }
            $result[$entityId] = ['slides_decks_total' => $total, 'slides_decks_ready' => $ready, 'slides_slides_total' => $slides];
        }

        return $result;
    }

    public function metricDefinitions(): array
    {
        return [
            'slides_decks_total' => ['label' => 'Präsentationen (gesamt)', 'group' => 'slides', 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'org_capital', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'slides_decks_ready' => ['label' => 'Präsentationen (fertig)', 'group' => 'slides', 'direction' => 'up', 'unit' => 'count', 'pair' => 'slides_decks_total', 'dimension' => 'org_capital', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'slides_slides_total' => ['label' => 'Folien (gesamt)', 'group' => 'slides', 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
        ];
    }
}
