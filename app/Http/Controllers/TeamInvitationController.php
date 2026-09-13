<?php

namespace App\Http\Controllers;

use App\Enums\TeamInvitationStatus;
use App\Http\Requests\StoreTeamInvitationRequest;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TeamInvitationController extends Controller
{
    /**
     * Store a newly created team invitation.
     */
    public function store(StoreTeamInvitationRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('inviteMember', $team);

        TeamInvitation::create([
            'team_id' => $team->id,
            'inviter_id' => $request->user()->id,
            'invitee_id' => (int) $request->validated('user_id'),
            'status' => TeamInvitationStatus::Pending,
        ]);

        return redirect()->route('teams.show', $team);
    }

    /**
     * Cancel the given pending team invitation.
     */
    public function destroy(Team $team, TeamInvitation $invitation): RedirectResponse
    {
        Gate::authorize('cancel', $invitation);

        if ($invitation->status !== TeamInvitationStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'This invitation is no longer pending.',
            ]);
        }

        $invitation->delete();

        return redirect()->route('teams.show', $team);
    }
}
