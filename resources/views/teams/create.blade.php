<x-layouts.app title="Create Team">
    <h1 class="text-2xl font-semibold text-slate-900">Create a team</h1>

    <x-card class="mt-8 max-w-md">
        <form method="POST" action="{{ route('teams.store') }}" class="space-y-4">
            @csrf

            <div>
                <x-label for="name">Team name</x-label>
                <x-input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus />
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <x-button>Create team</x-button>
        </form>
    </x-card>
</x-layouts.app>
