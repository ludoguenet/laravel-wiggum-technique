<?php

namespace App\Http\Controllers;

use App\Enums\TeamInvitationStatus;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TeamController extends Controller
{
    /**
     * Display the teams belonging to the authenticated user.
     */
    public function index(Request $request): View
    {
        $teams = $request->user()->teams()->with('owner')->withCount('users')->get();

        return view('teams.index', [
            'teams' => $teams,
        ]);
    }

    /**
     * Show the form for creating a new team.
     */
    public function create(): View
    {
        return view('teams.create');
    }

    /**
     * Store a newly created team.
     */
    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $team = DB::transaction(function () use ($request) {
            $team = Team::create([
                'name' => $request->validated('name'),
                'owner_id' => $request->user()->id,
            ]);

            $team->users()->attach($request->user());

            return $team;
        });

        return redirect()->route('teams.show', $team);
    }

    /**
     * Display the given team.
     */
    public function show(Team $team): View
    {
        Gate::authorize('view', $team);

        $team->load(['owner', 'users']);

        $canInviteMembers = Gate::allows('inviteMember', $team);
        $canUpdate = Gate::allows('update', $team);

        return view('teams.show', [
            'team' => $team,
            'canRemoveMembers' => Gate::allows('removeMember', $team),
            'canInviteMembers' => $canInviteMembers,
            'canUpdate' => $canUpdate,
            'canLeave' => Gate::allows('leave', $team) && ! $canUpdate,
            'invitableUsers' => $canInviteMembers
                ? User::whereNotIn('id', $team->users->pluck('id'))->orderBy('name')->get()
                : collect(),
            'pendingInvitations' => $canUpdate
                ? $team->invitations()->where('status', TeamInvitationStatus::Pending)->with('invitee')->get()
                : collect(),
        ]);
    }

    /**
     * Update the given team's name.
     */
    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $team->update([
            'name' => $request->validated('name'),
        ]);

        return redirect()->route('teams.show', $team);
    }
}
