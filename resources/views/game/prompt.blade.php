@extends('layouts.app')
@section('title', 'Prompt del GameMaster')

@section('content')
<h1 class="text-2xl font-serif text-amber-300 mb-2">Prompt del GameMaster</h1>
<p class="text-sm text-stone-400 mb-4">
    Edita el prompt que se enviará a MiniMax. Los cambios se aplican a partir de la siguiente
    petición a la API (las partidas en curso usan el prompt actualizado a partir de su próximo turno).
    Archivo: <code class="text-stone-300">{{ $path }}</code>
</p>

<form action="{{ route('games.prompt.update') }}" method="POST" class="space-y-4">
    @csrf
    @method('PUT')
    <textarea name="content" rows="24" required
        class="w-full rounded-md bg-stone-950 border border-stone-700 px-3 py-2 text-stone-100 font-mono text-sm focus:border-amber-500 focus:outline-none">{{ $content }}</textarea>
    <div class="flex gap-3">
        <button class="px-5 py-2 rounded-md bg-amber-600 hover:bg-amber-500 text-stone-950 font-medium">Guardar prompt</button>
        <a href="{{ route('games.index') }}" class="px-5 py-2 rounded-md border border-stone-700 text-stone-300 hover:bg-stone-800">Cancelar</a>
    </div>
</form>
@endsection
