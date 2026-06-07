@extends('layouts.app')
@section('title', 'Tus partidas')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-serif text-amber-300">Tus partidas</h1>
    <a href="{{ route('games.create') }}" class="px-4 py-2 rounded-md bg-amber-600 hover:bg-amber-500 text-stone-950 font-medium">+ Nueva partida</a>
</div>

@if ($games->isEmpty())
    <div class="rounded-lg border border-dashed border-stone-700 p-10 text-center text-stone-400">
        <p class="text-lg mb-2">Aún no has comenzado ninguna aventura.</p>
        <p>Pulsa <strong class="text-amber-300">Nueva partida</strong> para invocar al GameMaster.</p>
    </div>
@else
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($games as $g)
            <a href="{{ route('games.show', $g) }}" class="block rounded-lg border border-stone-800 bg-stone-900/60 p-4 hover:border-amber-600 transition">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="font-serif text-amber-200 text-lg truncate">{{ $g->title }}</h2>
                    <span class="text-xs text-stone-500">Nv. {{ $g->level }}</span>
                </div>
                <p class="text-sm text-stone-300">
                    <span class="text-stone-500">Héroe:</span> {{ $g->character_name }}
                    @if ($g->character_class)
                        <span class="text-stone-500">·</span> {{ $g->character_class }}
                    @endif
                </p>
                <p class="text-sm text-stone-300 mt-1">
                    <span class="text-stone-500">HP:</span>
                    <span class="{{ $g->hp < $g->hp_max / 2 ? 'text-red-400' : 'text-emerald-300' }}">{{ $g->hp }}/{{ $g->hp_max }}</span>
                    <span class="text-stone-500 ml-2">Oro:</span> {{ $g->gold }}
                </p>
                <p class="text-xs text-stone-500 mt-2 truncate">📍 {{ $g->location ?? '—' }}</p>
            </a>
        @endforeach
    </div>
@endif
@endsection
