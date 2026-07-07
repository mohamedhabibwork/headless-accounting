<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\Contracts\Bankable;
use Headless\Accounting\Models\BankAccount;
use Headless\Accounting\Support\Config;
use Illuminate\Database\Eloquent\Model;

/**
 * BankableProxy — drop-in trait for host-side models (often the
 * application's `Vendor` table or a third-party bank account
 * representation) that need to plug into the package's payment side
 * without migrating the data.
 *
 * Hosts that prefer not to import the package's `BankAccount` rows
 * can implement {@see Bankable} on their own model and pair it
 * with this trait to attach a snapshot view of the data.
 *
 * @mixin Model
 */
trait BankableProxy
{
    /**
     * Returns a {@see BankAccount}-shaped object implementing
     * {@see Bankable} but backed by the host's own data.
     */
    public function bankAccountProxy(): Bankable
    {
        $host = $this;
        $attributes = $host->getAttributes();

        return new class($attributes, $this->proxyIbanColumn(), $this->proxyBicColumn(), $this->proxyCurrencyColumn(), $this->proxyDefaultColumn()) implements Bankable
        {
            public function __construct(
                /** @var array<string,mixed> */
                private array $attributes,
                private string $ibanColumn,
                private string $bicColumn,
                private string $currencyColumn,
                private string $defaultColumn,
            ) {}

            public function iban(): ?string
            {
                $value = $this->attributes[$this->ibanColumn] ?? null;

                return $value !== null ? (string) $value : null;
            }

            public function bic(): ?string
            {
                $value = $this->attributes[$this->bicColumn] ?? null;

                return $value !== null ? (string) $value : null;
            }

            public function currency(): string
            {
                $value = $this->attributes[$this->currencyColumn] ?? null;

                return (string) ($value ?: Config::string(
                    'headless-accounting.currency.default'
                ));
            }

            public function isDefault(): bool
            {
                return (bool) ($this->attributes[$this->defaultColumn] ?? false);
            }
        };
    }

    public function proxyIbanColumn(): string
    {
        return property_exists($this, 'proxyIbanColumn')
            ? $this->proxyIbanColumn
            : 'iban';
    }

    public function proxyBicColumn(): string
    {
        return property_exists($this, 'proxyBicColumn')
            ? $this->proxyBicColumn
            : 'bic';
    }

    public function proxyCurrencyColumn(): string
    {
        return property_exists($this, 'proxyCurrencyColumn')
            ? $this->proxyCurrencyColumn
            : 'currency';
    }

    public function proxyDefaultColumn(): string
    {
        return property_exists($this, 'proxyDefaultColumn')
            ? $this->proxyDefaultColumn
            : 'is_default';
    }
}
