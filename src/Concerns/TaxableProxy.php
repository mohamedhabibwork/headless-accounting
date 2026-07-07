<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\Contracts\Taxable;
use Illuminate\Database\Eloquent\Model;

/**
 * TaxableProxy — for hosts that already have a `Taxable` model
 * living somewhere else (e.g. `App\Catalog\Service`) but want to
 * expose a `proxyTaxable()` accessor returning a package-aware
 * {@see Taxable}.
 *
 * The package's tax engine accepts any object implementing
 * {@see Taxable}, so hosts can hand-roll a tiny proxy record — this
 * trait produces one on demand without an extra DB column.
 *
 * @mixin Model
 */
trait TaxableProxy
{
    /**
     * Builds a proxy at runtime. The returned object implements
     * {@see Taxable} purely from data stored on the host model.
     */
    public function proxyTaxable(): Taxable
    {
        $attributes = $this->getAttributes();

        return new class($attributes, $this->proxyTaxClassColumn(), $this->proxyTaxContextColumn()) implements Taxable
        {
            public function __construct(
                /** @var array<string,mixed> */
                private array $attributes,
                private string $taxClassColumn,
                private string $taxContextColumn,
            ) {}

            public function taxClassId(): ?int
            {
                $value = $this->attributes[$this->taxClassColumn] ?? null;

                return $value !== null ? (int) $value : null;
            }

            public function taxContext(): array
            {
                $value = $this->attributes[$this->taxContextColumn] ?? [];

                return is_array($value) ? $value : [];
            }
        };
    }

    public function proxyTaxClassColumn(): string
    {
        return property_exists($this, 'proxyTaxClassColumn')
            ? $this->proxyTaxClassColumn
            : 'tax_class_id';
    }

    public function proxyTaxContextColumn(): string
    {
        return property_exists($this, 'proxyTaxContextColumn')
            ? $this->proxyTaxContextColumn
            : 'tax_context';
    }
}
