@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ? $title.' - '.config('app.name') : config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-slate-50 px-6 antialiased">
        <div class="w-full max-w-sm">
            <div class="mb-8 text-center text-xl font-semibold text-slate-900">
                {{ config('app.name') }}
            </div>

            {{ $slot }}
        </div>
    </body>
</html>
