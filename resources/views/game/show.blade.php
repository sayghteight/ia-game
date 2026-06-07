@extends('layouts.app')
@section('title', $game->title)

@section('content')
<div class="grid lg:grid-cols-[1fr_340px] gap-6">
    {{-- Chat principal --}}
    <section class="flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-2xl font-serif text-amber-300">{{ $game->title }}</h1>
                <p class="text-sm text-stone-400">
                    Ronda {{ $game->current_round }}
                    · Código <code class="text-amber-300">{{ $game->code }}</code>
                </p>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <button type="button" id="copy-invite" class="text-xs text-stone-400 hover:text-amber-300" data-code="{{ $game->code }}" data-pin="{{ $game->pin }}">
                    📋 Copiar invitación
                </button>
                @if ($currentPlayer->is_creator)
                    <form action="{{ route('games.destroy', ['game' => $game->code]) }}" method="POST" onsubmit="return confirm('¿Eliminar esta partida?')">
                        @csrf @method('DELETE')
                        <button class="text-xs text-stone-500 hover:text-red-400">Eliminar</button>
                    </form>
                @endif
            </div>
        </div>

        <div id="chat-messages" class="flex-1 space-y-4 mb-4 max-h-[60vh] overflow-y-auto pr-2">
            @foreach ($game->messages as $msg)
                @if ($msg->role === 'system') @continue @endif
                @if ($msg->role === 'user')
                    <div class="flex justify-end">
                        <div class="max-w-[80%] rounded-lg bg-amber-700/80 text-stone-50 px-4 py-2 shadow">
                            <p class="text-xs text-amber-200/80 mb-1">⚔️ {{ $msg->player?->name ?? 'Jugador' }}</p>
                            <p class="whitespace-pre-wrap leading-relaxed">{{ $msg->content }}</p>
                        </div>
                    </div>
                @else
                    <div class="flex justify-start">
                        <div class="max-w-[85%] rounded-lg bg-stone-800/80 border border-stone-700 px-4 py-2 shadow">
                            <p class="text-xs text-amber-300/80 mb-1">🎲 GameMaster (ronda {{ $msg->round }})</p>
                            <div class="whitespace-pre-wrap leading-relaxed">{{ $msg->content }}</div>
                            @if ($msg->tokens_input || $msg->tokens_output)
                                <p class="text-[10px] text-stone-500 mt-2">tokens: in {{ $msg->tokens_input ?? '—' }} / out {{ $msg->tokens_output ?? '—' }}</p>
                            @endif
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Acciones pendientes --}}
        @php $pending = $game->pendingMessages()->with('player')->get(); @endphp
        @if ($pending->isNotEmpty())
            <div class="mb-3 rounded-md border border-amber-700/60 bg-amber-900/20 p-3">
                <p class="text-xs text-amber-300 mb-1">⏳ Acciones pendientes (ronda {{ $game->current_round }})</p>
                <ul class="text-sm text-stone-200 space-y-1">
                    @foreach ($pending as $p)
                        <li>
                            <span class="text-amber-300 font-medium">{{ $p->player?->character_name ?? '?' }}:</span>
                            <span class="text-stone-300">{{ preg_replace('/^\[[^\]]+\]:\s*/', '', $p->content) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Input de acción + resolver --}}
        <form id="chat-form" action="{{ route('games.act', ['game' => $game->code]) }}" method="POST" class="flex gap-2 mb-2">
            @csrf
            <input id="action-input" type="text" name="action" required maxlength="2000" autofocus
                placeholder="¿Qué hace {{ $currentPlayer->character_name }}? (ej. 'Inspecciono la mesa con cuidado')"
                autocomplete="off"
                class="flex-1 rounded-md bg-stone-950 border border-stone-700 px-3 py-2 text-stone-100 focus:border-amber-500 focus:outline-none">
            <button class="px-4 py-2 rounded-md bg-amber-700 hover:bg-amber-600 text-stone-50 font-medium">Añadir acción</button>
        </form>

        <button id="resolve-btn"
                data-stream-url="{{ route('games.resolve', ['game' => $game->code]) }}"
                class="w-full px-4 py-2 rounded-md bg-emerald-700 hover:bg-emerald-600 text-stone-50 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                {{ $pending->isEmpty() ? 'disabled' : '' }}>
            🎲 Resolver ronda {{ $pending->isEmpty() ? '(sin acciones pendientes)' : '' }}
        </button>
    </section>

    {{-- Sidebar --}}
    <aside class="space-y-4">
        <div class="rounded-lg border border-stone-800 bg-stone-900/60 p-4">
            <h3 class="font-serif text-amber-300 mb-2">📜 Invitación</h3>
            <p class="text-sm text-stone-300">Comparte con tus amigos:</p>
            <div class="mt-2 space-y-1 text-sm">
                <div><span class="text-stone-500">Código:</span> <code class="text-amber-300">{{ $game->code }}</code></div>
                <div><span class="text-stone-500">PIN:</span> <code class="text-amber-300">{{ $game->pin }}</code></div>
            </div>
        </div>

        <div class="rounded-lg border border-stone-800 bg-stone-900/60 p-4">
            <h3 class="font-serif text-amber-300 mb-3">🛡️ Party ({{ $game->players->count() }})</h3>
            <ul class="space-y-3 text-sm">
                @foreach ($game->players as $p)
                    <li class="border-l-2 pl-3 {{ $p->id === $currentPlayer->id ? 'border-amber-400' : 'border-stone-700' }}">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-amber-200">{{ $p->character_name }}</span>
                            @if ($p->id === $currentPlayer->id)
                                <span class="text-[10px] uppercase tracking-wide bg-amber-700/30 text-amber-300 px-1.5 py-0.5 rounded">tú</span>
                            @elseif ($p->is_creator)
                                <span class="text-[10px] text-stone-500">creador</span>
                            @endif
                        </div>
                        <p class="text-xs text-stone-400">{{ $p->name }} · {{ $p->character_class ?: 'Aventurero' }}</p>
                        <p class="text-xs mt-1">
                            <span class="{{ $p->hp < $p->hp_max / 2 ? 'text-red-400' : 'text-emerald-300' }}">HP {{ $p->hp }}/{{ $p->hp_max }}</span>
                            · <span class="text-amber-300">{{ $p->gold }} oro</span>
                            · Nv {{ $p->level }}
                        </p>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="rounded-lg border border-stone-800 bg-stone-900/60 p-4">
            <h3 class="font-serif text-amber-300 mb-2">📍 Ubicación</h3>
            <p class="text-sm text-stone-200">{{ $game->location ?? '—' }}</p>
        </div>

        <div class="rounded-lg border border-stone-800 bg-stone-900/60 p-4">
            <h3 class="font-serif text-amber-300 mb-2">🎒 Tu inventario</h3>
            <ul class="text-sm text-stone-200 list-disc list-inside space-y-1">
                @foreach ($currentPlayer->inventory_list as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>

        @if ($game->world_notes)
            <div class="rounded-lg border border-stone-800 bg-stone-900/60 p-4">
                <h3 class="font-serif text-amber-300 mb-2">📜 Notas del mundo</h3>
                <p class="text-sm text-stone-300 leading-relaxed">{{ $game->world_notes }}</p>
            </div>
        @endif
    </aside>
</div>

<script>
(function () {
    // ---- Copiar invitación al portapapeles ----
    const copyBtn = document.getElementById('copy-invite');
    if (copyBtn) {
        copyBtn.addEventListener('click', () => {
            const text = `Únete a mi partida de RPG:\nCódigo: ${copyBtn.dataset.code}\nPIN: ${copyBtn.dataset.pin}`;
            navigator.clipboard.writeText(text).then(() => {
                copyBtn.textContent = '✅ Copiado';
                setTimeout(() => { copyBtn.textContent = '📋 Copiar invitación'; }, 2000);
            });
        });
    }

    // ---- Enviar acción (form normal, luego recarga) ----
    const form = document.getElementById('chat-form');
    form.addEventListener('submit', () => {
        const btn = form.querySelector('button');
        btn.disabled = true;
        btn.textContent = 'Enviando…';
    });

    // ---- Resolver ronda (SSE streaming) ----
    const resolveBtn = document.getElementById('resolve-btn');
    if (!resolveBtn) return;
    const streamUrl = resolveBtn.dataset.streamUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const messages = document.getElementById('chat-messages');

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function scrollToBottom() {
        messages.scrollTop = messages.scrollHeight;
    }

    resolveBtn.addEventListener('click', async () => {
        resolveBtn.disabled = true;
        const original = resolveBtn.textContent;
        resolveBtn.textContent = '🎲 El GameMaster está pensando…';

        // Burbuja placeholder del GM
        const wrap = document.createElement('div');
        wrap.className = 'flex justify-start';
        const inner = document.createElement('div');
        inner.className = 'max-w-[85%] rounded-lg bg-stone-800/80 border border-amber-600 px-4 py-2 shadow';
        const label = document.createElement('p');
        label.className = 'text-xs text-amber-300/80 mb-1';
        label.textContent = '🎲 GameMaster (resolviendo ronda…)';
        const contentDiv = document.createElement('div');
        contentDiv.className = 'whitespace-pre-wrap leading-relaxed';
        inner.appendChild(label);
        inner.appendChild(contentDiv);
        wrap.appendChild(inner);
        messages.appendChild(wrap);
        scrollToBottom();

        try {
            const response = await fetch(streamUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'text/event-stream',
                },
            });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            if (!response.body) throw new Error('Tu navegador no soporta streaming.');

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                buffer += decoder.decode(value, { stream: true });
                let idx;
                while ((idx = buffer.indexOf('\n\n')) !== -1) {
                    const eventBlock = buffer.substring(0, idx);
                    buffer = buffer.substring(idx + 2);
                    const line = eventBlock.split('\n').find((l) => l.startsWith('data: '));
                    if (!line) continue;
                    let data;
                    try { data = JSON.parse(line.substring(6)); } catch { continue; }
                    if (data.type === 'delta') {
                        contentDiv.textContent += data.content;
                        scrollToBottom();
                    } else if (data.type === 'error') {
                        contentDiv.innerHTML = '<span class="text-red-400">⚠ ' + escapeHtml(data.message) + '</span>';
                    }
                }
            }
            window.location.reload();
        } catch (err) {
            contentDiv.innerHTML = '<span class="text-red-400">⚠ ' + escapeHtml(err.message) + '</span>';
            resolveBtn.disabled = false;
            resolveBtn.textContent = original;
        }
    });
})();
</script>
@endsection
