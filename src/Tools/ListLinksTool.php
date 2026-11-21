<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Tools\Concerns\HandlesDecks;
use Platform\Slides\Models\SlidesDeck;
use Platform\Slides\Models\SlidesDeckLink;

class ListLinksTool implements ToolContract, ToolMetadataContract
{
    use HandlesDecks;

    public function getName(): string
    {
        return 'slides.links.GET';
    }

    public function getDescription(): string
    {
        return 'GET /slides/links - Verknüpfungen von Decks mit Objekten. Entweder deck_id (woran hängt das Deck?) oder linkable_type + linkable_id (welche Decks hängen an diesem Objekt, z. B. management_book_meeting #7). Liefert nur sichtbare Decks.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deck_id' => ['type' => 'integer'],
                'linkable_type' => ['type' => 'string'],
                'linkable_id' => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (! empty($arguments['deck_id'])) {
                ['deck' => $deck, 'error' => $error] = $this->findDeck($arguments, $context);
                if ($error) {
                    return $error;
                }

                return ToolResult::success(['links' => $deck->links()->get()->map(fn ($l) => $this->linkToArray($l))->all()]);
            }
            if (empty($arguments['linkable_type']) || empty($arguments['linkable_id'])) {
                return ToolResult::error('deck_id oder linkable_type + linkable_id angeben.', 'VALIDATION_ERROR');
            }
            $links = SlidesDeckLink::query()->where('linkable_type', $arguments['linkable_type'])
                ->where('linkable_id', (int) $arguments['linkable_id'])->with('deck')->get()
                ->filter(fn ($l) => $l->deck && \Illuminate\Support\Facades\Gate::forUser($context->user)->allows('view', $l->deck));

            return ToolResult::success([
                'links' => $links->map(fn ($l) => $this->linkToArray($l) + ['deck' => $this->deckToArray($l->deck)])->values()->all(),
            ]);
        } catch (\Throwable $e) {
            return $this->failure($e, 'Laden der Verknüpfungen');
        }
    }

    public function getMetadata(): array
    {
        return $this->meta('GET', ['links', 'list']);
    }
}
