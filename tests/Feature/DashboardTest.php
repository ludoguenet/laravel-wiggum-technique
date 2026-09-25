<?php

use App\Models\Team;
use App\Models\User;

test('dashboard lists only the teams the user belongs to with owner and member count', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach([$owner->id, $user->id]);
    $otherTeam = Team::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertViewHas('teams', function ($teams) use ($team, $owner) {
            return $teams->count() === 1
                && $teams->first()->is($team)
                && $teams->first()->relationLoaded('owner')
                && $teams->first()->owner->is($owner)
                && $teams->first()->users_count === 2;
        })
        ->assertDontSee($otherTeam->name);
});

test('guests are redirected to login from the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
