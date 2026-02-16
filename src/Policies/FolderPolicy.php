<?php

namespace Platform\Slides\Policies;

use Platform\Core\Policies\RolePolicy;
use Platform\Core\Models\User;
use Platform\Slides\Models\SlidesFolder;
use Platform\Slides\Enums\FolderRole;

class FolderPolicy extends RolePolicy
{
    public function view(User $user, $folder): bool
    {
        if ($this->isOwner($user, $folder)) {
            return true;
        }

        if (!$this->isInTeam($user, $folder)) {
            return false;
        }

        $userRole = $this->getUserFolderRole($user, $folder);
        return $userRole !== null;
    }

    public function update(User $user, $folder): bool
    {
        if ($this->isOwner($user, $folder)) {
            return true;
        }

        if (!$this->isInTeam($user, $folder)) {
            return false;
        }

        $userRole = $this->getUserFolderRole($user, $folder);
        return in_array($userRole, [
            FolderRole::OWNER->value,
            FolderRole::ADMIN->value,
            FolderRole::MEMBER->value,
        ], true);
    }

    public function delete(User $user, $folder): bool
    {
        if ($this->isOwner($user, $folder)) {
            return true;
        }

        if (!$this->isInTeam($user, $folder)) {
            return false;
        }

        $userRole = $this->getUserFolderRole($user, $folder);
        return $userRole === FolderRole::OWNER->value;
    }

    public function create(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function invite(User $user, $folder): bool
    {
        if ($this->isOwner($user, $folder)) {
            return true;
        }

        if (!$this->isInTeam($user, $folder)) {
            return false;
        }

        $userRole = $this->getUserFolderRole($user, $folder);
        return in_array($userRole, [
            FolderRole::OWNER->value,
            FolderRole::ADMIN->value,
        ], true);
    }

    public function removeMember(User $user, $folder): bool
    {
        if ($this->isOwner($user, $folder)) {
            return true;
        }

        if (!$this->isInTeam($user, $folder)) {
            return false;
        }

        $userRole = $this->getUserFolderRole($user, $folder);
        return in_array($userRole, [
            FolderRole::OWNER->value,
            FolderRole::ADMIN->value,
        ], true);
    }

    public function changeRole(User $user, $folder): bool
    {
        if ($this->isOwner($user, $folder)) {
            return true;
        }

        if (!$this->isInTeam($user, $folder)) {
            return false;
        }

        $userRole = $this->getUserFolderRole($user, $folder);
        return $userRole === FolderRole::OWNER->value;
    }

    protected function getUserFolderRole(User $user, $folder): ?string
    {
        if (!$folder instanceof SlidesFolder) {
            return null;
        }

        return $folder->getEffectiveRoleForUser($user->id);
    }

    protected function getUserRole(User $user, $model): ?string
    {
        return $this->getUserFolderRole($user, $model);
    }
}
