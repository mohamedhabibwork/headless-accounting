<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Concerns\TaxableProxy;

class FakeTaxableProxyHost extends FakeModel
{
    use TaxableProxy;

    protected $table = 'fake_taxable_proxies';

    protected $attributes = [
        'tax_class_id' => 7,
        'tax_context' => ['digital' => true],
    ];
}
