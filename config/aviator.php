<?php

return [

    'house_edge' => (float) env('AVIATOR_HOUSE_EDGE', 0.03),

    'min_stake' => (float) env('AVIATOR_MIN_STAKE', 10),

    'max_stake' => (float) env('AVIATOR_MAX_STAKE', 2000),

    'max_multiplier' => (float) env('AVIATOR_MAX_MULTIPLIER', 5000),

    'betting_window_seconds' => (int) env('AVIATOR_BETTING_WINDOW_SECONDS', 25),

    // How long a crashed/settled round stays on screen — crash reveal, win/
    // loss banner — before the next round's betting window opens and resets
    // the view. Without this pause the reset happens the instant the game
    // loop moves on, which reads as the result flashing and disappearing.
    'post_round_pause_seconds' => (int) env('AVIATOR_POST_ROUND_PAUSE_SECONDS', 15),

    // M(t) = e^(k*t) — see architecture doc §22
    'acceleration_k' => (float) env('AVIATOR_ACCELERATION_K', 0.25),

    // Withheld from gross winnings before crediting the wallet; confirm the
    // exact current rate/threshold under Kenyan law before launch.
    'winnings_tax_rate' => (float) env('AVIATOR_WINNINGS_TAX_RATE', 0.20),

    // Local/dev only: whether App\Listeners\PlaceBotBets makes the seeded
    // bot accounts (see database/seeders/BotPlayerSeeder) actually place
    // bets every round, so the live "active players" table has activity to
    // show without needing real players. Off by default everywhere.
    'bots_enabled' => (bool) env('AVIATOR_BOTS_ENABLED', false),

    // How many of the ~100 seeded bots play a given round.
    'bots_min_players' => (int) env('AVIATOR_BOTS_MIN_PLAYERS', 20),
    'bots_max_players' => (int) env('AVIATOR_BOTS_MAX_PLAYERS', 40),

    // Each bot's auto_cashout target is a random value in this range — kept
    // above PlaceBetRequest's 1.01 floor even though bots bypass that
    // FormRequest by calling BetService directly, so a target below the
    // crash multiplier still resolves as a real, sane win.
    'bots_min_auto_cashout' => (float) env('AVIATOR_BOTS_MIN_AUTO_CASHOUT', 1.10),
    'bots_max_auto_cashout' => (float) env('AVIATOR_BOTS_MAX_AUTO_CASHOUT', 10.00),

];
