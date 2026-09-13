<?php

namespace Tests\Feature\Api;

use App\Models\Player;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_player_can_log_in_with_msisdn_and_pin(): void
    {
        $player = Player::factory()->withPin('4321')->create();
        Wallet::factory()->create(['player_id' => $player->id]);

        $response = $this->postJson('/api/auth/login', [
            'msisdn' => $player->msisdn,
            'pin' => '4321',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'player' => ['msisdn', 'name', 'balance']]);
    }

    public function test_a_player_can_log_in_with_an_unnormalized_msisdn(): void
    {
        $player = Player::factory()->withPin('4321')->create(['msisdn' => '254712345678']);
        Wallet::factory()->create(['player_id' => $player->id]);

        $response = $this->postJson('/api/auth/login', [
            'msisdn' => '+254712345678',
            'pin' => '4321',
        ]);

        $response->assertOk()->assertJsonPath('player.msisdn', '254712345678');
    }

    public function test_login_fails_with_the_wrong_pin(): void
    {
        $player = Player::factory()->withPin('4321')->create();

        $response = $this->postJson('/api/auth/login', [
            'msisdn' => $player->msisdn,
            'pin' => '0000',
        ]);

        $response->assertUnprocessable();
    }

    public function test_login_fails_for_a_player_who_never_set_a_pin(): void
    {
        $player = Player::factory()->create();

        $response = $this->postJson('/api/auth/login', [
            'msisdn' => $player->msisdn,
            'pin' => '1234',
        ]);

        $response->assertUnprocessable();
    }

    public function test_a_valid_token_can_reach_an_authenticated_endpoint(): void
    {
        $player = Player::factory()->withPin('4321')->create();
        Wallet::factory()->create(['player_id' => $player->id]);

        $token = $this->postJson('/api/auth/login', [
            'msisdn' => $player->msisdn,
            'pin' => '4321',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJson(['msisdn' => $player->msisdn]);
    }

    public function test_an_authenticated_endpoint_rejects_no_token(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }
}
