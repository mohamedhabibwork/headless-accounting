<?php

declare(strict_types=1);

namespace Headless\Accounting\Payments;

use Headless\Accounting\Contracts\Payable;
use Headless\Accounting\Exceptions\ConfigurationException;
use Headless\Accounting\Payments\Contracts\Driver;
use Headless\Accounting\Payments\Contracts\Gateway as GatewayContract;

/**
 * Manager — the singleton {@see GatewayContract} implementation. Holds a
 * registry of drivers, picks the right one for a Payable (or its declared
 * driver preference), and is the place to register your own drivers.
 */
final class Manager implements GatewayContract
{
    /** @var array<string, Driver> */
    private array $drivers = [];

    /** @param array<string, array<string, mixed>> $config */
    public function __construct(private readonly array $config) {}

    public function register(string $name, Driver $driver): void
    {
        $this->drivers[$name] = $driver;
    }

    public function driver(string $name): Driver
    {
        if (! isset($this->drivers[$name])) {
            throw new ConfigurationException("Unknown payment driver: {$name}.");
        }

        return $this->drivers[$name];
    }

    public function drivers(): array
    {
        return $this->drivers;
    }

    public function default(): Driver
    {
        $name = (string) ($this->config['default'] ?? array_key_first($this->drivers));

        return $this->driver($name);
    }

    public function resolveFor(Payable $payable): Driver
    {
        // Payables may hint at a preferred driver in metadata.
        $preferred = data_get($payable, 'metadata.payment_intent.driver');
        if ($preferred && isset($this->drivers[$preferred])) {
            return $this->drivers[$preferred];
        }

        return $this->default();
    }

    /**
     * Bootstraps every driver declared in config with its own config block.
     * Called from the service provider after the Manager is constructed.
     */
    public function bootstrap(array $factories): void
    {
        foreach ($this->config['drivers'] ?? [] as $name => $block) {
            $class = $block['class'] ?? null;
            if (! $class || ! class_exists($class)) {
                continue;
            }
            $this->drivers[$name] = $factories[$name] ?? app($class, ['config' => $block]);
        }
    }
}
