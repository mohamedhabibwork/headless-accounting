<?php

declare(strict_types=1);

namespace Headless\Accounting\Inventory;

use Headless\Accounting\Models\SerialNumber;
use Headless\Accounting\Support\Config;
use Headless\Accounting\Tenancy\NumberSeries;
use Illuminate\Support\Facades\DB;

/**
 * SerialNumberGenerator — mints human-friendly serial identifiers for
 * unit-tracked inventory using the `number_prefixes.serial_number`
 * entry from the package config (defaults to `SN`).
 *
 * Counter rows live in {@see NumberSeries} (one row per
 * `(company_id, prefix)` pair), so minting is atomic under concurrent
 * requests via `lockForUpdate()` inside a database transaction.
 *
 *   $serial = $gen->next();                      // "SN-2026-000001"
 *   $serial = $gen->next(companyId: $co->id);    // per-company counter
 *
 *   $row = $gen->register($variant->id);        // mint + persist in one call
 *   $row = $gen->generateMany(5)->register($variant->id); // batch receipt
 */
final class SerialNumberGenerator
{
    public function __construct(private readonly SerialService $serials) {}

    /**
     * Mint the next serial identifier.
     *
     * The result follows the configured pattern `PREFIX-YYYY-NNNNNN`
     * (year segment is omitted when `reset_yearly` is false on the
     * underlying series).
     */
    public function next(?int $companyId = null): string
    {
        return $this->series($companyId)->next();
    }

    /**
     * Peek at the next counter value without incrementing it. Useful
     * for UI previews and dry-run reporting.
     */
    public function peek(?int $companyId = null): int
    {
        return $this->series($companyId)->peek();
    }

    /**
     * Mint a contiguous batch of serials (e.g. for a goods receipt of
     * N units). Each call shares a single locked transaction so the
     * returned identifiers are gap-free under concurrency.
     *
     * @return array<int, string>
     */
    public function generateMany(int $count, ?int $companyId = null): array
    {
        if ($count < 1) {
            return [];
        }

        $out = DB::transaction(function () use ($count, $companyId) {
            $series = $this->series($companyId, lock: true);
            $rows = [];
            for ($i = 0; $i < $count; $i++) {
                $rows[] = $series->next();
            }

            return $rows;
        });

        return $out;
    }

    /**
     * Mint a new serial and persist a {@see SerialNumber} row for the
     * given variant. Caller-supplied overrides take precedence over
     * defaults; pass `batch_id`, `location_id`, `bin_id`,
     * `manufacturing_date`, `warranty_expires_at` or `warranty_terms`
     * to enrich the registration.
     *
     * @param  array<string,mixed>  $overrides
     */
    public function register(int $variantId, array $overrides = [], ?int $companyId = null): SerialNumber
    {
        $serial = $this->next($companyId);

        return $this->serials->register(
            variantId: $variantId,
            serial: $serial,
            batchId: $overrides['batch_id'] ?? null,
            locationId: $overrides['location_id'] ?? null,
            binId: $overrides['bin_id'] ?? null,
            manufacturingDate: $overrides['manufacturing_date'] ?? null,
            warrantyExpiresAt: $overrides['warranty_expires_at'] ?? null,
            warrantyTerms: $overrides['warranty_terms'] ?? [],
        );
    }

    /**
     * Resolve the configured prefix (e.g. `SN`) for the serial-number
     * document type. Exposed so callers can build custom formats or
     * pre-validate imported identifiers.
     */
    public function prefix(): string
    {
        return Config::string('headless-accounting.number_prefixes.serial_number', 'SN');
    }

    /**
     * Resolve — and optionally row-lock — the {@see NumberSeries}
     * backing the serial-number counter. Uses `firstOrCreate` so the
     * row materialises on first use per `(company_id, prefix)` pair,
     * then refreshes so DB defaults (e.g. `next_number = 1`) are
     * visible to `peek()` on a fresh series.
     */
    private function series(?int $companyId, bool $lock = false): NumberSeries
    {
        $series = NumberSeries::for($companyId, $this->prefix());

        if ($lock) {
            return NumberSeries::query()
                ->whereKey($series->getKey())
                ->lockForUpdate()
                ->firstOrFail();
        }

        return $series->wasRecentlyCreated ? $series->refresh() : $series;
    }
}