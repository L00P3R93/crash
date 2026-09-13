<?php

namespace App\Domain\Aviator;

class CrashPointGenerator
{
    /**
     * HMAC-SHA256 provably-fair crash point construction.
     *
     * H = HMAC-SHA256(serverSeed, clientSeed:nonce)
     * U = first 52 bits of H, normalized to [0, 1)
     * M = (1 - houseEdge) / (1 - U), floored to 2 decimals, never below 1.00
     */
    public function generate(
        string $serverSeed,
        string $clientSeed,
        int $nonce,
        float $houseEdge = 0.03
    ): float {
        $message = "{$clientSeed}:{$nonce}";

        $hash = hash_hmac('sha256', $message, $serverSeed);

        // First 52 bits
        $hex = substr($hash, 0, 13);

        $value = hexdec($hex);

        $max = 2 ** 52;

        $u = $value / $max;

        $multiplier = (1 - $houseEdge) / (1 - $u);

        return max(1.00, floor($multiplier * 100) / 100);
    }
}
