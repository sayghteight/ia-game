@extends('layouts.app')
@section('title', 'Tus partidas')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-serif text-amber-300">Tus partidas</h1>
    <div class="flex gap-2">
        <a href="{{ route('games.join.form') }}" class="px-4 py-2 rounded-md border border-amber-600 text-amber-300 hover:bg-amber-600/10">Unirse</a>
        <a href="{{ route('games.create') }}" class="px-4 py-2 rounded-md bg-amber-600 hover:bg-amber-500 text-stone-950 font-medium">+ Nueva</a>
    </div>
</div>

@if ($games->isEmpty())
    <div class="rounded-lg border border-dashed border-stone-700 p-10 text-center text-stone-400">
        <p class="text-lg mb-2">Aún no formas parte de ninguna partida.</p>
        <p>Crea una nueva o <a href="{{ route('games.join.form') }}" class="text-amber-300 hover:underline">únete</a> con un código.</p>
    </div>
@else
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($games as $g)
            <a href="{{ route('games.show', ['game' => $g->code]) }}" class="block rounded-lg border border-stone-800 bg-stone-900/60 p-4 hover:border-amber-600 transition">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="font-serif text-amber-200 text-lg truncate">{{ $g->title }}</h2>
                    <span class="text-xs text-stone-500">Ronda {{ $g->current_round }}</span>
                </div>
                <p class="text-sm text-stone-300">
                    <span class="text-stone-500">Código:</span> <code class="text-amber-300">{{ $g->code }}</code>
                </p>
                <p class="text-xs text-stone-500 mt-2 truncate">📍 {{ $g->location ?? '—' }}</p>
            </a>
        @endforeach
    </div>
@endif
@endsection
