<?php

namespace Platform\Slides\Policies;

use Platform\Core\Policies\BasePolicy;
use Platform\Core\Models\User;
use Platform\Slides\Models\SlidesPresentation;
use Platform\Slides\Enums\FolderRole;

class PresentationPolicy extends BasePolicy
{
    public function view(User $user, $presentation): bool
    {
        if ($this->isOwner($user, $presentation)) {
            return true;
        }

        if (!$this->isInTeam($user, $presentation)) {
            return false;
        }

        // Check folder permissions if presentation is in a folder
        if ($presentation->folder_id && $presentation->folder) {
            $role = $presentation->folder->getEffectiveRoleForUser($user->id);
            return $role !== null;
        }

        return true;
    }

    public function update(User $user, $presentation): bool
    {
        if ($this->isOwner($user, $presentation)) {
            return true;
        }

        if (!$this->isInTeam($user, $presentation)) {
            return false;
        }

        if ($presentation->folder_id && $presentation->folder) {
            $role = $presentation->folder->getEffectiveRoleForUser($user->id);
            return in_array($role, [
                FolderRole::OWNER->value,
                FolderRole::ADMIN->value,
                FolderRole::MEMBER->value,
            ], true);
        }

        return true;
    }

    public function delete(User $user, $presentation): bool
    {
        if ($this->isOwner($user, $presentation)) {
            return true;
        }

        if (!$this->isInTeam($user, $presentation)) {
            return false;
        }

        if ($presentation->folder_id && $presentation->folder) {
            $role = $presentation->folder->getEffectiveRoleForUser($user->id);
            return in_array($role, [
                FolderRole::OWNER->value,
                FolderRole::ADMIN->value,
            ], true);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    protected function getUserRole(User $user, $model): ?string
    {
        if ($model->folder_id && $model->folder) {
            return $model->folder->getEffectiveRoleForUser($user->id);
        }

        if ($this->isOwner($user, $model)) {
            return FolderRole::OWNER->value;
        }

        return null;
    }
}
