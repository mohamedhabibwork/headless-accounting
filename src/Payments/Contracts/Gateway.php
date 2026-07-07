<?php

declare(strict_types=1);

namespace Headless\Accounting\Payments\Contracts;

use Headless\Accounting\Contracts\Payable;
use Headless\Accounting\Payments\Manager;

/**
 * Gateway — the public façade for the payment subsystem. It registers
 * drivers, picks one for a given payable, and routes operations.
 *
 * Implementation: {@see Manager}.
 */
interface Gateway
{
    public function driver(string $name): Driver;

    public function drivers(): array;

    public function register(string $name, Driver $driver): void;

    public function resolveFor(Payable $payable): Driver;

    public function default(): Driver;
}
