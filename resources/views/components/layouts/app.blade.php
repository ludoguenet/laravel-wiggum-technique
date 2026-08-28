@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ? $title.' - '.config('app.name') : config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
                <div class="flex items-center gap-8">
                    <a href="{{ route('dashboard') }}" class="text-lg font-semibold text-slate-900">
                        {{ config('app.name') }}
                    </a>

                    <nav class="flex items-center gap-6 text-sm font-medium">
                        <a
                            href="{{ route('dashboard') }}"
                            class="{{ request()->routeIs('dashboard') ? 'text-slate-900' : 'text-slate-500 hover:text-slate-900' }}"
                        >
                            Dashboard
                        </a>
                        <a
                            href="{{ route('teams.index') }}"
                            class="{{ request()->routeIs('teams.*') ? 'text-slate-900' : 'text-slate-500 hover:text-slate-900' }}"
                        >
                            Teams
                        </a>
                        <a
                            href="{{ route('invitations.index') }}"
                            class="{{ request()->routeIs('invitations.*') ? 'text-slate-900' : 'text-slate-500 hover:text-slate-900' }}"
                        >
                            Invitations
                        </a>
                    </nav>
                </div>

                <details class="relative">
                    <summary class="flex list-none items-center gap-1 text-sm font-medium text-slate-700 select-none [&::-webkit-details-marker]:hidden">
                        {{ auth()->user()->name }}
                        <span class="text-xs text-slate-400">&#9662;</span>
                    </summary>

                    <div class="absolute right-0 z-10 mt-2 w-40 rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-slate-600 hover:bg-slate-50">
                                Log out
                            </button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-10">
            {{ $slot }}
        </main>
    </body>
</html>
