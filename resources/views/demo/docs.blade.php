<x-demo.assistant
    title="Demo 2 · Internal docs assistant"
    route="demo.docs"
    :mode="$mode"
    :message="$message"
    :answer="$answer ?? null"
    :markdown="$markdown ?? null"
    :trace="$trace ?? null"
>
    <div class="mb-6 flex flex-wrap gap-2 font-mono text-xs">
        <span class="rounded bg-emerald-100 px-2 py-1 text-emerald-800">✓ search_documents</span>
        <span class="rounded bg-emerald-100 px-2 py-1 text-emerald-800">✓ get_document</span>
        @foreach (['create', 'update', 'delete', 'send email', 'execute command'] as $capability)
            <span class="rounded bg-slate-100 px-2 py-1 text-slate-400 line-through">✗ {{ $capability }}</span>
        @endforeach
        <span class="ml-auto self-center font-sans text-slate-500">This agent has no write capabilities.</span>
    </div>
</x-demo.assistant>
