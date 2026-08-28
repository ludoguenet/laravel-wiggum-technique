<?php

use App\Models\Team;
use App\Models\User;

test('team owner can remove a member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach([$owner->id, $member->id]);

    $response = $this->actingAs($owner)->delete(route('teams.members.destroy', [$team, $member]));

    $response->assertRedirect(route('teams.show', $team));
    expect($team->users()->whereKey($member->id)->exists())->toBeFalse();
});

test('non-owner cannot remove a member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $anotherMember = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach([$owner->id, $member->id, $anotherMember->id]);

    $response = $this->actingAs($member)->delete(route('teams.members.destroy', [$team, $anotherMember]));

    $response->assertForbidden();
    expect($team->users()->whereKey($anotherMember->id)->exists())->toBeTrue();
});

test('team owner cannot remove themselves', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($owner);

    $response = $this->actingAs($owner)->delete(route('teams.members.destroy', [$team, $owner]));

    $response->assertSessionHasErrors('member');
    expect($team->users()->whereKey($owner->id)->exists())->toBeTrue();
});

test('duplicate team membership is prevented', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($owner);

    expect(fn () => $team->users()->attach($owner))->toThrow(Illuminate\Database\QueryException::class);
});
