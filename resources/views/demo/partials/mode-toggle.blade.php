@props(['mode', 'route'])

<div @class([
    'mb-6 flex items-center justify-between rounded-lg border px-4 py-3',
    'border-red-300 bg-red-50' => $mode === \App\Enums\DemoMode::Vulnerable,
    'border-emerald-300 bg-emerald-50' => $mode === \App\Enums\DemoMode::Secure,
])>
    <span @class([
        'text-sm font-bold tracking-widest',
        'text-red-700' => $mode === \App\Enums\DemoMode::Vulnerable,
        'text-emerald-700' => $mode === \App\Enums\DemoMode::Secure,
    ])>
        {{ $mode === \App\Enums\DemoMode::Vulnerable ? '⚠ VULNERABLE MODE' : '✓ SECURE MODE' }}
    </span>

    <div class="flex gap-2 text-sm">
        @foreach (\App\Enums\DemoMode::cases() as $case)
            <a
                href="{{ route($route, ['mode' => $case->value]) }}"
                @class([
                    'rounded-md px-3 py-1 font-medium',
                    'bg-slate-900 text-white' => $case === $mode,
                    'text-slate-600 hover:bg-white' => $case !== $mode,
                ])
            >
                {{ ucfirst($case->value) }}
            </a>
        @endforeach
    </div>
</div>
