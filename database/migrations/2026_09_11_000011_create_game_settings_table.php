<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('house_edge', 5, 4)->nullable();
            $table->decimal('min_stake', 14, 2)->nullable();
            $table->decimal('max_stake', 14, 2)->nullable();
            $table->decimal('max_multiplier', 10, 2)->nullable();
            $table->unsignedInteger('betting_window_seconds')->nullable();
            $table->decimal('acceleration_k', 6, 4)->nullable();
            $table->decimal('winnings_tax_rate', 5, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_settings');
    }
};
