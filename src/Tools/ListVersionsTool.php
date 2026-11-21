<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Models\SlidesDeckVersion;
use Platform\Slides\Services\VersionService;
use Platform\Slides\Tools\Concerns\HandlesDecks;

class ListVersionsTool implements ToolContract, ToolMetadataContract
{
    use HandlesDecks;

    public function getName(): string
    {
        return 'slides.versions.GET';
    }

    public function getDescription(): string
    {
        return 'GET /slides/decks/{id}/versions - Versionen eines Decks, neueste zuerst, je mit Unterschied zum aktuellen Stand (added/removed/changed Folien). Versionen entstehen automatisch vor jeder Änderungsrunde, festgehalten über slides.versions.POST. Pflicht: deck_id. Optional: limit (Default 20), version_id (liefert den vollständigen Schnappschuss dieser Version inkl. html).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deck_id' => ['type' => 'integer'],
                'limit' => ['type' => 'integer'],
                'version_id' => ['type' => 'integer'],
            ],
            'required' => ['deck_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['deck' => $deck, 'error' => $error] = $this->findDeck($arguments, $context);
            if ($error) {
                return $error;
            }
            $svc = app(VersionService::class);
            if (! empty($arguments['version_id'])) {
                $v = SlidesDeckVersion::query()->where('deck_id', $deck->id)->find((int) $arguments['version_id']);
                if (! $v) {
                    return ToolResult::error("Version #{$arguments['version_id']} nicht gefunden.", 'NOT_FOUND');
                }

                return ToolResult::success(['version' => $this->versionToArray($v) + ['diff_to_current' => $svc->diffToCurrent($deck, $v), 'snapshot' => $v->snapshot]]);
            }
            $limit = max(1, min(100, (int) ($arguments['limit'] ?? 20)));
            $versions = SlidesDeckVersion::query()->where('deck_id', $deck->id)->with('creator:id,name')->orderByDesc('id')->limit($limit)->get();

            return ToolResult::success([
                'deck_id' => $deck->id,
                'current_revision' => $deck->revision,
                'versions' => $versions->map(fn ($v) => $this->versionToArray($v) + ['diff_to_current' => $svc->diffToCurrent($deck, $v)])->all(),
                'hint' => 'Zurücksetzen mit slides.versions.restore.POST {deck_id, version_id} – der aktuelle Stand wird vorher gesichert.',
            ]);
        } catch (\Throwable $e) {
            return $this->failure($e, 'Laden der Versionen');
        }
    }

    public function getMetadata(): array
    {
        return $this->meta('GET', ['versions', 'history']);
    }
}
