<?php

declare(strict_types=1);

namespace Headless\Accounting\Contracts;

use Headless\Accounting\Models\TaxClass;

/**
 * Anything that can be taxed.
 *
 * Taxable returns the identifier of the {@see TaxClass}
 * associated with the entity (a Product, a Variant, a Shipment line, …).
 */
interface Taxable
{
    /** The tax class identifier, used by the tax engine. */
    public function taxClassId(): ?int;

    /** Free-form context for tax resolution (e.g. digital goods flag). */
    public function taxContext(): array;
}
