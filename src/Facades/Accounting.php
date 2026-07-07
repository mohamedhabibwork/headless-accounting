<?php

declare(strict_types=1);

namespace Headless\Accounting\Facades;

use Headless\Accounting\HeadlessAccountingServiceProvider;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Headless\Accounting\Accounting\Journal journal()
 * @method static \Headless\Accounting\Accounting\Ledger ledger()
 *
 * @see HeadlessAccountingServiceProvider
 */
class Accounting extends Facade
{
    public static function getFacadeAccessor(): string
    {
        return 'headless.accounting';
    }
}
