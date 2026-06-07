<?php

use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GameController::class, 'index'])->name('games.index');
Route::get('/games/create', [GameController::class, 'create'])->name('games.create');
Route::post('/games', [GameController::class, 'store'])->name('games.store');
Route::get('/games/{game}', [GameController::class, 'show'])->name('games.show');
Route::post('/games/{game}/act', [GameController::class, 'act'])->name('games.act');
Route::post('/games/{game}/act/stream', [GameController::class, 'stream'])->name('games.act.stream');
Route::delete('/games/{game}', [GameController::class, 'destroy'])->name('games.destroy');

Route::get('/prompt', [GameController::class, 'promptEdit'])->name('games.prompt.edit');
Route::put('/prompt', [GameController::class, 'promptUpdate'])->name('games.prompt.update');
