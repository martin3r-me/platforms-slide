<?php

namespace Platform\Slides\Policies;

use Platform\Core\Models\User;
use Platform\Slides\Models\SlidesDeck;

/**
 * Team-basiert: Mitglieder des Teams, dem das Deck gehört, dürfen lesen und ändern; Owner und Ersteller immer.
 * Löschen: Owner, Ersteller oder Owner/Admin des Teams.
 */
class DeckPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, SlidesDeck $deck): bool
    {
        return $this->isOwnerOrCreator($user, $deck) || $this->teamRole($user, $deck) !== null;
    }

    public function update(User $user, SlidesDeck $deck): bool
    {
        return $this->view($user, $deck);
    }

    public function delete(User $user, SlidesDeck $deck): bool
    {
        return $this->isOwnerOrCreator($user, $deck) || in_array($this->teamRole($user, $deck), ['owner', 'admin'], true);
    }

    private function isOwnerOrCreator(User $user, SlidesDeck $deck): bool
    {
        return (int) $deck->owner_user_id === (int) $user->id || (int) $deck->created_by === (int) $user->id;
    }

    private function teamRole(User $user, SlidesDeck $deck): ?string
    {
        $team = $user->teams()->where('teams.id', $deck->team_id)->first();

        return $team ? (string) ($team->pivot->role ?? 'member') : null;
    }
}
