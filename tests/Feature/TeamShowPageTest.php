<?php

use App\Enums\TeamInvitationStatus;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;

test('owner sees the rename form, pending invitations, and no leave button', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($owner);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'inviter_id' => $owner->id,
        'invitee_id' => $invitee->id,
        'status' => TeamInvitationStatus::Pending,
    ]);

    $response = $this->actingAs($owner)->get(route('teams.show', $team));

    $response->assertOk()
        ->assertSee('Rename team')
        ->assertSee($invitee->name)
        ->assertDontSee('Leave team');
});

test('non-owner member sees the leave button but not the rename form or invitations', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach([$owner->id, $member->id]);

    $response = $this->actingAs($member)->get(route('teams.show', $team));

    $response->assertOk()
        ->assertSee('Leave team')
        ->assertDontSee('Rename team')
        ->assertDontSee('Pending invitations');
});

test('cancelled invitation no longer appears on the team page', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($owner);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'inviter_id' => $owner->id,
        'invitee_id' => $invitee->id,
        'status' => TeamInvitationStatus::Pending,
    ]);

    $this->actingAs($owner)->delete(route('teams.invitations.destroy', [$team, $invitation]));

    $response = $this->actingAs($owner)->get(route('teams.show', $team));

    $response->assertOk()->assertSee('No pending invitations.');
});
