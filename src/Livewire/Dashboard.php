<?php

namespace Platform\Slides\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Slides\Models\SlidesFolder;
use Platform\Slides\Models\SlidesPresentation;

class Dashboard extends Component
{
    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => null,
            'modelId' => null,
            'subject' => 'Präsentationen Dashboard',
            'description' => 'Übersicht aller Präsentationen und Ordner',
            'url' => route('slides.dashboard'),
            'source' => 'slides.dashboard',
            'recipients' => [],
            'meta' => [
                'view_type' => 'dashboard',
            ],
        ]);
    }

    public function createFolder()
    {
        $user = Auth::user();
        $this->authorize('create', SlidesFolder::class);

        $team = $user->currentTeam;
        if (!$team) {
            session()->flash('error', 'Kein Team ausgewählt.');
            return;
        }

        $folder = SlidesFolder::create([
            'name' => 'Neuer Ordner',
            'user_id' => $user->id,
            'team_id' => $team->id,
        ]);

        $folder->folderUsers()->create([
            'user_id' => $user->id,
            'role' => \Platform\Slides\Enums\FolderRole::OWNER->value,
        ]);

        $this->dispatch('updateSidebar');
        return $this->redirect(route('slides.folders.show', $folder), navigate: true);
    }

    public function createPresentation()
    {
        $user = Auth::user();
        $this->authorize('create', SlidesPresentation::class);

        $team = $user->currentTeam;
        if (!$team) {
            session()->flash('error', 'Kein Team ausgewählt.');
            return;
        }

        $presentation = SlidesPresentation::create([
            'name' => 'Neue Präsentation',
            'user_id' => $user->id,
            'team_id' => $team->id,
        ]);

        // Create first title slide
        $presentation->slides()->create([
            'sort_order' => 0,
            'layout_key' => 'title-center',
            'content' => $this->getTemplateContent('title-center'),
            'background' => ['type' => 'color', 'value' => '#ffffff'],
        ]);

        $this->dispatch('updateSidebar');
        return $this->redirect(route('slides.presentations.show', $presentation), navigate: true);
    }

    protected function getTemplateContent(string $layoutKey): array
    {
        $layouts = \Platform\Slides\Models\SlidesSlideTemplate::systemLayouts();
        foreach ($layouts as $layout) {
            if ($layout['layout_key'] === $layoutKey) {
                return $layout['content'];
            }
        }
        return ['version' => 1, 'mode' => 'layout', 'elements' => []];
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $folders = SlidesFolder::where('team_id', $team->id)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get()
            ->filter(fn($folder) => $user->can('view', $folder));

        $presentations = SlidesPresentation::where('team_id', $team->id)
            ->whereNull('folder_id')
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn($p) => $user->can('view', $p));

        $recentPresentations = SlidesPresentation::where('team_id', $team->id)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->filter(fn($p) => $user->can('view', $p))
            ->take(5);

        return view('slides::livewire.dashboard', [
            'folders' => $folders,
            'presentations' => $presentations,
            'recentPresentations' => $recentPresentations,
            'totalFolders' => $folders->count(),
            'totalPresentations' => SlidesPresentation::where('team_id', $team->id)->count(),
        ])->layout('platform::layouts.app');
    }
}
