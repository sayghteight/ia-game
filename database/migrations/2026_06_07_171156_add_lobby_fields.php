<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->boolean('is_ready')->default(false)->after('is_creator');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->string('status', 16)->default('lobby')->after('title'); // lobby | playing | finished
            $table->timestamp('started_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['status', 'started_at']);
        });
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('is_ready');
        });
    }
};
