<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /**
     * Determine whether the user can view the team.
     */
    public function view(User $user, Team $team): bool
    {
        return $team->users()->whereKey($user->id)->exists();
    }

    /**
     * Determine whether the user can create teams.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can manage the team.
     */
    public function update(User $user, Team $team): bool
    {
        return $team->owner_id === $user->id;
    }

    /**
     * Determine whether the user can remove a member from the team.
     */
    public function removeMember(User $user, Team $team): bool
    {
        return $team->owner_id === $user->id;
    }

    /**
     * Determine whether the user can invite a member to the team.
     */
    public function inviteMember(User $user, Team $team): bool
    {
        return $team->owner_id === $user->id;
    }
}
