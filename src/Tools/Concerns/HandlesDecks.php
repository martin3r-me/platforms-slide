<?php

namespace Platform\Slides\Tools\Concerns;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Models\SlidesDeck;
use Platform\Slides\Models\SlidesDeckLink;
use Platform\Slides\Models\SlidesDeckSlide;
use Platform\Slides\Services\DeckService;

/** Gemeinsame Helfer der slides.*-Tools: Team-Auflösung, Deck laden + Rechte, Serialisierung, Metadaten. */
trait HandlesDecks
{
    /** @return array{team:mixed, error:?ToolResult} */
    protected function resolveTeam(array $arguments, ToolContext $context): array
    {
        if (! $context->user) {
            return ['team' => null, 'error' => ToolResult::error('Kein User im Kontext gefunden.', 'AUTH_ERROR')];
        }
        $teamId = $arguments['team_id'] ?? null;
        $teamId = $teamId ?: $context->team?->id;
        if (! $teamId) {
            return ['team' => null, 'error' => ToolResult::error('Kein Team angegeben und kein Team im Kontext. Nutze "core.teams.GET".', 'MISSING_TEAM')];
        }
        $team = $context->user->teams()->where('teams.id', (int) $teamId)->first();
        if (! $team) {
            return ['team' => null, 'error' => ToolResult::error("Kein Zugriff auf Team {$teamId}.", 'ACCESS_DENIED')];
        }

        return ['team' => $team, 'error' => null];
    }

    /** @return array{deck:?SlidesDeck, error:?ToolResult} */
    protected function findDeck(array $arguments, ToolContext $context, string $ability = 'view', string $field = 'deck_id'): array
    {
        $id = (int) ($arguments[$field] ?? 0);
        if ($id <= 0) {
            return ['deck' => null, 'error' => ToolResult::error("{$field} ist erforderlich.", 'VALIDATION_ERROR')];
        }
        $deck = SlidesDeck::query()->find($id);
        if (! $deck) {
            return ['deck' => null, 'error' => ToolResult::error("Deck #{$id} nicht gefunden.", 'NOT_FOUND')];
        }
        if (! Gate::forUser($context->user)->allows($ability, $deck)) {
            return ['deck' => null, 'error' => ToolResult::error("Keine Berechtigung ({$ability}) für Deck #{$id}.", 'ACCESS_DENIED')];
        }

        return ['deck' => $deck, 'error' => null];
    }

    protected function service(): DeckService
    {
        return app(DeckService::class);
    }

    protected function deckToArray(SlidesDeck $deck, bool $withTheme = false): array
    {
        $out = [
            'id' => $deck->id,
            'uuid' => $deck->uuid,
            'team_id' => $deck->team_id,
            'title' => $deck->title,
            'description' => $deck->description,
            'size' => $deck->size?->value,
            'status' => $deck->status?->value,
            'status_label' => $deck->status?->label(),
            'revision' => $deck->revision,
            'fonts' => $deck->fonts ?? [],
            'slide_count' => $deck->slides_count ?? $deck->slides()->count(),
            'owner_user_id' => $deck->owner_user_id,
            'url' => \Illuminate\Support\Facades\Route::has('slides.decks.show') ? route('slides.decks.show', $deck) : null,
            'present_url' => \Illuminate\Support\Facades\Route::has('slides.decks.present') ? route('slides.decks.present', $deck) : null,
            'updated_at' => $deck->updated_at?->toIso8601String(),
        ];
        if ($withTheme) {
            $out['theme_css'] = $deck->theme_css;
        }

        return $out;
    }

    protected function slideToArray(SlidesDeckSlide $slide, bool $withHtml = true): array
    {
        $out = [
            'key' => $slide->slide_key,
            'position' => $slide->position,
            'is_hidden' => $slide->is_hidden,
            'notes' => $slide->notes,
            'updated_at' => $slide->updated_at?->toIso8601String(),
        ];
        if ($withHtml) {
            $out['html'] = $slide->html;
        }

        return $out;
    }

    protected function linkToArray(SlidesDeckLink $link): array
    {
        return [
            'deck_id' => $link->deck_id,
            'linkable_type' => $link->linkable_type,
            'linkable_id' => $link->linkable_id,
            'role' => $link->role,
            'created_at' => $link->created_at?->toIso8601String(),
        ];
    }

    protected function versionToArray(\Platform\Slides\Models\SlidesDeckVersion $v): array
    {
        return [
            'id' => $v->id,
            'revision' => $v->revision,
            'kind' => $v->kind,
            'kind_label' => $v->kindLabel(),
            'label' => $v->label,
            'slide_count' => $v->slide_count,
            'created_by' => $v->created_by,
            'created_by_name' => $v->relationLoaded('creator') ? $v->creator?->name : null,
            'created_at' => $v->created_at?->toIso8601String(),
        ];
    }

    protected function assetToArray(\Platform\Slides\Models\SlidesAsset $a): array
    {
        return [
            'id' => $a->id,
            'uuid' => $a->uuid,
            'deck_id' => $a->deck_id,
            'name' => $a->original_name,
            'mime_type' => $a->mime_type,
            'size_kb' => (int) ceil($a->size / 1024),
            'width' => $a->width,
            'height' => $a->height,
            'alt' => $a->alt,
            'url' => $a->url(),
            'img' => $a->imgTag(),
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }

    protected function failure(\Throwable $e, string $what): ToolResult
    {
        if ($e instanceof \InvalidArgumentException) {
            return ToolResult::error($e->getMessage(), 'VALIDATION_ERROR');
        }

        return ToolResult::error("Fehler beim {$what}: " . $e->getMessage(), 'EXECUTION_ERROR');
    }

    protected function meta(string $verb, array $tags): array
    {
        return [
            'category' => $verb === 'GET' ? 'query' : 'action',
            'tags' => array_merge(['slides'], $tags),
            'read_only' => $verb === 'GET',
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => $verb === 'GET' ? 'safe' : ($verb === 'DELETE' ? 'destructive' : 'write'),
            'idempotent' => in_array($verb, ['GET', 'PUT'], true),
        ];
    }
}
