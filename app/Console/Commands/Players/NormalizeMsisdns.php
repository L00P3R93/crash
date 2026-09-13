<?php

namespace App\Console\Commands\Players;

use App\Models\Player;
use App\Support\MsisdnNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off cleanup for msisdns stored before MsisdnNormalizer existed — e.g.
 * "+254712345678" saved verbatim from Africa's Talking instead of the
 * canonical "254712345678". Safe to re-run: a player already normalized is
 * a no-op.
 *
 * Deliberately does not auto-merge a real collision (two different player
 * rows whose msisdn normalizes to the same value — the same person with a
 * wallet under each). That means picking a surviving player id and
 * re-pointing their wallet, bets, topups, and ussd_sessions rows by hand —
 * financial data no automated script should silently rewrite. This command
 * only ever reports those for manual review.
 */
class NormalizeMsisdns extends Command
{
    protected $signature = 'players:normalize-msisdns {--apply : Write the safe renames (no collision). Without this, only reports what would happen.}';

    protected $description = 'Rewrites players.msisdn to the canonical 254XXXXXXXXX digits-only form and reports (without merging) any players that collide once normalized.';

    public function handle(): int
    {
        $players = Player::query()->select(['id', 'msisdn'])->orderBy('id')->get();

        $idsByNormalized = [];
        $renames = [];

        foreach ($players as $player) {
            $normalized = MsisdnNormalizer::normalize($player->msisdn);
            $idsByNormalized[$normalized][] = $player->id;

            if ($normalized !== $player->msisdn) {
                $renames[$player->id] = $normalized;
            }
        }

        $collisions = array_filter($idsByNormalized, fn (array $ids) => count($ids) > 1);
        $collidingIds = $collisions === [] ? [] : array_merge(...array_values($collisions));

        if ($collisions !== []) {
            $this->warn(count($collisions).' msisdn(s) collide once normalized — these need manual review and will NOT be changed:');

            foreach ($collisions as $normalized => $ids) {
                $this->line("  {$normalized} <- player ids ".implode(', ', $ids));
            }

            $this->newLine();
        }

        $safeRenames = array_diff_key($renames, array_flip($collidingIds));

        if ($safeRenames === []) {
            $this->info('No safe renames needed.');

            return self::SUCCESS;
        }

        $this->info(count($safeRenames).' player(s) need reformatting only (no collision):');

        foreach ($safeRenames as $id => $normalized) {
            $original = $players->firstWhere('id', $id)->msisdn;
            $this->line("  #{$id}: {$original} -> {$normalized}");
        }

        if (! $this->option('apply')) {
            $this->newLine();
            $this->comment('Dry run — re-run with --apply to write these changes.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($safeRenames) {
            foreach ($safeRenames as $id => $normalized) {
                Player::query()->whereKey($id)->update(['msisdn' => $normalized]);
            }
        });

        $this->newLine();
        $this->info(count($safeRenames).' player(s) updated.');

        return self::SUCCESS;
    }
}
