<?php

declare(strict_types=1);

namespace Headless\Accounting\Facades;

use Illuminate\Support\Facades\Facade;

class Discounts extends Facade
{
    public static function getFacadeAccessor(): string
    {
        return 'headless.discounts';
    }
}
