<?php

namespace App\Http\Controllers;

use App\Enums\TeamInvitationStatus;
use App\Models\TeamInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvitationController extends Controller
{
    /**
     * Display the authenticated user's invitations.
     */
    public function index(Request $request): View
    {
        $invitations = TeamInvitation::where('invitee_id', $request->user()->id)
            ->with(['team', 'inviter'])
            ->latest()
            ->get();

        return view('invitations.index', [
            'invitations' => $invitations,
        ]);
    }

    /**
     * Accept the given invitation.
     */
    public function accept(TeamInvitation $invitation): RedirectResponse
    {
        Gate::authorize('respond', $invitation);

        if ($invitation->status !== TeamInvitationStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'This invitation is no longer pending.',
            ]);
        }

        DB::transaction(function () use ($invitation) {
            $invitation->team->users()->syncWithoutDetaching($invitation->invitee_id);

            $invitation->update(['status' => TeamInvitationStatus::Accepted]);
        });

        return redirect()->route('invitations.index');
    }

    /**
     * Decline the given invitation.
     */
    public function decline(TeamInvitation $invitation): RedirectResponse
    {
        Gate::authorize('respond', $invitation);

        if ($invitation->status !== TeamInvitationStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'This invitation is no longer pending.',
            ]);
        }

        $invitation->update(['status' => TeamInvitationStatus::Declined]);

        return redirect()->route('invitations.index');
    }
}
