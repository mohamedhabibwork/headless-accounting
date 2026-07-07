<?php

declare(strict_types=1);

namespace Headless\Accounting\Console;

use Headless\Accounting\Actions\Inventory\QuarantineExpiredStock;
use Headless\Accounting\Actions\Inventory\ReleaseExpiredReservation;
use Illuminate\Console\Command;

/**
 * InventorySweepCommand — nightly (or on-demand) inventory hygiene
 * sweep. Both steps default to on; pass `--no-release-reservations` or
 * `--no-quarantine-expired` to disable individually.
 */
final class InventorySweepCommand extends Command
{
    protected $signature = 'ha:inventory:sweep
        {--release-reservations : release expired stock reservations}
        {--quarantine-expired : quarantine expired batches}';

    protected $description = 'Sweep reservations and batches.';

    public function handle(
        ReleaseExpiredReservation $release,
        QuarantineExpiredStock $quarantine,
    ): int {
        $releaseReservations = (bool) ($this->option('release-reservations') ?? true);
        $quarantineExpired = (bool) ($this->option('quarantine-expired') ?? true);

        if ($releaseReservations) {
            $count = $release->execute();
            $this->info("Released {$count} expired reservation(s).");
        } else {
            $this->line('Skipping reservation release.');
        }

        if ($quarantineExpired) {
            $count = $quarantine->execute();
            $this->info("Quarantined {$count} expired batch(es).");
        } else {
            $this->line('Skipping batch quarantine.');
        }

        return self::SUCCESS;
    }
}
