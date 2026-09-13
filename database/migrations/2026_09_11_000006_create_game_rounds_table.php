<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_rounds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('round_number')->unique();
            $table->string('status')->default('scheduled'); // scheduled|betting|running|crashed|settled|cancelled
            $table->string('server_seed')->nullable(); // revealed only after round settles
            $table->string('server_seed_hash'); // published before betting opens
            $table->string('client_seed');
            $table->unsignedBigInteger('nonce');
            $table->decimal('house_edge', 5, 4)->default(0.0300);
            $table->decimal('crash_multiplier', 10, 2)->nullable(); // generated once at creation, revealed at settlement
            $table->decimal('max_multiplier_cap', 10, 2)->default(5000.00);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('betting_closes_at')->nullable();
            $table->timestamp('crashed_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_rounds');
    }
};
