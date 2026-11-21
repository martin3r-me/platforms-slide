<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Models\SlidesDeckVersion;
use Platform\Slides\Services\VersionService;
use Platform\Slides\Tools\Concerns\HandlesDecks;

class RestoreVersionTool implements ToolContract, ToolMetadataContract
{
    use HandlesDecks;

    public function getName(): string
    {
        return 'slides.versions.restore.POST';
    }

    public function getDescription(): string
    {
        return 'POST /slides/decks/{id}/versions/{version}/restore - Setzt Folien, Reihenfolge, Notizen, Titel und Theme auf eine Version zurück. Der aktuelle Stand wird vorher als Version gesichert (backup_version_id), die Wiederherstellung ist also umkehrbar. Offene Ansichten aktualisieren sich live. Pflicht: deck_id, version_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deck_id' => ['type' => 'integer'],
                'version_id' => ['type' => 'integer'],
            ],
            'required' => ['deck_id', 'version_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['deck' => $deck, 'error' => $error] = $this->findDeck($arguments, $context, 'update');
            if ($error) {
                return $error;
            }
            $v = SlidesDeckVersion::query()->where('deck_id', $deck->id)->find((int) ($arguments['version_id'] ?? 0));
            if (! $v) {
                return ToolResult::error('Version nicht gefunden (version_id aus slides.versions.GET).', 'NOT_FOUND');
            }
            $backup = app(VersionService::class)->restore($deck, $v, $context->user);
            $deck->refresh();

            return ToolResult::success([
                'deck' => $this->deckToArray($deck),
                'restored_version_id' => $v->id,
                'backup_version_id' => $backup?->id,
                'message' => "Deck #{$deck->id} auf Version #{$v->id} zurückgesetzt." . ($backup ? " Vorheriger Stand liegt in Version #{$backup->id}." : ''),
            ]);
        } catch (\Throwable $e) {
            return $this->failure($e, 'Wiederherstellen');
        }
    }

    public function getMetadata(): array
    {
        return $this->meta('POST', ['versions', 'restore']);
    }
}
