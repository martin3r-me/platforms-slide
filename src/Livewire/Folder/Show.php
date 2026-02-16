<?php

namespace Platform\Slides\Livewire\Folder;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Slides\Models\SlidesFolder;
use Platform\Slides\Models\SlidesFolderUser;
use Platform\Slides\Models\SlidesPresentation;
use Platform\Slides\Enums\FolderRole;
use Livewire\Attributes\On;

class Show extends Component
{
    public SlidesFolder $folder;
    public $selectedUserId = null;
    public $selectedRole = 'viewer';

    public function mount(SlidesFolder $slidesFolder)
    {
        $this->folder = $slidesFolder;
        $this->authorize('view', $this->folder);
    }

    #[On('updateFolder')]
    public function updateFolder()
    {
        $this->folder->refresh();
    }

    public function createSubFolder()
    {
        $this->authorize('update', $this->folder);

        $user = Auth::user();
        $team = $user->currentTeam;

        if (!$team) {
            session()->flash('error', 'Kein Team ausgewählt.');
            return;
        }

        $subFolder = SlidesFolder::create([
            'name' => 'Neuer Unterordner',
            'user_id' => $user->id,
            'team_id' => $team->id,
            'parent_id' => $this->folder->id,
        ]);

        $subFolder->folderUsers()->create([
            'user_id' => $user->id,
            'role' => FolderRole::OWNER->value,
        ]);

        $this->folder->refresh();
        $this->dispatch('updateSidebar');

        return $this->redirect(route('slides.folders.show', $subFolder), navigate: true);
    }

    public function createPresentation()
    {
        $this->authorize('update', $this->folder);

        $user = Auth::user();
        $team = $user->currentTeam;

        if (!$team) {
            session()->flash('error', 'Kein Team ausgewählt.');
            return;
        }

        $presentation = SlidesPresentation::create([
            'name' => 'Neue Präsentation',
            'user_id' => $user->id,
            'team_id' => $team->id,
            'folder_id' => $this->folder->id,
        ]);

        // Create first title slide
        $layouts = \Platform\Slides\Models\SlidesSlideTemplate::systemLayouts();
        $titleLayout = collect($layouts)->firstWhere('layout_key', 'title-center');

        $presentation->slides()->create([
            'sort_order' => 0,
            'layout_key' => 'title-center',
            'content' => $titleLayout['content'] ?? ['version' => 1, 'mode' => 'layout', 'elements' => []],
            'background' => ['type' => 'color', 'value' => '#ffffff'],
        ]);

        $this->folder->refresh();
        return $this->redirect(route('slides.presentations.show', $presentation), navigate: true);
    }

    public function deleteFolder()
    {
        $this->authorize('delete', $this->folder);

        if ($this->folder->children()->count() > 0 || $this->folder->presentations()->count() > 0) {
            session()->flash('error', 'Der Ordner kann nicht gelöscht werden, da er noch Unterordner oder Präsentationen enthält.');
            return;
        }

        $parentId = $this->folder->parent_id;
        $this->folder->delete();

        $this->dispatch('updateSidebar');

        if ($parentId) {
            return $this->redirect(route('slides.folders.show', $parentId), navigate: true);
        }

        return $this->redirect(route('slides.dashboard'), navigate: true);
    }

    public function updateFolderName($name = null)
    {
        $this->authorize('update', $this->folder);

        if ($name === null) {
            $name = $this->folder->name;
        }

        $name = trim($name);
        if (empty($name)) {
            session()->flash('error', 'Der Ordner-Name darf nicht leer sein.');
            $this->folder->refresh();
            return;
        }

        $this->folder->update(['name' => $name]);
        $this->folder->refresh();
        session()->flash('success', 'Ordner wurde umbenannt.');
    }

    public function addFolderUser($userId = null, $role = null)
    {
        $this->authorize('invite', $this->folder);

        if ($userId === null) {
            $userId = $this->selectedUserId;
        }
        if ($role === null) {
            $role = $this->selectedRole;
        }

        if (!$userId) {
            session()->flash('error', 'Bitte wählen Sie einen User aus.');
            return;
        }

        $user = Auth::user();
        $team = $user->currentTeam;

        if (!$team) {
            session()->flash('error', 'Kein Team ausgewählt.');
            return;
        }

        $targetUser = \Platform\Core\Models\User::find($userId);
        if (!$targetUser || !$team->users()->where('users.id', $userId)->exists()) {
            session()->flash('error', 'Der ausgewählte User ist kein Mitglied des Teams.');
            return;
        }

        $allowedRoles = [FolderRole::ADMIN->value, FolderRole::MEMBER->value, FolderRole::VIEWER->value];
        if (!in_array($role, $allowedRoles, true)) {
            session()->flash('error', 'Ungültige Rolle.');
            return;
        }

        $existing = SlidesFolderUser::where('folder_id', $this->folder->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            $existing->update(['role' => $role]);
            session()->flash('success', 'Rolle wurde aktualisiert.');
        } else {
            SlidesFolderUser::create([
                'folder_id' => $this->folder->id,
                'user_id' => $userId,
                'role' => $role,
            ]);
            session()->flash('success', 'User wurde zum Ordner hinzugefügt.');
        }

        $this->selectedUserId = null;
        $this->selectedRole = 'viewer';
        $this->folder->refresh();
    }

    public function removeFolderUser($userId)
    {
        $this->authorize('removeMember', $this->folder);

        $folderUser = SlidesFolderUser::where('folder_id', $this->folder->id)
            ->where('user_id', $userId)
            ->first();

        if (!$folderUser) {
            session()->flash('error', 'User nicht gefunden.');
            return;
        }

        if ($folderUser->role === FolderRole::OWNER->value) {
            session()->flash('error', 'Der Owner kann nicht entfernt werden.');
            return;
        }

        $folderUser->delete();
        $this->folder->refresh();
        session()->flash('success', 'User wurde aus dem Ordner entfernt.');
    }

    public function changeFolderUserRole($userId, $newRole)
    {
        $this->authorize('changeRole', $this->folder);

        $folderUser = SlidesFolderUser::where('folder_id', $this->folder->id)
            ->where('user_id', $userId)
            ->first();

        if (!$folderUser) {
            session()->flash('error', 'User nicht gefunden.');
            return;
        }

        if ($folderUser->role === FolderRole::OWNER->value && $newRole !== FolderRole::OWNER->value) {
            session()->flash('error', 'Die Owner-Rolle kann nicht geändert werden.');
            return;
        }

        $folderUser->update(['role' => $newRole]);
        $this->folder->refresh();
        session()->flash('success', 'Rolle wurde geändert.');
    }

    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => get_class($this->folder),
            'modelId' => $this->folder->id,
            'subject' => $this->folder->name,
            'description' => $this->folder->description ?? '',
            'url' => route('slides.folders.show', $this->folder),
            'source' => 'slides.folder.view',
            'recipients' => [],
            'capabilities' => [
                'manage_channels' => true,
                'threads' => false,
            ],
            'meta' => [
                'created_at' => $this->folder->created_at,
            ],
        ]);
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $subFolders = $this->folder->children;
        $presentations = $this->folder->presentations;

        $folderUsers = $this->folder->folderUsers()->with('user')->get();

        if ($this->folder->user_id && !$folderUsers->contains('user_id', $this->folder->user_id)) {
            $ownerUser = \Platform\Core\Models\User::find($this->folder->user_id);
            if ($ownerUser) {
                $ownerFolderUser = new SlidesFolderUser([
                    'folder_id' => $this->folder->id,
                    'user_id' => $ownerUser->id,
                    'role' => FolderRole::OWNER->value,
                ]);
                $ownerFolderUser->setRelation('user', $ownerUser);
                $folderUsers->prepend($ownerFolderUser);
            }
        }

        $teamUsers = $team ? $team->users()->orderBy('name')->get() : collect();

        return view('slides::livewire.folder.show', [
            'user' => $user,
            'subFolders' => $subFolders,
            'presentations' => $presentations,
            'folderUsers' => $folderUsers,
            'teamUsers' => $teamUsers,
        ])->layout('platform::layouts.app');
    }
}
