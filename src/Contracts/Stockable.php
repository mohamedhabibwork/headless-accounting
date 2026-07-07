<?php

declare(strict_types=1);

namespace Headless\Accounting\Contracts;

interface Stockable
{
    /** Should this item ever be tracked in stock movements? */
    public function isStockTracked(): bool;
}
