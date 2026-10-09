<?php

namespace App\Support;

/**
 * Collects what really happened during one assistant request so the UI can show it on screen.
 */
class SecurityTrace
{
    /**
     * @var list<array{label: string, value: string, status: 'info'|'ok'|'danger'|'blocked'}>
     */
    private array $steps = [];

    /**
     * @var list<array{name: string, arguments: array<string, mixed>, result: string}>
     */
    private array $toolCalls = [];

    /**
     * @param  'info'|'ok'|'danger'|'blocked'  $status
     */
    public function step(string $label, string $value, string $status = 'info'): void
    {
        $this->steps[] = ['label' => $label, 'value' => $value, 'status' => $status];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function toolCall(string $name, array $arguments, string $result): void
    {
        $this->toolCalls[] = ['name' => $name, 'arguments' => $arguments, 'result' => $result];
    }

    /**
     * @return list<array{label: string, value: string, status: 'info'|'ok'|'danger'|'blocked'}>
     */
    public function steps(): array
    {
        return $this->steps;
    }

    /**
     * @return list<array{name: string, arguments: array<string, mixed>, result: string}>
     */
    public function toolCalls(): array
    {
        return $this->toolCalls;
    }
}
