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
 * SECURE (Demo 1) — the identity comes from the authenticated session, never from the model.
 */
class GetMyOrders implements Tool
{
    public function __construct(
        private User $user,
        private SecurityTrace $trace,
    ) {}

    public function name(): string
    {
        return 'get_my_orders';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return "Get the latest orders of the current user. It can only return the current user's own orders.";
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $query = Order::query()
            ->whereBelongsTo($this->user)
            ->latest()
            ->limit(10);

        $orders = $query->get();

        $this->trace->toolCall($this->name(), $request->all(), "{$orders->count()} order(s)");
        $this->trace->step('Authenticated user', "{$this->user->name} (#{$this->user->id})");
        $this->trace->step('LLM requested', '"my orders" (the tool accepts no user_id)');
        $this->trace->step('Server-side identity', "{$this->user->name} (#{$this->user->id})", 'ok');
        $this->trace->step('Database', $query->toRawSql());
        $this->trace->step('Result', "{$this->user->name}'s orders", 'ok');

        return $orders->toJson();
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
