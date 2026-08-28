<?php

namespace App\Http\Controllers;

use App\Enums\TeamInvitationStatus;
use App\Http\Requests\StoreTeamInvitationRequest;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

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
}
