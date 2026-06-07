@extends('layouts.app')
@section('title', 'Nueva partida')

@section('content')
<h1 class="text-2xl font-serif text-amber-300 mb-6">Invocar una nueva aventura</h1>

<form action="{{ route('games.store') }}" method="POST" class="space-y-5 max-w-xl bg-stone-900/60 border border-stone-800 rounded-lg p-6">
    @csrf
    <div>
        <label class="block text-sm text-stone-300 mb-1">Título de la campaña</label>
        <input type="text" name="title" required maxlength="120"
            placeholder="La sombra de Eldoria"
            class="w-full rounded-md bg-stone-950 border border-stone-700 px-3 py-2 text-stone-100 focus:border-amber-500 focus:outline-none">
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm text-stone-300 mb-1">Nombre del personaje</label>
            <input type="text" name="character_name" required maxlength="60"
                placeholder="Aelarin"
                class="w-full rounded-md bg-stone-950 border border-stone-700 px-3 py-2 text-stone-100 focus:border-amber-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm text-stone-300 mb-1">Clase</label>
            <input type="text" name="character_class" maxlength="40"
                placeholder="Guerrero, Mago, Pícaro…"
                class="w-full rounded-md bg-stone-950 border border-stone-700 px-3 py-2 text-stone-100 focus:border-amber-500 focus:outline-none">
        </div>
    </div>

    <p class="text-xs text-stone-400">
        Empezarás con 20 HP, 10 de oro, nivel 1 y un modesto inventario.
        El GameMaster narrará el comienzo de la aventura.
    </p>

    <button type="submit" class="px-5 py-2 rounded-md bg-amber-600 hover:bg-amber-500 text-stone-950 font-medium">
        Comenzar aventura →
    </button>
</form>
@endsection
