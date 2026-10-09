<?php

use App\Ai\Agents\OrderAssistant;
use App\Ai\Tools\GetMyOrders;
use App\Ai\Tools\GetOrdersByUserId;
use App\Models\Order;
use App\Models\User;
use App\Support\SecurityTrace;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->alice = User::factory()->create(['name' => 'Alice']);
    $this->bob = User::factory()->create(['name' => 'Bob']);

    Order::factory()->for($this->alice)->create(['reference' => 'ORD-ALICE']);
    Order::factory()->for($this->bob)->create(['reference' => 'ORD-BOB']);
});

test('the vulnerable get_orders tool leaks Bob\'s orders to Alice when the model passes user_id 2', function () {
    $tool = new GetOrdersByUserId($this->alice, new SecurityTrace);

    $result = $tool->handle(new Request(['user_id' => $this->bob->id]));

    expect($result)->toContain('ORD-BOB')->not->toContain('ORD-ALICE');
});

test('the secure tool only returns the authenticated user\'s orders, whatever the model sends', function () {
    $tool = new GetMyOrders($this->alice, new SecurityTrace);

    $result = $tool->handle(new Request(['user_id' => $this->bob->id]));

    expect($result)->toContain('ORD-ALICE')->not->toContain('ORD-BOB');
});

test('the secure tool does not let the model choose a user_id', function () {
    $tool = new GetMyOrders($this->alice, new SecurityTrace);

    expect($tool->schema(new JsonSchemaTypeFactory))->not->toHaveKey('user_id');
});

test('the orders assistant answers through the chat page', function () {
    OrderAssistant::fake(['You have **1 order**: ORD-ALICE.']);

    $response = $this->actingAs($this->alice)->post(route('demo.orders.ask'), [
        'message' => 'Show me my latest orders.',
        'mode' => 'secure',
    ]);

    $response->assertOk()->assertSee('<strong>1 order</strong>', false);
    OrderAssistant::assertPrompted('Show me my latest orders.');
});

test('the orders assistant page shows the tool exposed in vulnerable mode', function () {
    $this->actingAs($this->alice)->get(route('demo.orders'))
        ->assertOk()
        ->assertSee('VULNERABLE MODE')
        ->assertSee('get_orders({ user_id })');
});
