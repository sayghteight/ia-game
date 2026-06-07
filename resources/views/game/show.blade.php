@extends('layouts.app')
@section('title', $game->title)

@section('content')
<div class="grid lg:grid-cols-[1fr_320px] gap-6">
    {{-- Chat --}}
    <section class="flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-2xl font-serif text-amber-300">{{ $game->title }}</h1>
                <p class="text-sm text-stone-400">
                    {{ $game->character_name }}
                    @if ($game->character_class) · {{ $game->character_class }} @endif
                    · Nv. {{ $game->level }}
                </p>
            </div>
            <form action="{{ route('games.destroy', $game) }}" method="POST" onsubmit="return confirm('¿Eliminar esta partida?')">
                @csrf @method('DELETE')
                <button class="text-xs text-stone-500 hover:text-red-400">Eliminar</button>
            </form>
        </div>

        <div class="flex-1 space-y-4 mb-4 max-h-[70vh] overflow-y-auto pr-2">
            @foreach ($game->messages as $msg)
                @if ($msg->role === 'system') @continue @endif
                @if ($msg->role === 'user')
                    <div class="flex justify-end">
                        <div class="max-w-[80%] rounded-lg bg-amber-700/80 text-stone-50 px-4 py-2 shadow">
                            <p class="text-xs text-amber-200/80 mb-1">⚔️ Tú</p>
                            <p class="whitespace-pre-wrap leading-relaxed">{{ $msg->content }}</p>
                        </div>
                    </div>
                @else
                    <div class="flex justify-start">
                        <div class="max-w-[85%] rounded-lg bg-stone-800/80 border border-stone-700 px-4 py-2 shadow">
                            <p class="text-xs text-amber-300/80 mb-1">🎲 GameMaster</p>
                            <div class="prose prose-invert prose-sm max-w-none whitespace-pre-wrap leading-relaxed">{{ $msg->content }}</div>
                            @if ($msg->tokens_input || $msg->tokens_output)
                                <p class="text-[10px] text-stone-500 mt-2">
                                    tokens: in {{ $msg->tokens_input ?? '—' }} / out {{ $msg->tokens_output ?? '—' }}
                                </p>
                            @endif
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        <form action="{{ route('games.act', $game) }}" method="POST" class="flex gap-2">
            @csrf
            <input type="text" name="action" required maxlength="2000" autofocus
                placeholder="¿Qué haces? (ej. 'Inspecciono la mesa con cuidado')"
                class="flex-1 rounded-md bg-stone-950 border border-stone-700 px-3 py-2 text-stone-100 focus:border-amber-500 focus:outline-none">
            <button class="px-4 py-2 rounded-md bg-amber-600 hover:bg-amber-500 text-stone-950 font-medium">Enviar</button>
        </form>
    </section>

    {{-- Sidebar con estado --}}
    <aside class="space-y-4">
        <div class="rounded-lg border border-stone-800 bg-stone-900/60 p-4">
            <h3 class="font-serif text-amber-300 mb-3">Estado</h3>
            <dl class="text-sm space-y-1">
                <div class="flex justify-between">
                    <dt class="text-stone-400">HP</dt>
                    <dd class="{{ $game->hp < $game->hp_max / 2 ? 'text-red-400' : 'text-emerald-300' }}">{{ $game->hp }} / {{ $game->hp_max }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-stone-400">Nivel</dt>
                    <dd>{{ $game->level }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-stone-400">Experiencia</dt>
                    <dd>{{ $game->experience }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-stone-400">Oro</dt>
                    <dd class="text-amber-300">{{ $game->gold }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg border border-stone-800 bg-stone-900/60 p-4">
            <h3 class="font-serif text-amber-300 mb-2">📍 Ubicación</h3>
            <p class="text-sm text-stone-200">{{ $game->location ?? '—' }}</p>
        </div>

        <div class="rounded-lg border border-stone-800 bg-stone-900/60 p-4">
            <h3 class="font-serif text-amber-300 mb-2">🎒 Inventario</h3>
            @if (empty($game->inventory_list))
                <p class="text-sm text-stone-500">Vacío.</p>
            @else
                <ul class="text-sm text-stone-200 list-disc list-inside space-y-1">
                    @foreach ($game->inventory_list as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if ($game->status_notes)
            <div class="rounded-lg border border-stone-800 bg-stone-900/60 p-4">
                <h3 class="font-serif text-amber-300 mb-2">📜 Notas del mundo</h3>
                <p class="text-sm text-stone-300 leading-relaxed">{{ $game->status_notes }}</p>
            </div>
        @endif
    </aside>
</div>
@endsection
