<?php

namespace Tests\Feature\Api;

use App\Models\Player;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Deliberately stubbed until the web-triggered M-Pesa flow is built — see
 * App\Http\Controllers\Api\WalletController. These tests just lock in that
 * the routes exist, require auth, and fail loudly rather than silently.
 */
class WalletControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_topup_requires_authentication(): void
    {
        $this->postJson('/api/wallet/topup', ['amount' => 100])->assertUnauthorized();
    }

    public function test_topup_is_not_yet_available(): void
    {
        $player = Player::factory()->create();
        Wallet::factory()->create(['player_id' => $player->id]);
        Sanctum::actingAs($player);

        $this->postJson('/api/wallet/topup', ['amount' => 100])
            ->assertStatus(501);
    }

    public function test_withdraw_is_not_yet_available(): void
    {
        $player = Player::factory()->create();
        Wallet::factory()->create(['player_id' => $player->id]);
        Sanctum::actingAs($player);

        $this->postJson('/api/wallet/withdraw', ['amount' => 100])
            ->assertStatus(501);
    }
}
