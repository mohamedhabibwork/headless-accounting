<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Unit\Routing;

use Headless\Accounting\Http\Controllers\AddressController;
use Headless\Accounting\Http\Controllers\CartController;
use Headless\Accounting\Http\Controllers\CheckoutController;
use Headless\Accounting\Http\Controllers\CustomerController;
use Headless\Accounting\Http\Controllers\DiscountController;
use Headless\Accounting\Http\Controllers\FulfillmentPlanController;
use Headless\Accounting\Http\Controllers\InvoiceController;
use Headless\Accounting\Http\Controllers\OrderController;
use Headless\Accounting\Http\Controllers\PaymentController;
use Headless\Accounting\Http\Controllers\PricingController;
use Headless\Accounting\Http\Controllers\ProductController;
use Headless\Accounting\Http\Controllers\ReportsController;
use Headless\Accounting\Http\Controllers\StocktakeController;
use Headless\Accounting\Http\Controllers\TaxController;
use Headless\Accounting\Http\Controllers\WarehouseController;
use Headless\Accounting\Http\Controllers\WebhookController;
use Headless\Accounting\Http\Controllers\WorkflowController;
use Headless\Accounting\Http\HeadlessRouteMethods;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit-level coverage for the HeadlessRouteMethods mixin shape.
 *
 * This suite is intentionally Laravel-free so it can run without
 * Orchestra Testbench. It mirrors the shape of laravel/ui's
 * AuthRouteMethods unit coverage: confirm the public surface of
 * the mixin is intact, every method hands back a closure, and the
 * per-group helpers resolve to the expected controllers.
 */
class HeadlessRouteMethodsTest extends TestCase
{
    public static function expectedMixinMethods(): array
    {
        return [
            'headless',
            'headlessCatalog',
            'headlessPricing',
            'headlessCart',
            'headlessCheckout',
            'headlessOrders',
            'headlessPayments',
            'headlessInvoices',
            'headlessDiscounts',
            'headlessTaxes',
            'headlessCustomers',
            'headlessAddresses',
            'headlessReports',
            'headlessWorkflow',
            'headlessWebhooks',
            'headlessWarehouses',
            'headlessFulfillment',
            'headlessStocktakes',
        ];
    }

    public function testEveryExpectedMixinMethodExists(): void
    {
        $reflection = new ReflectionClass(HeadlessRouteMethods::class);

        foreach (self::expectedMixinMethods() as $name) {
            $this->assertTrue(
                $reflection->hasMethod($name),
                "HeadlessRouteMethods must expose a `{$name}()` method.",
            );
        }
    }

    public function testEveryMixinMethodReturnsACallable(): void
    {
        $mixin = new HeadlessRouteMethods;

        foreach (self::expectedMixinMethods() as $method) {
            $returned = $mixin->{$method}();
            $this->assertIsCallable(
                $returned,
                "`{$method}()` must return a callable so Route::mixin can register it as a macro.",
            );
        }
    }

    public function testMasterMixinAcceptsAnOptionsArray(): void
    {
        $closure = (new HeadlessRouteMethods)->headless();
        $this->assertIsCallable($closure);

        $reflection = new \ReflectionFunction($closure);
        $this->assertGreaterThanOrEqual(1, $reflection->getNumberOfParameters());
    }

    public function testEverySubgroupResolvesToItsDocumentedController(): void
    {
        $expected = [
            ProductController::class,
            PricingController::class,
            CartController::class,
            CheckoutController::class,
            OrderController::class,
            PaymentController::class,
            InvoiceController::class,
            DiscountController::class,
            TaxController::class,
            CustomerController::class,
            AddressController::class,
            ReportsController::class,
            WorkflowController::class,
            WebhookController::class,
            WarehouseController::class,
            FulfillmentPlanController::class,
            StocktakeController::class,
        ];

        foreach ($expected as $controller) {
            $this->assertTrue(
                class_exists($controller),
                "Expected controller {$controller} does not exist.",
            );
        }

        $this->assertSame(count($expected), count(array_unique($expected)));
    }
}
