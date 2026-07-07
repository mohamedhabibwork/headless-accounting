<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Concerns\HasOrderItems;
use Headless\Accounting\Contracts\OrderSubject;

class FakePayableSubject extends FakeModel implements OrderSubject
{
    use HasOrderItems;

    protected $table = 'fake_order_subjects';

    public function candidateLines(): iterable
    {
        yield ['variant_id' => 1, 'quantity' => 2, 'unit_price_minor' => 1500, 'currency' => 'EUR', 'name' => 'T-Shirt', 'sku' => 'TS-001'];

        yield ['variant_id' => 2, 'quantity' => 1, 'unit_price_minor' => 800, 'currency' => 'EUR', 'name' => 'Mug', 'sku' => 'MG-001'];
    }

    public function shippingMinor(): int
    {
        return 599;
    }

    public function discountTotalMinor(): int
    {
        return 500;
    }
}
