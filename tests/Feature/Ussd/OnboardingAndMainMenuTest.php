<?php

namespace Tests\Feature\Ussd;

use App\Models\Player;
use App\Models\UssdSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingAndMainMenuTest extends TestCase
{
    use InteractsWithUssd, RefreshDatabase;

    public function test_first_contact_creates_a_player_and_prompts_for_a_pin(): void
    {
        $response = $this->ussd('sess-1', '254712345678', '');

        $response->assertOk();
        $response->assertSee('CON SET YOUR PIN', false);
        $response->assertSee('Enter PIN:', false);

        $this->assertDatabaseHas('players', ['msisdn' => '254712345678']);
        $player = Player::query()->where('msisdn', '254712345678')->sole();
        $this->assertNotNull($player->wallet);
    }

    public function test_a_plus_prefixed_phone_number_is_stored_normalized(): void
    {
        $this->ussd('sess-plus', '+254712345678', '');

        $this->assertDatabaseHas('players', ['msisdn' => '254712345678']);
        $this->assertDatabaseMissing('players', ['msisdn' => '+254712345678']);
        $this->assertSame(1, Player::query()->count());
    }

    public function test_the_same_number_in_different_formats_resolves_to_one_player(): void
    {
        $this->ussd('sess-plus-a', '+254712345678', '');
        $this->ussd('sess-plus-b', '254712345678', '');

        $this->assertSame(1, Player::query()->where('msisdn', '254712345678')->count());
    }

    public function test_setting_and_confirming_a_pin_leads_to_the_main_menu(): void
    {
        $this->ussd('sess-2', '254712345678', '');
        $this->ussd('sess-2', '254712345678', '1234');
        $response = $this->ussd('sess-2', '254712345678', '1234*1234');

        $response->assertOk();
        $response->assertSee('Shinda Na Aviator', false);
        $response->assertSee('1: Play Aviator', false);

        $player = Player::query()->where('msisdn', '254712345678')->sole();
        $this->assertNotNull($player->pin_hash);
        $this->assertNotNull($player->pin_set_at);
    }

    public function test_mismatched_pin_confirmation_asks_to_retry(): void
    {
        $this->ussd('sess-3', '254712345678', '');
        $this->ussd('sess-3', '254712345678', '1234');
        $response = $this->ussd('sess-3', '254712345678', '1234*4321');

        $response->assertSee('PINs did not match', false);
    }

    public function test_a_returning_player_with_a_pin_goes_straight_to_the_main_menu(): void
    {
        Player::factory()->withPin('9999')->create(['msisdn' => '254799999999']);

        $response = $this->ussd('sess-4', '254799999999', '');

        $response->assertSee('Shinda Na Aviator', false);
    }

    public function test_the_same_session_id_resumes_rather_than_recreating(): void
    {
        Player::factory()->withPin('9999')->create(['msisdn' => '254798888888']);

        $this->ussd('sess-5', '254798888888', '');
        $this->ussd('sess-5', '254798888888', '4'); // -> help

        $this->assertSame(1, UssdSession::query()->where('session_id', 'sess-5')->count());
        $this->assertSame('help', UssdSession::query()->where('session_id', 'sess-5')->sole()->current_screen);
    }

    public function test_an_invalid_main_menu_choice_is_rejected(): void
    {
        Player::factory()->withPin('9999')->create(['msisdn' => '254797777777']);

        $response = $this->ussd('sess-6', '254797777777', '9');

        $response->assertSee('Invalid choice. Please try again.', false);
    }
}
