<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique(); // idempotency key, e.g. AVIATOR-BET-01J...
            $table->string('type'); // bet_debit | game_win | topup | withdrawal | refund | adjustment | withholding_tax
            $table->decimal('amount', 14, 2); // positive = credit, negative = debit
            $table->decimal('balance_before', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('status')->default('completed'); // pending | completed | failed | reversed
            $table->string('related_type')->nullable(); // polymorphic: AviatorBet, Topup, etc.
            $table->unsignedBigInteger('related_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
