<x-layouts.app :title="$team->name">
    <h1 class="text-2xl font-semibold text-slate-900">{{ $team->name }}</h1>

    @error('member')
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
    @enderror

    @error('user_id')
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
    @enderror

    <div class="mt-8 grid gap-6 sm:grid-cols-3">
        <x-card>
            <h2 class="text-sm font-medium tracking-wide text-slate-500 uppercase">Owner</h2>
            <p class="mt-2 text-slate-900">{{ $team->owner->name }}</p>
        </x-card>

        <x-card class="sm:col-span-2">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-medium tracking-wide text-slate-500 uppercase">
                    Members ({{ $team->users->count() }})
                </h2>

                @if ($canInviteMembers)
                    <form method="POST" action="{{ route('teams.invitations.store', $team) }}" class="flex items-center gap-2">
                        @csrf
                        <select name="user_id" class="rounded-md border-slate-300 text-sm">
                            <option value="">Select a user&hellip;</option>
                            @foreach ($invitableUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                        <x-button type="submit">Invite member</x-button>
                    </form>
                @endif
            </div>

            <ul class="mt-4 divide-y divide-slate-100">
                @foreach ($team->users as $member)
                    <li class="flex items-center justify-between py-3">
                        <span class="text-slate-900">{{ $member->name }}</span>

                        @if ($member->id === $team->owner_id)
                            <span class="text-sm text-slate-500">Owner</span>
                        @elseif ($canRemoveMembers)
                            <form method="POST" action="{{ route('teams.members.destroy', [$team, $member]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Remove</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-card>
    </div>
</x-layouts.app>
