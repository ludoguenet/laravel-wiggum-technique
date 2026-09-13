<?php

use App\Enums\TeamInvitationStatus;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->owner = User::factory()->create();

    $this->team = Team::factory()->create([
        'owner_id' => $this->owner->id,
    ]);

    $this->team->users()->attach($this->owner);
});

it('allows the team owner to invite a user', function () {
    $invitee = User::factory()->create();

    actingAs($this->owner)
        ->post("/teams/{$this->team->id}/invitations", [
            'user_id' => $invitee->id,
        ])
        ->assertRedirect();

    assertDatabaseHas('team_invitations', [
        'team_id' => $this->team->id,
        'inviter_id' => $this->owner->id,
        'invitee_id' => $invitee->id,
        'status' => TeamInvitationStatus::Pending->value,
    ]);
});

it('does not allow a non-owner to invite a user', function () {
    $member = User::factory()->create();
    $invitee = User::factory()->create();

    $this->team->users()->attach($member);

    actingAs($member)
        ->post("/teams/{$this->team->id}/invitations", [
            'user_id' => $invitee->id,
        ])
        ->assertForbidden();

    expect(TeamInvitation::count())->toBe(0);
});

it('does not allow inviting an existing team member', function () {
    $member = User::factory()->create();

    $this->team->users()->attach($member);

    actingAs($this->owner)
        ->post("/teams/{$this->team->id}/invitations", [
            'user_id' => $member->id,
        ])
        ->assertSessionHasErrors('user_id');

    expect(TeamInvitation::count())->toBe(0);
});

it('does not allow the owner to invite themselves', function () {
    actingAs($this->owner)
        ->post("/teams/{$this->team->id}/invitations", [
            'user_id' => $this->owner->id,
        ])
        ->assertSessionHasErrors('user_id');

    expect(TeamInvitation::count())->toBe(0);
});

it('does not allow duplicate pending invitations', function () {
    $invitee = User::factory()->create();

    TeamInvitation::factory()->create([
        'team_id' => $this->team->id,
        'inviter_id' => $this->owner->id,
        'invitee_id' => $invitee->id,
        'status' => TeamInvitationStatus::Pending,
    ]);

    actingAs($this->owner)
        ->post("/teams/{$this->team->id}/invitations", [
            'user_id' => $invitee->id,
        ])
        ->assertSessionHasErrors('user_id');

    expect(TeamInvitation::count())->toBe(1);
});

it('allows the invitee to see their invitations', function () {
    $invitee = User::factory()->create();

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $this->team->id,
        'inviter_id' => $this->owner->id,
        'invitee_id' => $invitee->id,
        'status' => TeamInvitationStatus::Pending,
    ]);

    actingAs($invitee)
        ->get('/invitations')
        ->assertOk()
        ->assertSee($this->team->name);
});

it('does not show another users invitations', function () {
    $invitee = User::factory()->create();
    $anotherUser = User::factory()->create();

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $this->team->id,
        'inviter_id' => $this->owner->id,
        'invitee_id' => $invitee->id,
        'status' => TeamInvitationStatus::Pending,
    ]);

    actingAs($anotherUser)
        ->get('/invitations')
        ->assertOk()
        ->assertDontSee($this->team->name);
});

it('allows the invitee to accept an invitation', function () {
    $invitee = User::factory()->create();

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $this->team->id,
        'inviter_id' => $this->owner->id,
        'invitee_id' => $invitee->id,
        'status' => TeamInvitationStatus::Pending,
    ]);

    actingAs($invitee)
        ->post("/invitations/{$invitation->id}/accept")
        ->assertRedirect();

    expect(
        $this->team->fresh()
            ->users()
            ->whereKey($invitee->id)
            ->exists()
    )->toBeTrue();

    expect($invitation->fresh()->status)
        ->toBe(TeamInvitationStatus::Accepted);
});

it('does not allow another user to accept an invitation', function () {
    $invitee = User::factory()->create();
    $anotherUser = User::factory()->create();

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $this->team->id,
        'inviter_id' => $this->owner->id,
        'invitee_id' => $invitee->id,
        'status' => TeamInvitationStatus::Pending,
    ]);

    actingAs($anotherUser)
        ->post("/invitations/{$invitation->id}/accept")
        ->assertForbidden();

    expect($invitation->fresh()->status)
        ->toBe(TeamInvitationStatus::Pending);

    expect(
        $this->team
            ->users()
            ->whereKey($invitee->id)
            ->exists()
    )->toBeFalse();
});

it('does not allow an invitation to be accepted twice', function () {
    $invitee = User::factory()->create();

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $this->team->id,
        'inviter_id' => $this->owner->id,
        'invitee_id' => $invitee->id,
        'status' => TeamInvitationStatus::Accepted,
    ]);

    actingAs($invitee)
        ->post("/invitations/{$invitation->id}/accept")
        ->assertSessionHasErrors();
});

it('allows the invitee to decline an invitation', function () {
    $invitee = User::factory()->create();

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $this->team->id,
        'inviter_id' => $this->owner->id,
        'invitee_id' => $invitee->id,
        'status' => TeamInvitationStatus::Pending,
    ]);

    actingAs($invitee)
        ->post("/invitations/{$invitation->id}/decline")
        ->assertRedirect();

    expect($invitation->fresh()->status)
        ->toBe(TeamInvitationStatus::Declined);

    expect(
        $this->team
            ->users()
            ->whereKey($invitee->id)
            ->exists()
    )->toBeFalse();
});

it('does not allow another user to decline an invitation', function () {
    $invitee = User::factory()->create();
    $anotherUser = User::factory()->create();

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $this->team->id,
        'inviter_id' => $this->owner->id,
        'invitee_id' => $invitee->id,
        'status' => TeamInvitationStatus::Pending,
    ]);

    actingAs($anotherUser)
        ->post("/invitations/{$invitation->id}/decline")
        ->assertForbidden();

    expect($invitation->fresh()->status)
        ->toBe(TeamInvitationStatus::Pending);
});

it('does not allow a declined invitation to be accepted', function () {
    $invitee = User::factory()->create();

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $this->team->id,
        'inviter_id' => $this->owner->id,
        'invitee_id' => $invitee->id,
        'status' => TeamInvitationStatus::Declined,
    ]);

    actingAs($invitee)
        ->post("/invitations/{$invitation->id}/accept")
        ->assertSessionHasErrors();

    expect(
        $this->team
            ->users()
            ->whereKey($invitee->id)
            ->exists()
    )->toBeFalse();
});

it('allows the team owner to cancel a pending invitation', function () {
    $invitee = User::factory()->create();

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $this->team->id,
        'inviter_id' => $this->owner->id,
        'invitee_id' => $invitee->id,
        'status' => TeamInvitationStatus::Pending,
    ]);

    actingAs($this->owner)
        ->delete("/teams/{$this->team->id}/invitations/{$invitation->id}")
        ->assertRedirect();

    expect(TeamInvitation::find($invitation->id))->toBeNull();
});

it('does not allow a non-owner to cancel an invitation', function () {
    $member = User::factory()->create();
    $invitee = User::factory()->create();

    $this->team->users()->attach($member);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $this->team->id,
        'inviter_id' => $this->owner->id,
        'invitee_id' => $invitee->id,
        'status' => TeamInvitationStatus::Pending,
    ]);

    actingAs($member)
        ->delete("/teams/{$this->team->id}/invitations/{$invitation->id}")
        ->assertForbidden();

    expect(TeamInvitation::find($invitation->id))->not->toBeNull();
});

it('does not allow the owner to cancel a non-pending invitation', function () {
    $invitee = User::factory()->create();

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $this->team->id,
        'inviter_id' => $this->owner->id,
        'invitee_id' => $invitee->id,
        'status' => TeamInvitationStatus::Accepted,
    ]);

    actingAs($this->owner)
        ->delete("/teams/{$this->team->id}/invitations/{$invitation->id}")
        ->assertSessionHasErrors();

    expect(TeamInvitation::find($invitation->id))->not->toBeNull();
});
