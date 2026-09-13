<x-layouts.app :title="$team->name">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">{{ $team->name }}</h1>

        @if ($canLeave)
            <form method="POST" action="{{ route('teams.leave', $team) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Leave team</button>
            </form>
        @endif
    </div>

    @error('member')
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
    @enderror

    @error('user_id')
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
    @enderror

    @error('team')
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
    @enderror

    @error('status')
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
    @enderror

    <div class="mt-8 grid gap-6 sm:grid-cols-3">
        <x-card>
            <h2 class="text-sm font-medium tracking-wide text-slate-500 uppercase">Owner</h2>
            <p class="mt-2 text-slate-900">{{ $team->owner->name }}</p>

            @if ($canUpdate)
                <form method="POST" action="{{ route('teams.update', $team) }}" class="mt-4 space-y-2">
                    @csrf
                    @method('PUT')
                    <x-label for="name">Team name</x-label>
                    <x-input id="name" type="text" name="name" value="{{ old('name', $team->name) }}" required />
                    @error('name')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <x-button type="submit">Rename team</x-button>
                </form>
            @endif
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

        @if ($canUpdate)
            <x-card class="sm:col-span-3">
                <h2 class="text-sm font-medium tracking-wide text-slate-500 uppercase">
                    Pending invitations ({{ $pendingInvitations->count() }})
                </h2>

                <ul class="mt-4 divide-y divide-slate-100">
                    @forelse ($pendingInvitations as $invitation)
                        <li class="flex items-center justify-between py-3">
                            <span class="text-slate-900">{{ $invitation->invitee->name }}</span>
                            <form method="POST" action="{{ route('teams.invitations.destroy', [$team, $invitation]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Cancel</button>
                            </form>
                        </li>
                    @empty
                        <li class="py-3 text-sm text-slate-500">No pending invitations.</li>
                    @endforelse
                </ul>
            </x-card>
        @endif
    </div>
</x-layouts.app>
