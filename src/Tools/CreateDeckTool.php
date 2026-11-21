<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Tools\Concerns\HandlesDecks;
use Illuminate\Support\Facades\Gate;
use Platform\Slides\Models\SlidesDeck;

class CreateDeckTool implements ToolContract, ToolMetadataContract
{
    use HandlesDecks;

    public function getName(): string
    {
        return 'slides.decks.POST';
    }

    public function getDescription(): string
    {
        return 'POST /slides/decks - Legt eine Präsentation an. Pflicht: title. Optional: description, size (16:9|4:3), status, theme_css (eigenes Theme; leer = Standard-Theme „BHG“), fonts (Google-Fonts-Familien, max. 4), linkable_type + linkable_id (+ role) um das Deck gleich an ein Objekt zu hängen. Danach Folien mit slides.slides.PUT schreiben; Regeln: slides.guide.GET.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'size' => ['type' => 'string', 'enum' => \Platform\Slides\Enums\DeckSize::values()],
                'status' => ['type' => 'string', 'enum' => \Platform\Slides\Enums\DeckStatus::values()],
                'theme_css' => ['type' => 'string', 'description' => 'Optional: gemeinsames CSS aller Folien (ohne @import).'],
                'fonts' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional: Google-Fonts-Familien, z. B. ["Inter","Source Serif 4"].'],
                'linkable_type' => ['type' => 'string', 'description' => 'Optional: Morph-Alias, z. B. management_book_meeting.'],
                'linkable_id' => ['type' => 'integer'],
                'role' => ['type' => 'string', 'description' => 'Optional: Rolle der Verknüpfung, z. B. presentation, pre-read.'],
                'team_id' => ['type' => 'integer'],
            ],
            'required' => ['title'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            if (! Gate::forUser($context->user)->allows('create', SlidesDeck::class)) {
                return ToolResult::error('Keine Berechtigung, Decks anzulegen.', 'ACCESS_DENIED');
            }
            ['deck' => $deck, 'removed' => $removed] = $this->service()->createDeck($context->user, $team->id, $arguments);
            $link = null;
            if (! empty($arguments['linkable_type']) && ! empty($arguments['linkable_id'])) {
                $link = $this->linkToArray($this->service()->link($deck, (string) $arguments['linkable_type'], (int) $arguments['linkable_id'], $arguments['role'] ?? 'presentation', $context->user));
            }

            return ToolResult::success([
                'deck' => $this->deckToArray($deck),
                'link' => $link,
                'removed' => $removed,
                'message' => "Deck '{$deck->title}' (#{$deck->id}) angelegt. Nächster Schritt: Folien mit slides.slides.PUT schreiben (Regeln: slides.guide.GET).",
            ]);
        } catch (\Throwable $e) {
            return $this->failure($e, 'Anlegen');
        }
    }

    public function getMetadata(): array
    {
        return $this->meta('POST', ['decks', 'create']);
    }
}
