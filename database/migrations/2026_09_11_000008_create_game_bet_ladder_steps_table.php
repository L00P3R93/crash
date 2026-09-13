<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_bet_ladder_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bet_id')->constrained('game_bets')->cascadeOnDelete();
            $table->unsignedInteger('step_number'); // 1, 2, 3... sequence within this bet
            $table->decimal('from_multiplier', 10, 2);
            $table->decimal('to_multiplier', 10, 2)->nullable(); // null when choice = cash_out
            $table->string('choice'); // cash_out | continue_safe | rocket_risky
            $table->decimal('probability_shown', 6, 4)->nullable();
            $table->boolean('survived')->nullable(); // null until resolved against crash_multiplier
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['bet_id', 'step_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_bet_ladder_steps');
    }
};
