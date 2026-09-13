<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TeamMemberController extends Controller
{
    /**
     * Remove the given member from the team.
     */
    public function destroy(Team $team, User $user): RedirectResponse
    {
        Gate::authorize('removeMember', $team);

        if ($team->owner_id === $user->id) {
            throw ValidationException::withMessages([
                'member' => 'The team owner cannot be removed from the team.',
            ]);
        }

        $team->users()->detach($user);

        return redirect()->route('teams.show', $team);
    }

    /**
     * Remove the authenticated user from the given team.
     */
    public function leave(Team $team, Request $request): RedirectResponse
    {
        Gate::authorize('leave', $team);

        $user = $request->user();

        if ($team->owner_id === $user->id) {
            throw ValidationException::withMessages([
                'team' => 'The team owner cannot leave the team.',
            ]);
        }

        $team->users()->detach($user);

        return redirect()->route('teams.index');
    }
}
