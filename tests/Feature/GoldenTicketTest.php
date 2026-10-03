<?php

use App\Models\Tour;

beforeEach(function () {
    Tour::factory()->lastTicket()->create(['name' => 'Visite de la chocolaterie Wonka']);
});

test('naive claim oversells the last ticket when Veruca buys it first', function () {
    $response = $this->postJson(route('claim.naively'));

    $response->assertOk()->assertJson([
        'message' => 'Charlie a son Golden Ticket ! 🎫',
        'tickets_left' => -1,
        'tickets_sold' => 2,
    ]);
});

test('safe claim refuses the ticket when Veruca buys it first', function () {
    $response = $this->postJson(route('claim.safely'));

    $response->assertConflict()->assertJson([
        'message' => 'Trop tard, Veruca a eu le dernier ticket. 🍫',
        'tickets_left' => 0,
        'tickets_sold' => 1,
    ]);
});

test('safe claim hands out the last ticket when Veruca stays home', function () {
    $response = $this->postJson(route('claim.safely'), ['veruca' => false]);

    $response->assertOk()->assertJson([
        'message' => 'Charlie a son Golden Ticket ! 🎫',
        'tickets_left' => 0,
        'tickets_sold' => 1,
    ]);
});

test('reset puts the last ticket back', function () {
    $this->postJson(route('claim.naively'));

    $response = $this->postJson(route('tickets.reset'));

    $response->assertOk()->assertJson(['tickets_left' => 1, 'tickets_sold' => 0]);
});
