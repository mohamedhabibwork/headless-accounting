<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Tax;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Contracts\Taxable;
use Headless\Accounting\Models\Address;
use Headless\Accounting\Tax\TaxBreakdown;
use Headless\Accounting\Tax\TaxEngine;

/**
 * CalculateLineTax — convenience wrapper around {@see TaxEngine::resolve()}
 * that callers (controllers, cart previews) can invoke without a full
 * order context.
 */
final class CalculateLineTax extends Action
{
    public function __construct(private readonly TaxEngine $engine) {}

    protected function handle(
        Taxable $subject,
        int $subtotalMinor,
        string $currency,
        ?Address $shipTo = null,
        ?Address $billTo = null,
        array $context = [],
    ): TaxBreakdown {
        return $this->engine->resolve($subject, $subtotalMinor, $currency, $shipTo, $billTo, $context);
    }
}
