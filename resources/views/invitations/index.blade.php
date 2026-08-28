<x-layouts.app title="Invitations">
    <h1 class="text-2xl font-semibold text-slate-900">Invitations</h1>

    <div class="mt-8 space-y-4">
        @forelse ($invitations as $invitation)
            <x-card class="flex items-center justify-between">
                <div>
                    <p class="text-slate-900">{{ $invitation->team->name }}</p>
                    <p class="text-sm text-slate-500">Invited by {{ $invitation->inviter->name }}</p>
                </div>

                @if ($invitation->status === \App\Enums\TeamInvitationStatus::Pending)
                    <div class="flex items-center gap-3">
                        <form method="POST" action="{{ route('invitations.accept', $invitation) }}">
                            @csrf
                            <x-button type="submit">Accept</x-button>
                        </form>

                        <form method="POST" action="{{ route('invitations.decline', $invitation) }}">
                            @csrf
                            <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Decline</button>
                        </form>
                    </div>
                @else
                    <span class="text-sm text-slate-500">{{ ucfirst($invitation->status->value) }}</span>
                @endif
            </x-card>
        @empty
            <x-card class="text-center text-slate-500">
                You have no invitations.
            </x-card>
        @endforelse
    </div>
</x-layouts.app>
