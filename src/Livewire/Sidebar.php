<?php

namespace Platform\Slides\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Slides\Models\SlidesFolder;
use Platform\Slides\Models\SlidesPresentation;
use Livewire\Attributes\On;

class Sidebar extends Component
{
    public array $expandedFolders = [];

    public function mount()
    {
        $this->initializeExpandedFolders();
    }

    protected function initializeExpandedFolders()
    {
        $user = auth()->user();
        $teamId = $user?->currentTeam->id ?? null;

        if (!$user || !$teamId) {
            return;
        }

        $allFolders = SlidesFolder::where('team_id', $teamId)
            ->get()
            ->filter(fn($folder) => $user->can('view', $folder));

        $this->expandedFolders = $allFolders->pluck('id')->toArray();
    }

    #[On('updateSidebar')]
    public function updateSidebar()
    {
        // Re-render
    }

    public function createFolder()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        if (!$team) {
            return;
        }

        $this->authorize('create', SlidesFolder::class);

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

    public function toggleFolder($folderId)
    {
        if (in_array($folderId, $this->expandedFolders)) {
            $this->expandedFolders = array_values(array_filter($this->expandedFolders, fn($id) => $id !== $folderId));
        } else {
            $this->expandedFolders[] = $folderId;
        }
    }

    public function isFolderExpanded($folderId): bool
    {
        return in_array($folderId, $this->expandedFolders);
    }

    public function render()
    {
        $user = auth()->user();
        $teamId = $user?->currentTeam->id ?? null;

        if (!$user || !$teamId) {
            return view('slides::livewire.sidebar', [
                'rootFolders' => collect(),
                'recentPresentations' => collect(),
            ]);
        }

        $rootFolders = SlidesFolder::where('team_id', $teamId)
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('name')
            ->get()
            ->filter(fn($folder) => $user->can('view', $folder));

        $recentPresentations = SlidesPresentation::where('team_id', $teamId)
            ->whereNull('folder_id')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->filter(fn($p) => $user->can('view', $p));

        return view('slides::livewire.sidebar', [
            'rootFolders' => $rootFolders,
            'recentPresentations' => $recentPresentations,
        ]);
    }
}
