<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Slides\Models\SlidesPresentation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Tool zum Löschen eines Decks (Präsentation).
 */
class DeleteDeckTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'slides.decks.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /decks/{id} - Löscht ein Deck und alle zugehörigen Slides. REST-Parameter: deck_id (required, integer) - Deck-ID. confirm (optional, boolean) - Bestätigung bei vielen Slides.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deck_id' => [
                    'type' => 'integer',
                    'description' => 'ID des zu löschenden Decks (ERFORDERLICH).',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Bestätigung, dass das Deck wirklich gelöscht werden soll.',
                ],
            ],
            'required' => ['deck_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            if (empty($arguments['deck_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'deck_id ist erforderlich.');
            }

            $deck = SlidesPresentation::find($arguments['deck_id']);
            if (!$deck) {
                return ToolResult::error('DECK_NOT_FOUND', 'Das Deck wurde nicht gefunden.');
            }

            try {
                Gate::forUser($context->user)->authorize('delete', $deck);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst dieses Deck nicht löschen.');
            }

            $slidesCount = $deck->slides()->count();
            $mediaCount = $deck->media()->count();

            if ($slidesCount > 10 && !($arguments['confirm'] ?? false)) {
                return ToolResult::error('CONFIRMATION_REQUIRED', "Das Deck hat {$slidesCount} Slide(s) und {$mediaCount} Medien. Bitte bestätige die Löschung mit 'confirm: true'.");
            }

            $deckName = $deck->name;
            $deckId = $deck->id;

            $deck->delete();

            return ToolResult::success([
                'deck_id' => $deckId,
                'deck_name' => $deckName,
                'deleted_slides_count' => $slidesCount,
                'deleted_media_count' => $mediaCount,
                'message' => "Deck '{$deckName}' und alle {$slidesCount} Slides erfolgreich gelöscht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Decks: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['slides', 'deck', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => false,
            'side_effects' => ['deletes'],
        ];
    }
}
