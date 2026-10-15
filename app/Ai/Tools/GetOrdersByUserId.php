<?php

namespace App\Ai\Tools;

use App\Models\Order;
use App\Models\User;
use App\Support\SecurityTrace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * ⚠️ VULNERABLE ON PURPOSE (Demo 1) — the model chooses whose orders are read.
 */
class GetOrdersByUserId implements Tool
{
    public function __construct(
        private User $user,
        private SecurityTrace $trace,
    ) {}

    public function name(): string
    {
        return 'get_orders';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Get the latest orders of a user, given their user_id.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $query = Order::query()
            ->where('user_id', $request['user_id'])
            ->latest();

        $orders = $query->get();
        $owner = User::find($request['user_id']);

        $this->trace->toolCall($this->name(), $request->all(), "{$orders->count()} order(s)");
        $this->trace->step('Authenticated user', "{$this->user->name} (#{$this->user->id})");
        $this->trace->step('LLM tool argument', "user_id = {$request['user_id']}", $owner?->is($this->user) ? 'ok' : 'danger');
        $this->trace->step('Database', $query->toRawSql(), $owner?->is($this->user) ? 'info' : 'danger');
        $this->trace->step('Result', $owner ? "{$owner->name}'s orders" : 'No orders', $owner?->is($this->user) ? 'ok' : 'danger');

        return $orders->toJson();
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()->description('The id of the user whose orders should be returned.')->required(),
        ];
    }
}
