@props(['title', 'mode', 'route', 'message', 'answer' => null, 'markdown' => null, 'trace' => null])

<x-layouts.app :title="$title">
    <h1 class="text-2xl font-semibold text-slate-900">{{ $title }}</h1>
    <p class="mt-1 mb-6 text-sm text-slate-500">
        Logged in as: <strong class="text-slate-900">{{ auth()->user()->name }} (#{{ auth()->user()->id }})</strong>
    </p>

    @include('demo.partials.mode-toggle', ['mode' => $mode, 'route' => $route])

    {{ $slot }}

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            <x-card>
                <form method="POST" action="{{ route($route.'.ask') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="mode" value="{{ $mode->value }}">
                    <label for="message" class="text-xs font-semibold tracking-wide text-slate-500 uppercase">User</label>
                    <input
                        id="message"
                        name="message"
                        value="{{ old('message', $message) }}"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none"
                    >
                    @error('message')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <x-button>Ask the assistant</x-button>
                </form>
            </x-card>

            @isset($answer)
                <x-card>
                    <p class="mb-3 text-xs font-semibold tracking-wide text-slate-500 uppercase">Assistant</p>
                    <div class="assistant-answer space-y-3 text-sm leading-relaxed text-slate-800 [&_a]:font-medium [&_a]:text-blue-600 [&_a]:underline [&_code]:rounded [&_code]:bg-slate-100 [&_code]:px-1 [&_li]:ml-5 [&_ol]:list-decimal [&_pre]:overflow-x-auto [&_pre]:rounded [&_pre]:bg-slate-900 [&_pre]:p-3 [&_pre]:text-slate-100 [&_pre_code]:bg-transparent [&_ul]:list-disc">
                        {!! $answer !!}
                    </div>
                </x-card>

                <details class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
                    <summary class="cursor-pointer font-semibold text-slate-700">Debug: raw model output (Markdown)</summary>
                    <pre class="mt-3 overflow-x-auto rounded bg-slate-900 p-3 text-xs whitespace-pre-wrap text-slate-100">{{ $markdown }}</pre>
                </details>
            @endisset
        </div>

        <div class="space-y-6 lg:col-span-2">
            @isset($trace)
                <x-card>
                    <p class="mb-3 text-xs font-semibold tracking-wide text-slate-500 uppercase">Tool calls</p>
                    @forelse ($trace->toolCalls() as $call)
                        <details class="mb-2 font-mono text-xs">
                            <summary class="cursor-pointer text-slate-900">
                                {{ $call['name'] }}({{ json_encode($call['arguments'], JSON_UNESCAPED_SLASHES) }}) → {{ $call['result'] }}
                            </summary>
                        </details>
                    @empty
                        <p class="text-sm text-slate-500">No tool call.</p>
                    @endforelse
                </x-card>

                <div class="rounded-lg border border-slate-800 bg-slate-950 p-5 font-mono text-xs text-slate-200 shadow-sm">
                    <p class="mb-4 font-bold tracking-widest text-slate-400">SECURITY TRACE</p>
                    <dl class="space-y-3">
                        @foreach ($trace->steps() as $step)
                            <div>
                                <dt class="text-slate-500">{{ $step['label'] }}:</dt>
                                <dd @class([
                                    'break-all',
                                    'text-slate-100' => $step['status'] === 'info',
                                    'text-emerald-400' => $step['status'] === 'ok',
                                    'text-red-400 font-bold' => $step['status'] === 'danger',
                                    'text-amber-300 font-bold' => $step['status'] === 'blocked',
                                ])>{{ $step['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endisset
        </div>
    </div>
</x-layouts.app>
