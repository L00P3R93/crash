<?php

namespace App\Domain\Aviator\Exceptions;

use RuntimeException;

class RoundClosedException extends RuntimeException implements HasPlayerMessage
{
    private string $playerMessage;

    public static function noOpenRound(): self
    {
        $e = new self('No round is currently open for betting.');
        $e->playerMessage = 'Betting isn\'t open right now — hang tight for the next round.';

        return $e;
    }

    public static function alreadyCrashed(int $roundId): self
    {
        // Internal message keeps the round id for logs/debugging; the
        // player never sees a raw database id (architecture doc-adjacent
        // gotcha: this can legitimately fire the instant a round crashes,
        // slightly before the player's own client-side animation catches up
        // — so the copy needs to read as normal gameplay, not an error).
        $e = new self("Round {$roundId} has already crashed.");
        $e->playerMessage = 'Too late — the round already crashed. Get ready for the next one!';

        return $e;
    }

    public function playerMessage(): string
    {
        return $this->playerMessage;
    }
}
