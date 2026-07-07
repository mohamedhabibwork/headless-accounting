<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests;

use Headless\Accounting\Facades\Accounting;
use Headless\Accounting\Facades\Discounts;
use Headless\Accounting\Facades\Payments;
use Headless\Accounting\Facades\Tax;
use Headless\Accounting\HeadlessAccountingServiceProvider;
use Headless\Accounting\Tests\Traits\RefreshSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;
    use RefreshSchema;

    protected function getPackageProviders($app): array
    {
        return [HeadlessAccountingServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Accounting' => Accounting::class,
            'Discounts' => Discounts::class,
            'Tax' => Tax::class,
            'Payments' => Payments::class,
        ];
    }

    /**
     * Configure the host environment for every test.
     *
     * `defineEnvironment` is the Orchestra Testbench 9+ canonical hook
     * for environment setup; the legacy `getEnvironmentSetUp` is
     * intentionally not overridden here.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'finance_test'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.timezone', 'UTC');
    }
}
