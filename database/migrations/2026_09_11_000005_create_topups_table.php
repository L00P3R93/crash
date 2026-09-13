<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('provider'); // mpesa | airtel_money | card | manual
            $table->string('provider_reference')->nullable(); // e.g. M-Pesa CheckoutRequestID / MpesaReceiptNumber
            $table->string('status')->default('pending'); // pending | completed | failed | cancelled
            $table->foreignId('wallet_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('provider_reference');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topups');
    }
};
