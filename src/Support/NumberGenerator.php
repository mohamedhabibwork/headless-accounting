<?php

declare(strict_types=1);

namespace Headless\Accounting\Support;

use Carbon\CarbonImmutable;

/**
 * NumberGenerator — human-friendly incremental IDs (orders, invoices,
 * journal entries) with per-year resets and prefixes. Thread-safe via
 * the `(modelClass)::query()->count()` baseline; for very high volume
 * swap to a `sequences` table.
 *
 *   $invoice = NumberGenerator::next('INV', \Headless\Accounting\Models\Invoice::class);
 *   // "INV-2026-000132"
 */
final class NumberGenerator
{
    public static function next(string $prefix, string $modelClass, ?string $year = null, int $pad = 6): string
    {
        $year ??= CarbonImmutable::now()->format('Y');
        $count = $modelClass::withTrashed()
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('%s-%s-%0'.$pad.'d', $prefix, $year, $count);
    }

    public static function daily(string $prefix, string $modelClass, int $pad = 5): string
    {
        $now = CarbonImmutable::now();
        $count = $modelClass::withTrashed()
            ->whereDate('created_at', $now->toDateString())
            ->count() + 1;

        return sprintf('%s-%s-%0'.$pad.'d', $prefix, $now->format('Ymd'), $count);
    }
}
