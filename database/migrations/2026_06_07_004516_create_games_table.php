<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('character_name');
            $table->string('character_class')->nullable();
            $table->integer('hp')->default(20);
            $table->integer('hp_max')->default(20);
            $table->integer('gold')->default(0);
            $table->integer('level')->default(1);
            $table->integer('experience')->default(0);
            $table->text('location')->nullable();
            $table->text('inventory')->nullable(); // JSON serializado
            $table->text('status_notes')->nullable(); // Estado del mundo (resumen)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
