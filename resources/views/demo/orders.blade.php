<x-demo.assistant
    title="Demo 1 · Orders assistant"
    route="demo.orders"
    :mode="$mode"
    :message="$message"
    :answer="$answer ?? null"
    :markdown="$markdown ?? null"
    :trace="$trace ?? null"
>
    <p class="mb-6 text-sm text-slate-600">
        Tool available to the model:
        <code class="rounded bg-slate-100 px-1 font-mono">
            {{ $mode === \App\Enums\DemoMode::Vulnerable ? 'get_orders({ user_id })' : 'get_my_orders()' }}
        </code>
    </p>
</x-demo.assistant>
