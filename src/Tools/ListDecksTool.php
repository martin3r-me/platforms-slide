<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Slides\Models\SlidesPresentation;
use Illuminate\Support\Facades\Gate;

/**
 * Tool zum Auflisten von Decks (Präsentationen) im Slides-Modul.
 */
class ListDecksTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'slides.decks.GET';
    }

    public function getDescription(): string
    {
        return 'GET /decks?team_id={id}&search=...&folder_id={id} - Listet Decks (Präsentationen) auf. REST-Parameter: team_id (optional, integer) - Filter nach Team. search (optional, string) - Suchbegriff für Name. folder_id (optional, integer) - Filter nach Ordner. limit/offset (optional) - Pagination.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Wenn nicht angegeben, wird das aktuelle Team verwendet.',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Optional: Suchbegriff für Deck-Namen.',
                ],
                'folder_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Filtert nach Ordner-ID. Nutze null oder weglassen für alle.',
                ],
                'is_published' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Filtert nach Veröffentlichungsstatus.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Optional: Max. Anzahl Ergebnisse (Standard: 50).',
                ],
                'offset' => [
                    'type' => 'integer',
                    'description' => 'Optional: Offset für Pagination (Standard: 0).',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $teamId = $arguments['team_id'] ?? null;
            if (empty($teamId) || $teamId === 0) {
                $teamId = $context->team?->id;
            }

            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden. Nutze "core.teams.GET" um Teams zu sehen.');
            }

            $query = SlidesPresentation::query()
                ->where('team_id', $teamId)
                ->with(['user', 'folder']);

            if (!empty($arguments['search'])) {
                $query->where('name', 'like', '%' . $arguments['search'] . '%');
            }

            if (isset($arguments['folder_id'])) {
                $query->where('folder_id', $arguments['folder_id'] ?: null);
            }

            if (isset($arguments['is_published'])) {
                $query->where('is_published', $arguments['is_published']);
            }

            $query->orderByDesc('updated_at');

            $limit = min($arguments['limit'] ?? 50, 200);
            $offset = $arguments['offset'] ?? 0;
            $query->limit($limit)->offset($offset);

            $decks = $query->get()->filter(function ($deck) use ($context) {
                try {
                    return Gate::forUser($context->user)->allows('view', $deck);
                } catch (\Throwable $e) {
                    return false;
                }
            })->values();

            $decksList = $decks->map(function ($deck) {
                return [
                    'id' => $deck->id,
                    'uuid' => $deck->uuid,
                    'name' => $deck->name,
                    'description' => $deck->description,
                    'folder_id' => $deck->folder_id,
                    'folder_name' => $deck->folder?->name,
                    'slide_count' => $deck->slides()->count(),
                    'slide_width' => $deck->slide_width,
                    'slide_height' => $deck->slide_height,
                    'is_published' => $deck->is_published,
                    'owner_user_id' => $deck->user_id,
                    'owner_name' => $deck->user?->name ?? 'Unbekannt',
                    'team_id' => $deck->team_id,
                    'created_at' => $deck->created_at->toIso8601String(),
                    'updated_at' => $deck->updated_at->toIso8601String(),
                ];
            })->toArray();

            return ToolResult::success([
                'decks' => $decksList,
                'count' => count($decksList),
                'team_id' => $teamId,
                'message' => count($decksList) > 0
                    ? count($decksList) . ' Deck(s) gefunden.'
                    : 'Keine Decks gefunden.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Decks: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['slides', 'deck', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
