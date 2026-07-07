<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts;

use Headless\Accounting\Discounts\Contracts\Limitation as LimitationContract;

abstract class BaseLimitation implements LimitationContract
{
    protected array $config = [];

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    protected function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }
}
