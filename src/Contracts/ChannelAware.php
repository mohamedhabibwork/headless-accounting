<?php

declare(strict_types=1);

namespace Headless\Accounting\Contracts;

interface ChannelAware
{
    public function channel(): string;
}
