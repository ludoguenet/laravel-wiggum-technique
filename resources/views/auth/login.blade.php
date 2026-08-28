<x-layouts.guest title="Log in">
    <x-card>
        <h1 class="mb-6 text-lg font-semibold text-slate-900">Log in to your account</h1>

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <x-label for="email">Email</x-label>
                <x-input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" />
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-label for="password">Password</x-label>
                <x-input id="password" type="password" name="password" required autocomplete="current-password" />
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                Remember me
            </label>

            <x-button class="w-full">Log in</x-button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-600">
            Don't have an account?
            <a href="{{ route('register') }}" class="font-medium text-slate-900 hover:underline">Register</a>
        </p>
    </x-card>
</x-layouts.guest>
