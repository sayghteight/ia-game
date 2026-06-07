<?php

use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GameController::class, 'index'])->name('games.index');
Route::get('/games/create', [GameController::class, 'create'])->name('games.create');
Route::post('/games', [GameController::class, 'store'])->name('games.store');
Route::get('/join', [GameController::class, 'joinForm'])->name('games.join.form');
Route::post('/join', [GameController::class, 'join'])->name('games.join');

// A partir de aquí, las URLs se identifican por `code` en vez de por id
Route::get('/games/{game:code}', [GameController::class, 'show'])->name('games.show');
Route::post('/games/{game:code}/act', [GameController::class, 'act'])->name('games.act');
Route::post('/games/{game:code}/resolve', [GameController::class, 'resolve'])->name('games.resolve');
Route::post('/games/{game:code}/act/stream', [GameController::class, 'stream'])->name('games.act.stream');
Route::delete('/games/{game:code}', [GameController::class, 'destroy'])->name('games.destroy');

Route::get('/prompt', [GameController::class, 'promptEdit'])->name('games.prompt.edit');
Route::put('/prompt', [GameController::class, 'promptUpdate'])->name('games.prompt.update');
