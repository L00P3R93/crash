<?php

namespace App\Domain\Aviator;

use Illuminate\Support\Str;

class ProvablyFairService
{
    public function __construct(
        private readonly CrashPointGenerator $generator = new CrashPointGenerator
    ) {}

    /**
     * A fresh, unpredictable server seed. Kept secret until the round settles.
     */
    public function generateServerSeed(): string
    {
        return Str::random(40);
    }

    /**
     * A fresh client seed. Published immediately since it carries no information
     * about the crash point without the server seed.
     */
    public function generateClientSeed(): string
    {
        return Str::random(16);
    }

    /**
     * Committed before betting opens: proves the server seed can't be changed
     * after the fact once revealed.
     */
    public function hashServerSeed(string $serverSeed): string
    {
        return hash('sha256', $serverSeed);
    }

    public function crashPointFor(string $serverSeed, string $clientSeed, int $nonce, float $houseEdge): float
    {
        return $this->generator->generate($serverSeed, $clientSeed, $nonce, $houseEdge);
    }

    /**
     * Reproduce a settled round's result from its revealed values, for player
     * or staff verification.
     */
    public function verify(
        string $serverSeed,
        string $expectedServerSeedHash,
        string $clientSeed,
        int $nonce,
        float $houseEdge,
        float $expectedCrashMultiplier
    ): bool {
        if ($this->hashServerSeed($serverSeed) !== $expectedServerSeedHash) {
            return false;
        }

        $recomputed = $this->crashPointFor($serverSeed, $clientSeed, $nonce, $houseEdge);

        return abs($recomputed - $expectedCrashMultiplier) < 0.0001;
    }
}
