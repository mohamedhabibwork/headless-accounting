<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts;

use Headless\Accounting\Discounts\Contracts\Limitation;
use Headless\Accounting\Exceptions\ConfigurationException;

/**
 * LimitationFactory — symmetric of {@see ConditionFactory}.
 */
final class LimitationFactory
{
    public function __construct(private readonly array $pool) {}

    public function make(string $type): Limitation
    {
        $class = $this->pool[$type] ?? null;
        if (! $class || ! class_exists($class)) {
            throw new ConfigurationException("Unknown limitation type: {$type}");
        }
        $instance = app($class);
        if (! $instance instanceof Limitation) {
            throw new ConfigurationException("{$class} must implement Limitation");
        }

        return $instance;
    }

    public function available(): array
    {
        return array_keys($this->pool);
    }
}
