<?php

namespace App\Domain\Aviator\Exceptions;

/**
 * Separates a domain exception's internal `getMessage()` (written for logs
 * — names internal ids like round/player/bet) from the copy actually meant
 * to reach a player, so the API layer never has to guess which one is safe
 * to expose. See bootstrap/app.php's exception rendering.
 */
interface HasPlayerMessage
{
    public function playerMessage(): string;
}
