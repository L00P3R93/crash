<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ussd_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique(); // Africa's Talking sessionId header
            $table->string('msisdn'); // Africa's Talking phoneNumber param
            $table->string('service_code'); // Africa's Talking serviceCode param, e.g. *384*1234#
            $table->string('network_code')->nullable();
            $table->foreignId('player_id')->nullable()->constrained()->nullOnDelete();
            $table->string('current_screen')->default('main_menu'); // e.g. bet_entry, ladder_decision, change_bet
            $table->json('state')->nullable(); // in-progress bet_id, chosen stake, pending rung, text history
            $table->string('status')->default('active'); // active | completed | expired | cancelled
            $table->string('last_input')->nullable(); // raw AT `text` param on the most recent request
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('msisdn');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ussd_sessions');
    }
};
