<?php

use App\Enums\TeamInvitationStatus;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;

test('owner can rename their team', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id, 'name' => 'Old Name']);
    $team->users()->attach($owner);

    $response = $this->actingAs($owner)->put(route('teams.update', $team), [
        'name' => 'New Name',
    ]);

    $response->assertRedirect(route('teams.show', $team));
    expect($team->fresh()->name)->toBe('New Name');
});

test('non-owner cannot rename a team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id, 'name' => 'Old Name']);
    $team->users()->attach([$owner->id, $member->id]);

    $response = $this->actingAs($member)->put(route('teams.update', $team), [
        'name' => 'New Name',
    ]);

    $response->assertForbidden();
    expect($team->fresh()->name)->toBe('Old Name');
});

test('renaming a team does not change its owner, members, or invitations', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id, 'name' => 'Old Name']);
    $team->users()->attach([$owner->id, $member->id]);

    $pendingInvitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'status' => TeamInvitationStatus::Pending,
    ]);
    $acceptedInvitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'status' => TeamInvitationStatus::Accepted,
    ]);

    $this->actingAs($owner)->put(route('teams.update', $team), [
        'name' => 'New Name',
    ]);

    $team->refresh();
    expect($team->owner_id)->toBe($owner->id)
        ->and($team->users->pluck('id')->sort()->values()->all())->toBe(collect([$owner->id, $member->id])->sort()->values()->all())
        ->and($pendingInvitation->fresh()->status)->toBe(TeamInvitationStatus::Pending)
        ->and($acceptedInvitation->fresh()->status)->toBe(TeamInvitationStatus::Accepted);
});

test('renaming a team requires a name', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id, 'name' => 'Old Name']);
    $team->users()->attach($owner);

    $response = $this->actingAs($owner)->put(route('teams.update', $team), [
        'name' => '',
    ]);

    $response->assertSessionHasErrors('name');
    expect($team->fresh()->name)->toBe('Old Name');
});
