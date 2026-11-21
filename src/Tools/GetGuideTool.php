<?php

namespace Platform\Slides\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Tools\Concerns\HandlesDecks;
use Platform\Slides\Support\Guide;

class GetGuideTool implements ToolContract, ToolMetadataContract
{
    use HandlesDecks;

    public function getName(): string
    {
        return 'slides.guide.GET';
    }

    public function getDescription(): string
    {
        return 'GET /slides/guide - Format- und Designregeln für Präsentationen (HTML-Folien): Ablauf, Folienformat, erlaubte Elemente, Designregeln, Theme-Klassen und Beispiele. VOR dem ersten Schreiben von Folien lesen.';
    }

    public function getSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return ToolResult::success(Guide::forAgents());
    }

    public function getMetadata(): array
    {
        return $this->meta('GET', ['guide', 'format', 'design']);
    }
}
