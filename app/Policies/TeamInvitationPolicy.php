<?php

namespace App\Policies;

use App\Models\TeamInvitation;
use App\Models\User;

class TeamInvitationPolicy
{
    /**
     * Determine whether the user can respond to (accept or decline) the invitation.
     */
    public function respond(User $user, TeamInvitation $invitation): bool
    {
        return $invitation->invitee_id === $user->id;
    }

    /**
     * Determine whether the user can cancel the invitation.
     */
    public function cancel(User $user, TeamInvitation $invitation): bool
    {
        return $invitation->team->owner_id === $user->id;
    }
}
