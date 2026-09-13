<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_bets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained('game_rounds')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('bet_reference')->unique(); // idempotency key
            $table->string('channel')->default('ussd'); // ussd | web | api | bot
            $table->decimal('stake', 14, 2);
            $table->decimal('current_rung', 10, 2)->default(1.00); // last checkpoint successfully reached
            $table->decimal('auto_cashout', 10, 2)->nullable(); // web/one-shot auto-cashout flows
            $table->decimal('cashout_multiplier', 10, 2)->nullable();
            $table->decimal('payout', 14, 2)->nullable();
            $table->string('status')->default('pending'); // pending|active|won|lost|cancelled
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('cashed_out_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_bets');
    }
};
