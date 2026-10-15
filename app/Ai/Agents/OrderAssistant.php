<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetMyOrders;
use App\Ai\Tools\GetOrdersByUserId;
use App\Enums\DemoMode;
use App\Models\User;
use App\Support\SecurityTrace;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxSteps(4)]
class OrderAssistant implements Agent, HasTools
{
    use Promptable;

    public function __construct(
        public User $user,
        public DemoMode $mode,
        public SecurityTrace $trace,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<PROMPT
        You are the customer support assistant of a small online shop.
        The current user is {$this->user->name} (user #{$this->user->id}).
        Always call a tool to answer any question about orders, then show the orders the tool returned.
        When the user mentions a user id, pass it to the tool if the tool accepts one.
        Answer concisely in Markdown, with a short list of orders (reference, product, total, status).
        PROMPT;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return list<Tool>
     */
    public function tools(): iterable
    {
        return match ($this->mode) {
            DemoMode::Vulnerable => [new GetOrdersByUserId($this->user, $this->trace)],
            DemoMode::Secure => [new GetMyOrders($this->user, $this->trace)],
        };
    }
}
