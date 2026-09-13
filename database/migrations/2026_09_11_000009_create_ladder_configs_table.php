<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ladder_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('default');
            $table->decimal('safe_ratio', 5, 2)->default(1.10); // "Continue" step multiplier vs current rung
            $table->decimal('risky_ratio', 5, 2)->default(2.00); // "Rocket" step multiplier vs current rung
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ladder_configs');
    }
};
