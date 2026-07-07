<?php

declare(strict_types=1);

namespace Headless\Accounting\Contracts;

use Headless\Accounting\Support\NumberGenerator;

/**
 * NumberSeries — contract that lets host projects plug a custom
 * "next-number" generator (Lucid, prefix-aware, with reset policies)
 * behind {@see NumberGenerator}.
 *
 * Implementations are responsible for atomicity under concurrent
 * requests (e.g. via a `sequences` table with `lockForUpdate`).
 *
 * Bind a concrete implementation through the service provider:
 *
 *     $this->app->singleton(NumberSeries::class, MySequenceService::class);
 *
 * Or replace the contract shipped with this package via:
 *
 *     'headless-accounting.number_series' => MySequence::class,
 */
interface NumberSeries
{
    /**
     * Build the next identifier for a given document type.
     *
     * @param  string  $type  Document slug, e.g. 'order', 'invoice', 'journal'.
     * @param  string  $model  The Eloquent model class to count against.
     * @param  array<string,mixed>  $options  Free-form overrides (year, prefix, padding, …).
     */
    public function next(string $type, string $model, array $options = []): string;

    /**
     * Build the next *daily* identifier.
     *
     * @param  array<string,mixed>  $options
     */
    public function nextDaily(string $type, string $model, array $options = []): string;

    /**
     * Validate that an identifier conforms to this series' format.
     * Used by import paths to detect tampering.
     */
    public function matchesFormat(string $type, string $candidate): bool;
}
