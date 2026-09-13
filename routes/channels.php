<?php

use App\Models\Player;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| The live round itself (multiplier ticks, crash, settlement) is public —
| anyone can watch a round without being logged in, same as USSD players
| can see the round is running without having placed a bet. Only a
| player's own bet/cashout confirmations are private.
|
*/

Broadcast::channel('aviator.player.{playerId}', function (Player $player, int $playerId) {
    return $player->id === $playerId;
});
