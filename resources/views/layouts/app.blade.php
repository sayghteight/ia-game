<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>

    @fonts

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-full bg-stone-950 text-stone-100 font-sans antialiased">
    <header class="border-b border-stone-800 bg-stone-900/70 backdrop-blur sticky top-0 z-10">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
            <a href="{{ route('games.index') }}" class="flex items-center gap-2 text-amber-400 hover:text-amber-300">
                <span class="text-2xl">⚔️</span>
                <span class="font-serif text-lg tracking-wide">{{ config('app.name') }}</span>
            </a>
            <nav class="flex items-center gap-3 text-sm">
                <a href="{{ route('games.index') }}" class="text-stone-300 hover:text-white">Partidas</a>
                <a href="{{ route('games.prompt.edit') }}" class="text-stone-300 hover:text-white">Prompt GM</a>
                <a href="{{ route('games.create') }}" class="px-3 py-1.5 rounded-md bg-amber-600 hover:bg-amber-500 text-stone-950 font-medium">+ Nueva</a>
            </nav>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-6">
        @if (session('status'))
            <div class="mb-4 p-3 rounded-md bg-emerald-900/40 border border-emerald-700 text-emerald-200 text-sm">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 p-3 rounded-md bg-red-900/40 border border-red-700 text-red-200 text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>

    <footer class="max-w-6xl mx-auto px-4 py-6 text-center text-xs text-stone-500">
        GameMaster IA · MiniMax Token Plan
    </footer>
</body>
</html>
