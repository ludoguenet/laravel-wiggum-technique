<?php

use App\Models\Team;
use App\Models\User;

test('authenticated user can create a team', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/teams', [
        'name' => 'Acme',
    ]);

    $team = Team::where('name', 'Acme')->firstOrFail();
    $response->assertRedirect(route('teams.show', $team));
});

test('team creator becomes the owner', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/teams', ['name' => 'Acme']);

    $team = Team::where('name', 'Acme')->firstOrFail();
    expect($team->owner_id)->toBe($user->id);
});

test('team creator becomes a member', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/teams', ['name' => 'Acme']);

    $team = Team::where('name', 'Acme')->firstOrFail();
    expect($team->users->pluck('id'))->toContain($user->id);
});

test('creating a team requires a name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/teams', ['name' => '']);

    $response->assertSessionHasErrors('name');
});

test('user can see their teams', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $user->id]);
    $team->users()->attach($user);

    $response = $this->actingAs($user)->get('/teams');

    $response->assertStatus(200);
    $response->assertSee($team->name);
});

test('user does not see teams they do not belong to', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();

    $response = $this->actingAs($user)->get('/teams');

    $response->assertStatus(200);
    $response->assertDontSee($otherTeam->name);
});

test('guest is redirected to login when creating a team', function () {
    $response = $this->post('/teams', ['name' => 'Acme']);

    $response->assertRedirect('/login');
});
