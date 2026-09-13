<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_settings', function (Blueprint $table) {
            $table->unsignedInteger('post_round_pause_seconds')->nullable()->after('betting_window_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('game_settings', function (Blueprint $table) {
            $table->dropColumn('post_round_pause_seconds');
        });
    }
};
