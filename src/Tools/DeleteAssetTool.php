<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Models\SlidesAsset;
use Platform\Slides\Services\AssetService;
use Platform\Slides\Tools\Concerns\HandlesDecks;

class DeleteAssetTool implements ToolContract, ToolMetadataContract
{
    use HandlesDecks;

    public function getName(): string
    {
        return 'slides.assets.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /slides/assets/{id} - Löscht ein Bild. Wird es noch in Folien verwendet, wird nicht gelöscht und die Folien werden genannt – mit force=true trotzdem löschen (das Bild fehlt dann dort). Pflicht: asset_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'asset_id' => ['type' => 'integer'],
                'force' => ['type' => 'boolean'],
            ],
            'required' => ['asset_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (! $context->user) {
                return ToolResult::error('Kein User im Kontext gefunden.', 'AUTH_ERROR');
            }
            $asset = SlidesAsset::query()->find((int) ($arguments['asset_id'] ?? 0));
            if (! $asset || ! $context->user->teams()->where('teams.id', $asset->team_id)->exists()) {
                return ToolResult::error('Bild nicht gefunden.', 'NOT_FOUND');
            }
            $svc = app(AssetService::class);
            $usages = $svc->usages($asset);
            if ($usages !== [] && empty($arguments['force'])) {
                return ToolResult::error('Bild wird noch verwendet: ' . collect($usages)->map(fn ($u) => "Deck #{$u['deck_id']} / {$u['key']}")->implode(', ') . '. Mit force=true trotzdem löschen.', 'IN_USE');
            }
            $svc->delete($asset);

            return ToolResult::success(['deleted' => $asset->id, 'was_used_in' => $usages, 'message' => 'Bild gelöscht.']);
        } catch (\Throwable $e) {
            return $this->failure($e, 'Löschen des Bildes');
        }
    }

    public function getMetadata(): array
    {
        return $this->meta('DELETE', ['assets', 'images']);
    }
}
