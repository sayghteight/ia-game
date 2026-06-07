<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // ---- 1) Limpiar la tabla games: ahora solo guarda el MUNDO ----
        // Eliminamos las columnas de personaje (ahora viven en players).
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn([
                'character_name', 'character_class', 'hp', 'hp_max',
                'gold', 'level', 'experience', 'location', 'inventory',
                'status_notes', 'is_active',
            ]);
        });

        // Añadimos código (compartible), PIN (privado) y ronda actual.
        Schema::table('games', function (Blueprint $table) {
            $table->string('code', 8)->nullable()->unique()->after('id');
            $table->string('pin', 4)->nullable()->after('code');
            $table->unsignedInteger('current_round')->default(1)->after('pin');
            $table->text('location')->nullable()->after('current_round');
            $table->text('world_notes')->nullable()->after('location');
        });

        // ---- 2) Crear la tabla players ----
        // Cada jugador tiene su propio personaje + un session_token (cookie).
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);                    // nombre en el chat
            $table->string('character_name', 60);          // nombre del PJ
            $table->string('character_class', 40)->nullable();
            $table->integer('hp')->default(20);
            $table->integer('hp_max')->default(20);
            $table->integer('gold')->default(10);
            $table->integer('level')->default(1);
            $table->integer('xp')->default(0);
            $table->text('inventory')->nullable();
            $table->string('session_token', 64)->unique();
            $table->boolean('is_creator')->default(false);
            $table->timestamps();

            $table->index(['game_id', 'session_token']);
        });

        // ---- 3) Extender messages con player/round/status ----
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('player_id')->nullable()->after('game_id')->constrained('players')->nullOnDelete();
            $table->unsignedInteger('round')->default(1)->after('player_id');
            $table->string('status', 16)->default('resolved')->after('tokens_output'); // pending|resolved
            $table->index(['game_id', 'round', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['game_id', 'round', 'status']);
            $table->dropColumn(['player_id', 'round', 'status']);
        });

        Schema::dropIfExists('players');

        Schema::table('games', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'pin', 'current_round', 'location', 'world_notes']);
            $table->string('character_name');
            $table->string('character_class')->nullable();
            $table->integer('hp')->default(20);
            $table->integer('hp_max')->default(20);
            $table->integer('gold')->default(0);
            $table->integer('level')->default(1);
            $table->integer('experience')->default(0);
            $table->text('location')->nullable();
            $table->text('inventory')->nullable();
            $table->text('status_notes')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }
};
