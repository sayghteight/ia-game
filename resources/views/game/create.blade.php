@extends('layouts.app')
@section('title', 'Nueva partida')

@section('content')
<h1 class="text-2xl font-serif text-amber-300 mb-2">Invocar una nueva aventura</h1>
<p class="text-stone-400 text-sm mb-6">Serás el creador. Tras crearla, recibirás un <strong>código</strong> y un <strong>PIN</strong> para invitar a otros jugadores.</p>

<form action="{{ route('games.store') }}" method="POST" class="space-y-5 max-w-xl bg-stone-900/60 border border-stone-800 rounded-lg p-6">
    @csrf
    <div>
        <label class="block text-sm text-stone-300 mb-1">Título de la campaña</label>
        <input type="text" name="title" required maxlength="120"
            placeholder="La sombra de Eldoria"
            class="w-full rounded-md bg-stone-950 border border-stone-700 px-3 py-2 text-stone-100 focus:border-amber-500 focus:outline-none">
    </div>
    <div>
        <label class="block text-sm text-stone-300 mb-1">Tu nombre (visible en el chat)</label>
        <input type="text" name="name" required maxlength="60"
            placeholder="Carmen"
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
    <div>
        <label class="block text-sm text-stone-300 mb-1">PIN de la partida (opcional, 4 dígitos)</label>
        <input type="text" name="pin" pattern="\d{4}" maxlength="4" inputmode="numeric"
            placeholder="Aleatorio si lo dejas vacío"
            class="w-full rounded-md bg-stone-950 border border-stone-700 px-3 py-2 text-stone-100 focus:border-amber-500 focus:outline-none">
        <p class="text-xs text-stone-500 mt-1">Lo compartirás con quien quieras que se una.</p>
    </div>

    <p class="text-xs text-stone-400">
        Tu personaje empezará con 20 HP, 10 de oro, nivel 1 y un modesto inventario.
    </p>

    <button type="submit" class="px-5 py-2 rounded-md bg-amber-600 hover:bg-amber-500 text-stone-950 font-medium">
        Crear partida →
    </button>
</form>
@endsection
