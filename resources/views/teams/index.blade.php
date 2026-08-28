<x-layouts.app title="Teams">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">My Teams</h1>
        <x-button href="{{ route('teams.create') }}">Create team</x-button>
    </div>

    <div class="mt-8">
        @if ($teams->isEmpty())
            <x-card class="text-center text-slate-500">
                You don't belong to any teams yet.
            </x-card>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($teams as $team)
                    <x-card>
                        <h3 class="font-semibold text-slate-900">{{ $team->name }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Owner: {{ $team->owner->name }}</p>
                        <p class="text-sm text-slate-500">{{ $team->users_count }} {{ Str::plural('member', $team->users_count) }}</p>
                        <a href="{{ route('teams.show', $team) }}" class="mt-4 inline-block text-sm font-medium text-slate-900 hover:underline">
                            View team &rarr;
                        </a>
                    </x-card>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>
