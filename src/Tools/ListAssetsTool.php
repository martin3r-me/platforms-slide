<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Models\SlidesAsset;
use Platform\Slides\Tools\Concerns\HandlesDecks;

class ListAssetsTool implements ToolContract, ToolMetadataContract
{
    use HandlesDecks;

    public function getName(): string
    {
        return 'slides.assets.GET';
    }

    public function getDescription(): string
    {
        return 'GET /slides/assets - Bilder für Folien (hochgeladen in der Deck-Ansicht oder per slides.assets.POST). Je Bild url, Maße und ein fertiges <img>-Snippet zum Einsetzen in Folien-HTML. Optional: deck_id (Bilder dieses Decks zuerst, plus team-weite), team_id, search (Dateiname/alt).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deck_id' => ['type' => 'integer'],
                'team_id' => ['type' => 'integer'],
                'search' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $deck = null;
            if (! empty($arguments['deck_id'])) {
                ['deck' => $deck, 'error' => $error] = $this->findDeck($arguments, $context);
                if ($error) {
                    return $error;
                }
                $teamId = $deck->team_id;
            } else {
                ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
                if ($error) {
                    return $error;
                }
                $teamId = $team->id;
            }
            $q = SlidesAsset::query()->where('team_id', $teamId);
            if ($deck) {
                $q->where(fn ($w) => $w->where('deck_id', $deck->id)->orWhereNull('deck_id'))->orderByRaw('deck_id is null');
            }
            if (! empty($arguments['search'])) {
                $term = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $arguments['search']) . '%';
                $q->where(fn ($w) => $w->where('original_name', 'like', $term)->orWhere('alt', 'like', $term));
            }
            $assets = $q->orderByDesc('id')->limit(100)->get();

            return ToolResult::success(['team_id' => $teamId, 'assets' => $assets->map(fn ($a) => $this->assetToArray($a))->all()]);
        } catch (\Throwable $e) {
            return $this->failure($e, 'Laden der Bilder');
        }
    }

    public function getMetadata(): array
    {
        return $this->meta('GET', ['assets', 'images']);
    }
}
