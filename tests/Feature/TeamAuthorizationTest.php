<?php

use App\Models\Team;
use App\Models\User;

test('member can view the team page', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach([$owner->id, $member->id]);

    $response = $this->actingAs($member)->get(route('teams.show', $team));

    $response->assertStatus(200);
});

test('non-member cannot access a private team page', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($owner);

    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->get(route('teams.show', $team));

    $response->assertForbidden();
});

test('guest cannot access a team page', function () {
    $team = Team::factory()->create();
    $team->users()->attach($team->owner_id);

    $response = $this->get(route('teams.show', $team));

    $response->assertRedirect('/login');
});

test('owner can manage the team', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);

    expect($owner->can('update', $team))->toBeTrue();
});

test('member who is not the owner cannot manage the team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach([$owner->id, $member->id]);

    expect($member->can('update', $team))->toBeFalse();
});
