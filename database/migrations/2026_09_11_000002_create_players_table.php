<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn')->unique();
            $table->string('name')->nullable();
            $table->string('pin_hash')->nullable();
            $table->timestamp('pin_set_at')->nullable();
            $table->string('status')->default('active'); // active | suspended | banned
            $table->string('registered_via')->nullable(); // ussd | web | api
            $table->decimal('daily_deposit_limit', 14, 2)->nullable();
            $table->decimal('daily_stake_limit', 14, 2)->nullable();
            $table->timestamp('self_excluded_until')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
