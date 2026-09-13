<?php

namespace App\Support;

/**
 * Shared msisdn masking for anything that shows a player's phone number to
 * other players — the USSD "Top Pilots" leaderboard and the web live bet
 * feed both need the same convention, not two copies of it.
 */
class MsisdnMasker
{
    public static function mask(string $msisdn): string
    {
        if (strlen($msisdn) < 9) {
            return $msisdn;
        }

        return substr($msisdn, 0, 6).'***'.substr($msisdn, -3);
    }
}
