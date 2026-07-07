<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts;

use Headless\Accounting\Discounts\Contracts\Condition;
use Headless\Accounting\Exceptions\ConfigurationException;

/**
 * ConditionFactory — resolves a condition `type` string (stored in DB) to
 * an instantiated class implementing {@see Condition}, by consulting the
 * pool configured at `discounts.conditions`.
 */
final class ConditionFactory
{
    public function __construct(private readonly array $pool) {}

    public function make(string $type): Condition
    {
        $class = $this->pool[$type] ?? null;
        if (! $class || ! class_exists($class)) {
            throw new ConfigurationException("Unknown condition type: {$type}");
        }
        $instance = app($class);
        if (! $instance instanceof Condition) {
            throw new ConfigurationException("{$class} must implement Condition");
        }

        return $instance;
    }

    /** @return string[] type slugs */
    public function available(): array
    {
        return array_keys($this->pool);
    }
}
