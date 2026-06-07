@extends('layouts.app')
@section('title', 'Unirse a una partida')

@section('content')
<h1 class="text-2xl font-serif text-amber-300 mb-6">Unirse a una partida</h1>

@if (request('code'))
    <p class="text-sm text-amber-300 mb-4">Código detectado en la URL: <code>{{ request('code') }}</code></p>
@endif

<form action="{{ route('games.join') }}" method="POST" class="space-y-5 max-w-xl bg-stone-900/60 border border-stone-800 rounded-lg p-6">
    @csrf
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm text-stone-300 mb-1">Código (6 caracteres)</label>
            <input type="text" name="code" required minlength="6" maxlength="6"
                value="{{ old('code', request('code')) }}"
                placeholder="ABC123"
                class="w-full rounded-md bg-stone-950 border border-stone-700 px-3 py-2 text-stone-100 uppercase tracking-widest focus:border-amber-500 focus:outline-none"
                style="text-transform: uppercase;">
        </div>
        <div>
            <label class="block text-sm text-stone-300 mb-1">PIN (4 dígitos)</label>
            <input type="text" name="pin" required pattern="\d{4}" maxlength="4" inputmode="numeric"
                placeholder="0000"
                class="w-full rounded-md bg-stone-950 border border-stone-700 px-3 py-2 text-stone-100 focus:border-amber-500 focus:outline-none">
        </div>
    </div>
    <hr class="border-stone-800">
    <div>
        <label class="block text-sm text-stone-300 mb-1">Tu nombre (visible en el chat)</label>
        <input type="text" name="name" required maxlength="60"
            value="{{ old('name') }}"
            placeholder="Carmen"
            class="w-full rounded-md bg-stone-950 border border-stone-700 px-3 py-2 text-stone-100 focus:border-amber-500 focus:outline-none">
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm text-stone-300 mb-1">Nombre del personaje</label>
            <input type="text" name="character_name" required maxlength="60"
                value="{{ old('character_name') }}"
                placeholder="Aelarin"
                class="w-full rounded-md bg-stone-950 border border-stone-700 px-3 py-2 text-stone-100 focus:border-amber-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm text-stone-300 mb-1">Clase</label>
            <input type="text" name="character_class" maxlength="40"
                value="{{ old('character_class') }}"
                placeholder="Guerrero, Mago, Pícaro…"
                class="w-full rounded-md bg-stone-950 border border-stone-700 px-3 py-2 text-stone-100 focus:border-amber-500 focus:outline-none">
        </div>
    </div>

    <button type="submit" class="px-5 py-2 rounded-md bg-amber-600 hover:bg-amber-500 text-stone-950 font-medium">
        Entrar en la partida →
    </button>
</form>
@endsection
