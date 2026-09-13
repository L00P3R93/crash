<?php

namespace App\Domain\Ussd;

use App\Domain\Ussd\Contracts\UssdScreen;
use App\Domain\Ussd\Screens\AccountScreen;
use App\Domain\Ussd\Screens\BetEntryScreen;
use App\Domain\Ussd\Screens\BetHistoryScreen;
use App\Domain\Ussd\Screens\ChangeBetScreen;
use App\Domain\Ussd\Screens\FairnessVerifyScreen;
use App\Domain\Ussd\Screens\HelpScreen;
use App\Domain\Ussd\Screens\LadderDecisionScreen;
use App\Domain\Ussd\Screens\MainMenuScreen;
use App\Domain\Ussd\Screens\SetPinScreen;
use App\Domain\Ussd\Screens\TopUpScreen;
use App\Domain\Ussd\Screens\VerifyPinScreen;
use App\Domain\Ussd\Screens\WithdrawScreen;
use App\Models\UssdSession;

class UssdMenuRenderer
{
    /**
     * @var array<string, class-string<UssdScreen>>
     */
    private const SCREENS = [
        'main_menu' => MainMenuScreen::class,
        'set_pin' => SetPinScreen::class,
        'verify_pin' => VerifyPinScreen::class,
        'bet_entry' => BetEntryScreen::class,
        'change_bet' => ChangeBetScreen::class,
        'ladder_decision' => LadderDecisionScreen::class,
        'account' => AccountScreen::class,
        'bet_history' => BetHistoryScreen::class,
        'topup' => TopUpScreen::class,
        'withdraw' => WithdrawScreen::class,
        'help' => HelpScreen::class,
        'fairness' => FairnessVerifyScreen::class,
    ];

    public function resolveScreen(UssdSession $session): UssdScreen
    {
        return app(self::SCREENS[$session->current_screen] ?? MainMenuScreen::class);
    }

    /**
     * Transitions the session to $screenKey and immediately renders that
     * screen's response — a menu choice and the next prompt happen in the
     * same request/response round trip, there's no separate "navigate" step.
     */
    public function render(UssdSession $session, string $screenKey, string $input = ''): UssdResponse
    {
        $session->current_screen = $screenKey;

        $class = self::SCREENS[$screenKey] ?? MainMenuScreen::class;

        return app($class)->handle($session, $input);
    }
}
