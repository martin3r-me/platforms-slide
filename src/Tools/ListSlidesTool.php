<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Tools\Concerns\HandlesDecks;

class ListSlidesTool implements ToolContract, ToolMetadataContract
{
    use HandlesDecks;

    public function getName(): string
    {
        return 'slides.slides.GET';
    }

    public function getDescription(): string
    {
        return 'GET /slides/decks/{id}/slides - Folien eines Decks in Reihenfolge mit html und notes. Pflicht: deck_id. Optional: keys (nur diese Folien), include_html (Default true), include_theme (theme_css mitliefern).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deck_id' => ['type' => 'integer'],
                'keys' => ['type' => 'array', 'items' => ['type' => 'string']],
                'include_html' => ['type' => 'boolean'],
                'include_theme' => ['type' => 'boolean'],
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
            $query = $deck->slides();
            if (! empty($arguments['keys']) && is_array($arguments['keys'])) {
                $query->whereIn('slide_key', $arguments['keys']);
            }
            $withHtml = (bool) ($arguments['include_html'] ?? true);

            return ToolResult::success([
                'deck' => $this->deckToArray($deck, (bool) ($arguments['include_theme'] ?? false)),
                'slides' => $query->get()->map(fn ($s) => $this->slideToArray($s, $withHtml))->all(),
            ]);
        } catch (\Throwable $e) {
            return $this->failure($e, 'Laden der Folien');
        }
    }

    public function getMetadata(): array
    {
        return $this->meta('GET', ['slides', 'list']);
    }
}
