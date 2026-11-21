<?php

namespace Platform\Slides\Tools;

use Illuminate\Support\Facades\Storage;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Slides\Services\AssetService;
use Platform\Slides\Tools\Concerns\HandlesDecks;

class CreateAssetTool implements ToolContract, ToolMetadataContract
{
    use HandlesDecks;

    public function getName(): string
    {
        return 'slides.assets.POST';
    }

    public function getDescription(): string
    {
        return 'POST /slides/assets - Legt ein Bild für Folien an (PNG, JPEG, WebP, GIF, SVG; max. 10 MB). Quelle: context_file_id (eine in der Plattform hochgeladene Datei, z. B. aus core.context-files) ODER data (Base64, optional als data:-URI) ODER svg (SVG-Quelltext). Optional: deck_id (dem Deck zuordnen, sonst team-weit), team_id, name, alt. Antwort: url und <img>-Snippet für slides.slides.PUT.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deck_id' => ['type' => 'integer'],
                'team_id' => ['type' => 'integer'],
                'context_file_id' => ['type' => 'integer'],
                'data' => ['type' => 'string', 'description' => 'Base64 oder data:image/…;base64,…'],
                'svg' => ['type' => 'string', 'description' => 'SVG-Quelltext (<svg …>…</svg>)'],
                'name' => ['type' => 'string'],
                'alt' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $deck = null;
            if (! empty($arguments['deck_id'])) {
                ['deck' => $deck, 'error' => $error] = $this->findDeck($arguments, $context, 'update');
                if ($error) {
                    return $error;
                }
                $teamId = (int) $deck->team_id;
            } else {
                ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
                if ($error) {
                    return $error;
                }
                $teamId = (int) $team->id;
            }

            $name = $arguments['name'] ?? null;
            if (! empty($arguments['context_file_id'])) {
                $cf = \Platform\Core\Models\ContextFile::query()->find((int) $arguments['context_file_id']);
                if (! $cf || (int) $cf->team_id !== $teamId) {
                    return ToolResult::error('Datei nicht gefunden oder aus einem anderen Team.', 'NOT_FOUND');
                }
                $contents = (string) Storage::disk($cf->disk)->get($cf->path);
                $name ??= $cf->original_name;
            } elseif (! empty($arguments['data'])) {
                $data = preg_replace('#^data:[^,]*;base64,#i', '', trim((string) $arguments['data']));
                $contents = base64_decode(preg_replace('/\s+/', '', $data), true);
                if ($contents === false) {
                    return ToolResult::error('data ist kein gültiges Base64.', 'VALIDATION_ERROR');
                }
            } elseif (! empty($arguments['svg'])) {
                $contents = (string) $arguments['svg'];
                $name ??= 'grafik.svg';
            } else {
                return ToolResult::error('Quelle fehlt: context_file_id, data oder svg.', 'VALIDATION_ERROR');
            }

            $asset = app(AssetService::class)->store($teamId, $deck, $contents, $name, $context->user, $arguments['alt'] ?? null);

            return ToolResult::success(['asset' => $this->assetToArray($asset), 'message' => 'Bild gespeichert. In Folien einsetzen mit dem img-Snippet; Breite/Höhe bei Bedarf per CSS anpassen.']);
        } catch (\Throwable $e) {
            return $this->failure($e, 'Speichern des Bildes');
        }
    }

    public function getMetadata(): array
    {
        return $this->meta('POST', ['assets', 'images', 'upload']);
    }
}
