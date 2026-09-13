<?php

namespace App\Console\Commands\Aviator;

use App\Domain\Aviator\RoundScheduler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunGameLoop extends Command
{
    protected $signature = 'aviator:run-game-loop';

    protected $description = 'Long-running worker that continuously creates, runs, and settles Aviator rounds. Run under Supervisor, not the scheduler.';

    public function handle(RoundScheduler $scheduler): never
    {
        $this->info('Aviator game loop starting.');

        while (true) {
            Cache::put('aviator:game_loop:heartbeat', now()->toIso8601String(), now()->addMinutes(2));

            try {
                $round = $scheduler->runOnce();

                $this->info("Round {$round->round_number} settled at {$round->crash_multiplier}x.");
            } catch (Throwable $e) {
                Log::error('Aviator game loop iteration failed.', [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Avoid a tight crash loop if something is persistently wrong.
                sleep(3);
            }
        }
    }
}
