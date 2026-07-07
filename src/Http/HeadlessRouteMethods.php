<?php

declare(strict_types=1);

namespace Headless\Accounting\Http;

use Headless\Accounting\Http\Controllers\AddressController;
use Headless\Accounting\Http\Controllers\BatchController;
use Headless\Accounting\Http\Controllers\BinController;
use Headless\Accounting\Http\Controllers\CartController;
use Headless\Accounting\Http\Controllers\CheckoutController;
use Headless\Accounting\Http\Controllers\CustomerController;
use Headless\Accounting\Http\Controllers\DiscountController;
use Headless\Accounting\Http\Controllers\DisposalOrderController;
use Headless\Accounting\Http\Controllers\FulfillmentPlanController;
use Headless\Accounting\Http\Controllers\GoodsIssueController;
use Headless\Accounting\Http\Controllers\InventoryAdjustmentController;
use Headless\Accounting\Http\Controllers\InventoryController;
use Headless\Accounting\Http\Controllers\InventoryTransferController;
use Headless\Accounting\Http\Controllers\InvoiceController;
use Headless\Accounting\Http\Controllers\OrderController;
use Headless\Accounting\Http\Controllers\PaymentController;
use Headless\Accounting\Http\Controllers\PricingController;
use Headless\Accounting\Http\Controllers\ProductController;
use Headless\Accounting\Http\Controllers\ProductionOrderController;
use Headless\Accounting\Http\Controllers\ReplenishmentController;
use Headless\Accounting\Http\Controllers\ReportsController;
use Headless\Accounting\Http\Controllers\SerialNumberController;
use Headless\Accounting\Http\Controllers\StocktakeController;
use Headless\Accounting\Http\Controllers\StockWriteOffController;
use Headless\Accounting\Http\Controllers\TaxController;
use Headless\Accounting\Http\Controllers\WarehouseController;
use Headless\Accounting\Http\Controllers\WebhookController;
use Headless\Accounting\Http\Controllers\WorkflowController;
use Headless\Accounting\Support\Config;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\Route;

/**
 * Mirrors the pattern used by `laravel/ui`'s `AuthRouteMethods`.
 *
 * Each public method returns a closure which, when invoked on the bound
 * {@see RouteRegistrar}, registers a sub-group of routes for a given
 * domain of the package. The closure is bound to `Route::class` (a
 * `RouteRegistrar`) at mix-in time so that calls like `$this->get(...)`
 * and `$this->group(...)` resolve to the underlying Router.
 *
 * The package's service provider registers this mixin via
 * {@see Route::mixin()} in its `boot()` method. After the service
 * provider is booted, the following callable helpers are available
 * anywhere `Route` is:
 *
 *     Route::headless();                                       // register everything (config-aware defaults)
 *     Route::headless(['prefix' => 'api/shop/v1']);            // custom prefix
 *     Route::headless(['webhooks' => false]);                  // opt out of one group
 *
 *     Route::prefix('v1/shop')->middleware('api')->group(function () {
 *         Route::headlessCatalog();
 *         Route::headlessCheckout();
 *         Route::headlessOrders();
 *         Route::headlessPayments();
 *     });
 *
 * Defaults for each group's `true/false` switch are read from
 * `config('headless-accounting.http.groups.*')`; explicit options
 * passed to `Route::headless(['catalog' => false])` always win.
 */
class HeadlessRouteMethods
{
    /**
     * Register every package route group under one shared prefix / middleware
     * / namespace group.
     *
     * Per-group switches and their config keys:
     *  - catalog    ⇢ http.groups.catalog
     *  - pricing    ⇢ http.groups.pricing
     *  - cart       ⇢ http.groups.cart
     *  - checkout   ⇢ http.groups.checkout
     *  - orders     ⇢ http.groups.orders
     *  - payments   ⇢ http.groups.payments
     *  - invoices   ⇢ http.groups.invoices
     *  - discounts  ⇢ http.groups.discounts
     *  - taxes      ⇢ http.groups.taxes
     *  - customers  ⇢ http.groups.customers
     *  - addresses  ⇢ http.groups.addresses
     *  - reports    ⇢ http.groups.reports
     *  - workflow   ⇢ http.groups.workflow
     *  - webhooks   ⇢ http.groups.webhooks
     *  - inventory  ⇢ http.groups.inventory
     *  - batches    ⇢ http.groups.batches
     *  - serials    ⇢ http.groups.serials
     *  - production ⇢ http.groups.production
     *
     * Explicit options passed via `Route::headless(['catalog' => false])`
     * always take precedence over the config-derived defaults.
     */
    public function headless(): callable
    {
        return function (array $options = []) {
            $options = array_merge(
                [
                    'prefix' => Config::string('headless-accounting.http.base_path', 'api/v1/headless'),
                    'middleware' => Config::array('headless-accounting.http.middleware', ['api']),
                    'namespace' => 'Headless\\Accounting\\Http\\Controllers',
                    'catalog' => Config::bool('headless-accounting.http.groups.catalog', true),
                    'pricing' => Config::bool('headless-accounting.http.groups.pricing', true),
                    'cart' => Config::bool('headless-accounting.http.groups.cart', true),
                    'checkout' => Config::bool('headless-accounting.http.groups.checkout', true),
                    'orders' => Config::bool('headless-accounting.http.groups.orders', true),
                    'payments' => Config::bool('headless-accounting.http.groups.payments', true),
                    'invoices' => Config::bool('headless-accounting.http.groups.invoices', true),
                    'discounts' => Config::bool('headless-accounting.http.groups.discounts', true),
                    'taxes' => Config::bool('headless-accounting.http.groups.taxes', true),
                    'customers' => Config::bool('headless-accounting.http.groups.customers', true),
                    'addresses' => Config::bool('headless-accounting.http.groups.addresses', true),
                    'reports' => Config::bool('headless-accounting.http.groups.reports', true),
                    'workflow' => Config::bool('headless-accounting.http.groups.workflow', true),
                    'webhooks' => Config::bool('headless-accounting.http.groups.webhooks', true),
                    'warehouses' => Config::bool('headless-accounting.http.groups.warehouses', true),
                    'fulfillment' => Config::bool('headless-accounting.http.groups.fulfillment', true),
                    'stocktakes' => Config::bool('headless-accounting.http.groups.stocktakes', true),
                    'inventory' => Config::bool('headless-accounting.http.groups.inventory', true),
                    'batches' => Config::bool('headless-accounting.http.groups.batches', true),
                    'serials' => Config::bool('headless-accounting.http.groups.serials', true),
                    'production' => Config::bool('headless-accounting.http.groups.production', true),
                ],
                $options,
            );

            $this->group(
                [
                    'namespace' => $options['namespace'],
                    'prefix' => $options['prefix'],
                    'middleware' => $options['middleware'],
                ],
                function () use ($options) {
                    if ($options['catalog']) {
                        $this->headlessCatalog();
                    }
                    if ($options['pricing']) {
                        $this->headlessPricing();
                    }
                    if ($options['cart']) {
                        $this->headlessCart();
                    }
                    if ($options['checkout']) {
                        $this->headlessCheckout();
                    }
                    if ($options['orders']) {
                        $this->headlessOrders();
                    }
                    if ($options['payments']) {
                        $this->headlessPayments();
                    }
                    if ($options['invoices']) {
                        $this->headlessInvoices();
                    }
                    if ($options['discounts']) {
                        $this->headlessDiscounts();
                    }
                    if ($options['taxes']) {
                        $this->headlessTaxes();
                    }
                    if ($options['customers']) {
                        $this->headlessCustomers();
                    }
                    if ($options['addresses']) {
                        $this->headlessAddresses();
                    }
                    if ($options['reports']) {
                        $this->headlessReports();
                    }
                    if ($options['workflow']) {
                        $this->headlessWorkflow();
                    }
                    if ($options['webhooks']) {
                        $this->headlessWebhooks();
                    }
                    if ($options['warehouses']) {
                        $this->headlessWarehouses();
                    }
                    if ($options['fulfillment']) {
                        $this->headlessFulfillment();
                    }
                    if ($options['stocktakes']) {
                        $this->headlessStocktakes();
                    }
                    if ($options['inventory']) {
                        $this->headlessInventory();
                    }
                    if ($options['batches']) {
                        $this->headlessBatches();
                    }
                    if ($options['serials']) {
                        $this->headlessSerials();
                    }
                    if ($options['production']) {
                        $this->headlessProduction();
                    }
                },
            );
        };
    }

    /**
     * Catalog — product list / show. Paths:
     *  - GET    catalog/products                                (headless.catalog.products.index)
     *  - GET    catalog/products/{id}                           (headless.catalog.products.show)
     */
    public function headlessCatalog(): callable
    {
        return function () {
            $this->get('catalog/products', [ProductController::class, 'index'])->name('headless.catalog.products.index');
            $this->get('catalog/products/{id}', [ProductController::class, 'show'])->name('headless.catalog.products.show');
        };
    }

    /**
     * Pricing — resolve a variant price. Paths:
     *  - GET    pricing/resolve                                 (headless.pricing.resolve)
     */
    public function headlessPricing(): callable
    {
        return function () {
            $this->get('pricing/resolve', [PricingController::class, 'resolve'])->name('headless.pricing.resolve');
        };
    }

    /**
     * Cart — anonymous / customer-keyed cart. Paths:
     *  - POST   cart                                           (headless.cart.store)
     *  - GET    cart/{cartId}                                  (headless.cart.show)
     *  - DELETE cart/{cartId}                                  (headless.cart.destroy)
     *  - POST   cart/{cartId}/items/{variantId}                (headless.cart.items.add)
     */
    public function headlessCart(): callable
    {
        return function () {
            $this->post('cart', [CartController::class, 'store'])->name('headless.cart.store');
            $this->get('cart/{cartId}', [CartController::class, 'show'])->name('headless.cart.show');
            $this->delete('cart/{cartId}', [CartController::class, 'destroy'])->name('headless.cart.destroy');
            $this->post('cart/{cartId}/items/{variantId}', [CartController::class, 'addItem'])->name('headless.cart.items.add');
        };
    }

    /**
     * Checkout — onboarding endpoints that convert a cart / draft into a
     * placed order. Paths:
     *  - POST   checkout                                       (headless.checkout.store)
     *  - POST   orders/{orderId}/place                         (headless.checkout.place)
     */
    public function headlessCheckout(): callable
    {
        return function () {
            $this->post('checkout', [CheckoutController::class, 'store'])->name('headless.checkout.store');
            $this->post('orders/{orderId}/place', [CheckoutController::class, 'place'])->name('headless.checkout.place');
        };
    }

    /**
     * Orders — per-order mutations and read. Paths:
     *  - GET    orders/{orderId}                               (headless.orders.show)
     *  - POST   orders/{orderId}/items/{variantId}             (headless.orders.items.add)
     *  - POST   orders/{orderId}/recalc                        (headless.orders.recalculate)
     *  - POST   orders/{orderId}/discounts                     (headless.orders.discounts.apply)
     *  - POST   orders/{orderId}/refunds                       (headless.orders.refunds)
     */
    public function headlessOrders(): callable
    {
        return function () {
            $this->get('orders/{orderId}', [OrderController::class, 'show'])->name('headless.orders.show');
            $this->post('orders/{orderId}/items/{variantId}', [OrderController::class, 'addItem'])->name('headless.orders.items.add');
            $this->post('orders/{orderId}/recalc', [OrderController::class, 'recalculate'])->name('headless.orders.recalculate');
            $this->post('orders/{orderId}/discounts', [OrderController::class, 'applyDiscount'])->name('headless.orders.discounts.apply');
            $this->post('orders/{orderId}/refunds', [OrderController::class, 'refund'])->name('headless.orders.refunds');
        };
    }

    /**
     * Payments — capture against an order, refund a payment directly. Paths:
     *  - POST   orders/{orderId}/payments                      (headless.payments.capture)
     *  - POST   payments/{paymentId}/refund                    (headless.payments.refund)
     */
    public function headlessPayments(): callable
    {
        return function () {
            $this->post('orders/{orderId}/payments', [PaymentController::class, 'capture'])->name('headless.payments.capture');
            $this->post('payments/{paymentId}/refund', [PaymentController::class, 'refund'])->name('headless.payments.refund');
        };
    }

    /**
     * Invoices — read-only and list. Paths:
     *  - GET    invoices                                       (headless.invoices.index)
     *  - GET    invoices/{invoiceId}                           (headless.invoices.show)
     */
    public function headlessInvoices(): callable
    {
        return function () {
            $this->get('invoices', [InvoiceController::class, 'index'])->name('headless.invoices.index');
            $this->get('invoices/{invoiceId}', [InvoiceController::class, 'show'])->name('headless.invoices.show');
        };
    }

    /**
     * Discounts — promotion management. Paths:
     *  - GET    discounts                                      (headless.discounts.index)
     *  - POST   discounts                                      (headless.discounts.store)
     *  - GET    discounts/{discountId}                         (headless.discounts.show)
     *  - PUT    discounts/{discountId}                         (headless.discounts.update)
     *  - DELETE discounts/{discountId}                         (headless.discounts.destroy)
     */
    public function headlessDiscounts(): callable
    {
        return function () {
            $this->get('discounts', [DiscountController::class, 'index'])->name('headless.discounts.index');
            $this->post('discounts', [DiscountController::class, 'store'])->name('headless.discounts.store');
            $this->get('discounts/{discountId}', [DiscountController::class, 'show'])->name('headless.discounts.show');
            $this->put('discounts/{discountId}', [DiscountController::class, 'update'])->name('headless.discounts.update');
            $this->delete('discounts/{discountId}', [DiscountController::class, 'destroy'])->name('headless.discounts.destroy');
        };
    }

    /**
     * Taxes — zones / classes / rates. Paths:
     *  - GET    tax/zones                                      (headless.taxes.zones.index)
     *  - POST   tax/zones                                      (headless.taxes.zones.upsert)
     *  - GET    tax/zones/{zoneId}                             (headless.taxes.zones.show)
     *  - DELETE tax/zones/{zoneId}                             (headless.taxes.zones.destroy)
     *  - GET    tax/classes                                    (headless.taxes.classes.index)
     *  - POST   tax/classes                                    (headless.taxes.classes.store)
     *  - GET    tax/classes/{classId}                          (headless.taxes.classes.show)
     *  - DELETE tax/classes/{classId}                          (headless.taxes.classes.destroy)
     *  - GET    tax/rates                                      (headless.taxes.rates.index)
     *  - POST   tax/rates                                      (headless.taxes.rates.store)
     *  - DELETE tax/rates/{rateId}                             (headless.taxes.rates.destroy)
     */
    public function headlessTaxes(): callable
    {
        return function () {
            $this->get('tax/zones', [TaxController::class, 'indexZones'])->name('headless.taxes.zones.index');
            $this->post('tax/zones', [TaxController::class, 'upsertZone'])->name('headless.taxes.zones.upsert');
            $this->get('tax/zones/{zoneId}', [TaxController::class, 'showZone'])->name('headless.taxes.zones.show');
            $this->delete('tax/zones/{zoneId}', [TaxController::class, 'destroyZone'])->name('headless.taxes.zones.destroy');

            $this->get('tax/classes', [TaxController::class, 'indexClasses'])->name('headless.taxes.classes.index');
            $this->post('tax/classes', [TaxController::class, 'storeClass'])->name('headless.taxes.classes.store');
            $this->get('tax/classes/{classId}', [TaxController::class, 'showClass'])->name('headless.taxes.classes.show');
            $this->delete('tax/classes/{classId}', [TaxController::class, 'destroyClass'])->name('headless.taxes.classes.destroy');

            $this->get('tax/rates', [TaxController::class, 'indexRates'])->name('headless.taxes.rates.index');
            $this->post('tax/rates', [TaxController::class, 'storeRate'])->name('headless.taxes.rates.store');
            $this->delete('tax/rates/{rateId}', [TaxController::class, 'destroyRate'])->name('headless.taxes.rates.destroy');
        };
    }

    /**
     * Customers — CRUD over the customer aggregate. Paths:
     *  - GET    customers                                      (headless.customers.index)
     *  - POST   customers                                      (headless.customers.store)
     *  - GET    customers/{customerId}                         (headless.customers.show)
     *  - PUT    customers/{customerId}                         (headless.customers.update)
     *  - DELETE customers/{customerId}                         (headless.customers.destroy)
     */
    public function headlessCustomers(): callable
    {
        return function () {
            $this->get('customers', [CustomerController::class, 'index'])->name('headless.customers.index');
            $this->post('customers', [CustomerController::class, 'store'])->name('headless.customers.store');
            $this->get('customers/{customerId}', [CustomerController::class, 'show'])->name('headless.customers.show');
            $this->put('customers/{customerId}', [CustomerController::class, 'update'])->name('headless.customers.update');
            $this->delete('customers/{customerId}', [CustomerController::class, 'destroy'])->name('headless.customers.destroy');
        };
    }

    /**
     * Addresses — nested CRUD under a customer. Paths:
     *  - GET    customers/{customerId}/addresses               (headless.addresses.index)
     *  - POST   customers/{customerId}/addresses               (headless.addresses.store)
     *  - GET    customers/{customerId}/addresses/{addressId}   (headless.addresses.show)
     *  - PUT    customers/{customerId}/addresses/{addressId}   (headless.addresses.update)
     *  - DELETE customers/{customerId}/addresses/{addressId}   (headless.addresses.destroy)
     */
    public function headlessAddresses(): callable
    {
        return function () {
            $this->get('customers/{customerId}/addresses', [AddressController::class, 'index'])->name('headless.addresses.index');
            $this->post('customers/{customerId}/addresses', [AddressController::class, 'store'])->name('headless.addresses.store');
            $this->get('customers/{customerId}/addresses/{addressId}', [AddressController::class, 'show'])->name('headless.addresses.show');
            $this->put('customers/{customerId}/addresses/{addressId}', [AddressController::class, 'update'])->name('headless.addresses.update');
            $this->delete('customers/{customerId}/addresses/{addressId}', [AddressController::class, 'destroy'])->name('headless.addresses.destroy');
        };
    }

    /**
     * Reporting — accounting and merchant reports. Paths:
     *  - GET    reports/trial-balance                          (headless.reports.trial-balance)
     *  - GET    reports/income-statement                       (headless.reports.income-statement)
     *  - GET    reports/sales-by-channel                       (headless.reports.sales-by-channel)
     */
    public function headlessReports(): callable
    {
        return function () {
            $this->get('reports/trial-balance', [ReportsController::class, 'trialBalance'])->name('headless.reports.trial-balance');
            $this->get('reports/income-statement', [ReportsController::class, 'incomeStatement'])->name('headless.reports.income-statement');
            $this->get('reports/sales-by-channel', [ReportsController::class, 'salesByChannel'])->name('headless.reports.sales-by-channel');
        };
    }

    /**
     * Workflow / Approvals — multi-level approval engine.
     *   Definitions (templates):
     *    - GET    workflow/definitions                         (headless.workflow.definitions.index)
     *    - POST   workflow/definitions                         (headless.workflow.definitions.store)
     *    - GET    workflow/definitions/{definitionId}          (headless.workflow.definitions.show)
     *    - PUT    workflow/definitions/{definitionId}          (headless.workflow.definitions.update)
     *    - DELETE workflow/definitions/{definitionId}          (headless.workflow.definitions.destroy)
     *   Steps (nested):
     *    - POST   workflow/definitions/{definitionId}/steps    (headless.workflow.steps.store)
     *    - PUT    workflow/steps/{stepId}                      (headless.workflow.steps.update)
     *    - DELETE workflow/steps/{stepId}                      (headless.workflow.steps.destroy)
     *   Instances (live approvals):
     *    - GET    workflow/instances                           (headless.workflow.instances.index)
     *    - POST   workflow/instances                           (headless.workflow.instances.store)
     *    - GET    workflow/instances/{instanceId}              (headless.workflow.instances.show)
     *    - POST   workflow/instances/{instanceId}/decisions    (headless.workflow.instances.decide)
     *    - POST   workflow/instances/{instanceId}/cancel       (headless.workflow.instances.cancel)
     *    - GET    workflow/instances/{instanceId}/actions      (headless.workflow.instances.actions)
     *   Action log / inbox:
     *    - GET    workflow/actions                             (headless.workflow.actions.index)
     *    - GET    workflow/inbox                               (headless.workflow.inbox)
     *   Delegations:
     *    - GET    workflow/delegations                         (headless.workflow.delegations.index)
     *    - POST   workflow/delegations                         (headless.workflow.delegations.store)
     *    - DELETE workflow/delegations/{delegationId}         (headless.workflow.delegations.destroy)
     */
    public function headlessWorkflow(): callable
    {
        return function () {
            // Definitions
            $this->get('workflow/definitions', [WorkflowController::class, 'indexDefinitions'])->name('headless.workflow.definitions.index');
            $this->post('workflow/definitions', [WorkflowController::class, 'storeDefinition'])->name('headless.workflow.definitions.store');
            $this->get('workflow/definitions/{definitionId}', [WorkflowController::class, 'showDefinition'])->name('headless.workflow.definitions.show');
            $this->put('workflow/definitions/{definitionId}', [WorkflowController::class, 'updateDefinition'])->name('headless.workflow.definitions.update');
            $this->delete('workflow/definitions/{definitionId}', [WorkflowController::class, 'destroyDefinition'])->name('headless.workflow.definitions.destroy');

            // Steps (nested under definition, plus direct by id)
            $this->post('workflow/definitions/{definitionId}/steps', [WorkflowController::class, 'storeStep'])->name('headless.workflow.steps.store');
            $this->put('workflow/steps/{stepId}', [WorkflowController::class, 'updateStep'])->name('headless.workflow.steps.update');
            $this->delete('workflow/steps/{stepId}', [WorkflowController::class, 'destroyStep'])->name('headless.workflow.steps.destroy');

            // Instances
            $this->get('workflow/instances', [WorkflowController::class, 'indexInstances'])->name('headless.workflow.instances.index');
            $this->post('workflow/instances', [WorkflowController::class, 'startInstance'])->name('headless.workflow.instances.store');
            $this->get('workflow/instances/{instanceId}', [WorkflowController::class, 'showInstance'])->name('headless.workflow.instances.show');
            $this->post('workflow/instances/{instanceId}/decisions', [WorkflowController::class, 'decideInstance'])->name('headless.workflow.instances.decide');
            $this->post('workflow/instances/{instanceId}/cancel', [WorkflowController::class, 'cancelInstance'])->name('headless.workflow.instances.cancel');
            $this->get('workflow/instances/{instanceId}/actions', [WorkflowController::class, 'indexInstanceActions'])->name('headless.workflow.instances.actions');

            // Action log + inbox
            $this->get('workflow/actions', [WorkflowController::class, 'indexActions'])->name('headless.workflow.actions.index');
            $this->get('workflow/inbox', [WorkflowController::class, 'inbox'])->name('headless.workflow.inbox');

            // Delegations
            $this->get('workflow/delegations', [WorkflowController::class, 'indexDelegations'])->name('headless.workflow.delegations.index');
            $this->post('workflow/delegations', [WorkflowController::class, 'storeDelegation'])->name('headless.workflow.delegations.store');
            $this->delete('workflow/delegations/{delegationId}', [WorkflowController::class, 'destroyDelegation'])->name('headless.workflow.delegations.destroy');
        };
    }

    /**
     * Warehouses — list, show, CRUD, rate-shop. Paths:
     *  - GET    warehouses                                       (headless.warehouses.index)
     *  - POST   warehouses                                       (headless.warehouses.store)
     *  - GET    warehouses/{warehouse}                           (headless.warehouses.show)
     *  - PUT    warehouses/{warehouse}                           (headless.warehouses.update)
     *  - DELETE warehouses/{warehouse}                           (headless.warehouses.destroy)
     *  - POST   warehouses/{warehouse}/rate-shop                 (headless.warehouses.rate-shop)
     */
    public function headlessWarehouses(): callable
    {
        return function () {
            $this->get('warehouses', [WarehouseController::class, 'index'])->name('headless.warehouses.index');
            $this->post('warehouses', [WarehouseController::class, 'store'])->name('headless.warehouses.store');
            $this->get('warehouses/{warehouse}', [WarehouseController::class, 'show'])->name('headless.warehouses.show');
            $this->put('warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('headless.warehouses.update');
            $this->delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('headless.warehouses.destroy');
            $this->post('warehouses/{warehouse}/rate-shop', [WarehouseController::class, 'rateShop'])->name('headless.warehouses.rate-shop');
        };
    }

    /**
     * Fulfillment — plans, pick lists, packing, shipping, delivery. Paths:
     *  - GET    fulfillment/plans                                 (headless.fulfillment.plans.index)
     *  - GET    fulfillment/plans/{plan}                         (headless.fulfillment.plans.show)
     *  - POST   orders/{order}/fulfillment-plan                   (headless.fulfillment.plans.build)
     *  - POST   fulfillment/plans/{plan}/pick-lists               (headless.fulfillment.pick-lists.create)
     *  - POST   fulfillment/pick-lists/{list}/pick               (headless.fulfillment.pick-lists.pick)
     *  - POST   fulfillment/pick-lists/{list}/pack               (headless.fulfillment.pick-lists.pack)
     *  - POST   fulfillment/pack-stations/{station}/ship         (headless.fulfillment.shipments.ship)
     *  - POST   shipments/{shipment}/deliver                     (headless.fulfillment.shipments.deliver)
     */
    public function headlessFulfillment(): callable
    {
        return function () {
            $this->get('fulfillment/plans', [FulfillmentPlanController::class, 'index'])->name('headless.fulfillment.plans.index');
            $this->get('fulfillment/plans/{plan}', [FulfillmentPlanController::class, 'show'])->name('headless.fulfillment.plans.show');
            $this->post('orders/{order}/fulfillment-plan', [FulfillmentPlanController::class, 'buildForOrder'])->name('headless.fulfillment.plans.build');
            $this->post('fulfillment/plans/{plan}/pick-lists', [FulfillmentPlanController::class, 'createPickList'])->name('headless.fulfillment.pick-lists.create');
            $this->post('fulfillment/pick-lists/{pickList}/pick', [FulfillmentPlanController::class, 'pickLine'])->name('headless.fulfillment.pick-lists.pick');
            $this->post('fulfillment/pick-lists/{pickList}/pack', [FulfillmentPlanController::class, 'packList'])->name('headless.fulfillment.pick-lists.pack');
            $this->post('fulfillment/pack-stations/{packStation}/ship', [FulfillmentPlanController::class, 'ship'])->name('headless.fulfillment.shipments.ship');
            $this->post('shipments/{shipment}/deliver', [FulfillmentPlanController::class, 'markDelivered'])->name('headless.fulfillment.shipments.deliver');
        };
    }

    /**
     * Stocktakes — full lifecycle (draft → posted). Paths:
     *  - GET    stocktakes                                       (headless.stocktakes.index)
     *  - POST   stocktakes                                       (headless.stocktakes.store)
     *  - GET    stocktakes/{stocktake}                           (headless.stocktakes.show)
     *  - POST   stocktakes/{stocktake}/counts                    (headless.stocktakes.counts.record)
     *  - POST   stocktakes/{stocktake}/submit                    (headless.stocktakes.submit)
     *  - POST   stocktakes/{stocktake}/approve                   (headless.stocktakes.approve)
     *  - POST   stocktakes/{stocktake}/cancel                    (headless.stocktakes.cancel)
     *  - POST   stocktakes/{stocktake}/post                      (headless.stocktakes.post)
     *  - GET    stocktakes/{stocktake}/variance                  (headless.stocktakes.variance-summary)
     */
    public function headlessStocktakes(): callable
    {
        return function () {
            $this->get('stocktakes', [StocktakeController::class, 'index'])->name('headless.stocktakes.index');
            $this->post('stocktakes', [StocktakeController::class, 'store'])->name('headless.stocktakes.store');
            $this->get('stocktakes/{stocktake}', [StocktakeController::class, 'show'])->name('headless.stocktakes.show');
            $this->post('stocktakes/{stocktake}/counts', [StocktakeController::class, 'recordCount'])->name('headless.stocktakes.counts.record');
            $this->post('stocktakes/{stocktake}/submit', [StocktakeController::class, 'submitForReview'])->name('headless.stocktakes.submit');
            $this->post('stocktakes/{stocktake}/approve', [StocktakeController::class, 'approve'])->name('headless.stocktakes.approve');
            $this->post('stocktakes/{stocktake}/cancel', [StocktakeController::class, 'cancel'])->name('headless.stocktakes.cancel');
            $this->post('stocktakes/{stocktake}/post', [StocktakeController::class, 'post'])->name('headless.stocktakes.post');
            $this->get('stocktakes/{stocktake}/variance', [StocktakeController::class, 'varianceSummary'])->name('headless.stocktakes.variance-summary');
        };
    }

    /**
     * Provider webhooks — public, no auth. Paths:
     *  - POST   webhooks/payments/{driver}                     (headless.webhooks.payments)
     */
    public function headlessWebhooks(): callable
    {
        return function () {
            $this->post('webhooks/payments/{driver}', [WebhookController::class, 'handle'])->name('headless.webhooks.payments');
        };
    }

    /**
     * Inventory — meta / aggregated endpoints plus the document
     * controllers for goods issues, write-offs, disposal orders,
     * inventory transfers, adjustments, bins, replenishment.
     *
     *  - GET    inventory/valuation                              (headless.inventory.valuation)
     *  - GET    inventory/availability                           (headless.inventory.availability)
     *  - GET    inventory/expiring                              (headless.inventory.expiring)
     *  - POST   inventory/sweep                                 (headless.inventory.sweep)
     *  - GET    replenishment/proposals                         (headless.replenishment.proposals)
     *  - POST   replenishment/generate                          (headless.replenishment.generate)
     *  - GET    replenishment/proposal                           (headless.replenishment.proposal)
     *  - GET    goods-issues                                    (headless.goods-issues.index)
     *  - POST   goods-issues                                    (headless.goods-issues.store)
     *  - GET    goods-issues/{goodsIssue}                       (headless.goods-issues.show)
     *  - PUT    goods-issues/{goodsIssue}                       (headless.goods-issues.update)
     *  - DELETE goods-issues/{goodsIssue}                       (headless.goods-issues.destroy)
     *  - POST   goods-issues/{goodsIssue}/post                  (headless.goods-issues.post)
     *  - GET    stock-write-offs                                (headless.stock-write-offs.index)
     *  - POST   stock-write-offs                                (headless.stock-write-offs.store)
     *  - GET    stock-write-offs/{stockWriteOff}                (headless.stock-write-offs.show)
     *  - DELETE stock-write-offs/{stockWriteOff}                (headless.stock-write-offs.destroy)
     *  - POST   stock-write-offs/{stockWriteOff}/approve        (headless.stock-write-offs.approve)
     *  - POST   stock-write-offs/{stockWriteOff}/post           (headless.stock-write-offs.post)
     *  - GET    disposal-orders                                 (headless.disposal-orders.index)
     *  - POST   disposal-orders                                 (headless.disposal-orders.store)
     *  - GET    disposal-orders/{disposalOrder}                 (headless.disposal-orders.show)
     *  - PUT    disposal-orders/{disposalOrder}                 (headless.disposal-orders.update)
     *  - DELETE disposal-orders/{disposalOrder}                 (headless.disposal-orders.destroy)
     *  - POST   disposal-orders/{disposalOrder}/execute         (headless.disposal-orders.execute)
     *  - GET    inventory-transfers                             (headless.inventory-transfers.index)
     *  - POST   inventory-transfers                             (headless.inventory-transfers.store)
     *  - GET    inventory-transfers/{transfer}                  (headless.inventory-transfers.show)
     *  - DELETE inventory-transfers/{transfer}                  (headless.inventory-transfers.destroy)
     *  - POST   inventory-transfers/{transfer}/post             (headless.inventory-transfers.post)
     *  - GET    inventory-adjustments                           (headless.inventory-adjustments.index)
     *  - GET    inventory-adjustments/{adjustment}              (headless.inventory-adjustments.show)
     *  - POST   inventory-adjustments/{adjustment}/post         (headless.inventory-adjustments.post)
     *  - GET    bins                                            (headless.bins.index)
     *  - POST   bins                                            (headless.bins.store)
     *  - GET    bins/{bin}                                      (headless.bins.show)
     *  - PUT    bins/{bin}                                      (headless.bins.update)
     *  - DELETE bins/{bin}                                      (headless.bins.destroy)
     *  - GET    bins/{bin}/contents                             (headless.bins.contents)
     */
    public function headlessInventory(): callable
    {
        return function () {
            $this->get('inventory/valuation', [InventoryController::class, 'valuation'])->name('headless.inventory.valuation');
            $this->get('inventory/availability', [InventoryController::class, 'availability'])->name('headless.inventory.availability');
            $this->get('inventory/expiring', [InventoryController::class, 'expiring'])->name('headless.inventory.expiring');
            $this->post('inventory/sweep', [InventoryController::class, 'sweep'])->name('headless.inventory.sweep');

            $this->get('replenishment/proposals', [ReplenishmentController::class, 'proposals'])->name('headless.replenishment.proposals');
            $this->post('replenishment/generate', [ReplenishmentController::class, 'generate'])->name('headless.replenishment.generate');
            $this->get('replenishment/proposal', [ReplenishmentController::class, 'proposalForVariant'])->name('headless.replenishment.proposal');

            $this->get('goods-issues', [GoodsIssueController::class, 'index'])->name('headless.goods-issues.index');
            $this->post('goods-issues', [GoodsIssueController::class, 'store'])->name('headless.goods-issues.store');
            $this->get('goods-issues/{goodsIssue}', [GoodsIssueController::class, 'show'])->name('headless.goods-issues.show');
            $this->put('goods-issues/{goodsIssue}', [GoodsIssueController::class, 'update'])->name('headless.goods-issues.update');
            $this->delete('goods-issues/{goodsIssue}', [GoodsIssueController::class, 'destroy'])->name('headless.goods-issues.destroy');
            $this->post('goods-issues/{goodsIssue}/post', [GoodsIssueController::class, 'post'])->name('headless.goods-issues.post');

            $this->get('stock-write-offs', [StockWriteOffController::class, 'index'])->name('headless.stock-write-offs.index');
            $this->post('stock-write-offs', [StockWriteOffController::class, 'store'])->name('headless.stock-write-offs.store');
            $this->get('stock-write-offs/{stockWriteOff}', [StockWriteOffController::class, 'show'])->name('headless.stock-write-offs.show');
            $this->delete('stock-write-offs/{stockWriteOff}', [StockWriteOffController::class, 'destroy'])->name('headless.stock-write-offs.destroy');
            $this->post('stock-write-offs/{stockWriteOff}/approve', [StockWriteOffController::class, 'approve'])->name('headless.stock-write-offs.approve');
            $this->post('stock-write-offs/{stockWriteOff}/post', [StockWriteOffController::class, 'post'])->name('headless.stock-write-offs.post');

            $this->get('disposal-orders', [DisposalOrderController::class, 'index'])->name('headless.disposal-orders.index');
            $this->post('disposal-orders', [DisposalOrderController::class, 'store'])->name('headless.disposal-orders.store');
            $this->get('disposal-orders/{disposalOrder}', [DisposalOrderController::class, 'show'])->name('headless.disposal-orders.show');
            $this->put('disposal-orders/{disposalOrder}', [DisposalOrderController::class, 'update'])->name('headless.disposal-orders.update');
            $this->delete('disposal-orders/{disposalOrder}', [DisposalOrderController::class, 'destroy'])->name('headless.disposal-orders.destroy');
            $this->post('disposal-orders/{disposalOrder}/execute', [DisposalOrderController::class, 'execute'])->name('headless.disposal-orders.execute');

            $this->get('inventory-transfers', [InventoryTransferController::class, 'index'])->name('headless.inventory-transfers.index');
            $this->post('inventory-transfers', [InventoryTransferController::class, 'store'])->name('headless.inventory-transfers.store');
            $this->get('inventory-transfers/{transfer}', [InventoryTransferController::class, 'show'])->name('headless.inventory-transfers.show');
            $this->delete('inventory-transfers/{transfer}', [InventoryTransferController::class, 'destroy'])->name('headless.inventory-transfers.destroy');
            $this->post('inventory-transfers/{transfer}/post', [InventoryTransferController::class, 'post'])->name('headless.inventory-transfers.post');

            $this->get('inventory-adjustments', [InventoryAdjustmentController::class, 'index'])->name('headless.inventory-adjustments.index');
            $this->get('inventory-adjustments/{adjustment}', [InventoryAdjustmentController::class, 'show'])->name('headless.inventory-adjustments.show');
            $this->post('inventory-adjustments/{adjustment}/post', [InventoryAdjustmentController::class, 'post'])->name('headless.inventory-adjustments.post');

            $this->get('bins', [BinController::class, 'index'])->name('headless.bins.index');
            $this->post('bins', [BinController::class, 'store'])->name('headless.bins.store');
            $this->get('bins/{bin}', [BinController::class, 'show'])->name('headless.bins.show');
            $this->put('bins/{bin}', [BinController::class, 'update'])->name('headless.bins.update');
            $this->delete('bins/{bin}', [BinController::class, 'destroy'])->name('headless.bins.destroy');
            $this->get('bins/{bin}/contents', [BinController::class, 'contents'])->name('headless.bins.contents');
        };
    }

    /**
     * Batches — lot/batch master CRUD plus the lookup helpers the
     * warehouse team needs (near-expiry scan, manual quarantine).
     *
     *  - GET    batches                                          (headless.batches.index)
     *  - POST   batches                                          (headless.batches.store)
     *  - GET    batches/{batch}                                  (headless.batches.show)
     *  - PUT    batches/{batch}                                  (headless.batches.update)
     *  - DELETE batches/{batch}                                  (headless.batches.destroy)
     *  - GET    batches/near-expiry                              (headless.batches.near-expiry)
     *  - POST   batches/{batch}/quarantine                       (headless.batches.quarantine)
     */
    public function headlessBatches(): callable
    {
        return function () {
            $this->get('batches', [BatchController::class, 'index'])->name('headless.batches.index');
            $this->post('batches', [BatchController::class, 'store'])->name('headless.batches.store');
            $this->get('batches/near-expiry', [BatchController::class, 'nearExpiry'])->name('headless.batches.near-expiry');
            $this->get('batches/{batch}', [BatchController::class, 'show'])->name('headless.batches.show');
            $this->put('batches/{batch}', [BatchController::class, 'update'])->name('headless.batches.update');
            $this->delete('batches/{batch}', [BatchController::class, 'destroy'])->name('headless.batches.destroy');
            $this->post('batches/{batch}/quarantine', [BatchController::class, 'quarantine'])->name('headless.batches.quarantine');
        };
    }

    /**
     * Serial numbers — unit-level tracking. CRUD plus the
     * state-machine verbs that mirror a unit's real-world lifecycle.
     *
     *  - GET    serial-numbers                                   (headless.serial-numbers.index)
     *  - POST   serial-numbers                                   (headless.serial-numbers.store)
     *  - GET    serial-numbers/{serialNumber}                    (headless.serial-numbers.show)
     *  - PUT    serial-numbers/{serialNumber}                    (headless.serial-numbers.update)
     *  - DELETE serial-numbers/{serialNumber}                    (headless.serial-numbers.destroy)
     *  - POST   serial-numbers/{serialNumber}/assign             (headless.serial-numbers.assign)
     *  - POST   serial-numbers/{serialNumber}/return             (headless.serial-numbers.return)
     *  - POST   serial-numbers/{serialNumber}/repair             (headless.serial-numbers.repair)
     *  - POST   serial-numbers/{serialNumber}/retire             (headless.serial-numbers.retire)
     *  - GET    serial-numbers/{serialNumber}/history            (headless.serial-numbers.history)
     */
    public function headlessSerials(): callable
    {
        return function () {
            $this->get('serial-numbers', [SerialNumberController::class, 'index'])->name('headless.serial-numbers.index');
            $this->post('serial-numbers', [SerialNumberController::class, 'store'])->name('headless.serial-numbers.store');
            $this->get('serial-numbers/{serialNumber}', [SerialNumberController::class, 'show'])->name('headless.serial-numbers.show');
            $this->put('serial-numbers/{serialNumber}', [SerialNumberController::class, 'update'])->name('headless.serial-numbers.update');
            $this->delete('serial-numbers/{serialNumber}', [SerialNumberController::class, 'destroy'])->name('headless.serial-numbers.destroy');
            $this->post('serial-numbers/{serialNumber}/assign', [SerialNumberController::class, 'assign'])->name('headless.serial-numbers.assign');
            $this->post('serial-numbers/{serialNumber}/return', [SerialNumberController::class, 'markReturned'])->name('headless.serial-numbers.return');
            $this->post('serial-numbers/{serialNumber}/repair', [SerialNumberController::class, 'markUnderRepair'])->name('headless.serial-numbers.repair');
            $this->post('serial-numbers/{serialNumber}/retire', [SerialNumberController::class, 'retire'])->name('headless.serial-numbers.retire');
            $this->get('serial-numbers/{serialNumber}/history', [SerialNumberController::class, 'history'])->name('headless.serial-numbers.history');
        };
    }

    /**
     * Production orders — full lifecycle (planned → in_progress → completed).
     *
     *  - GET    production-orders                                (headless.production-orders.index)
     *  - POST   production-orders                                (headless.production-orders.store)
     *  - GET    production-orders/{productionOrder}              (headless.production-orders.show)
     *  - PUT    production-orders/{productionOrder}              (headless.production-orders.update)
     *  - DELETE production-orders/{productionOrder}              (headless.production-orders.destroy)
     *  - POST   production-orders/{productionOrder}/consume      (headless.production-orders.consume)
     *  - POST   production-orders/{productionOrder}/complete     (headless.production-orders.complete)
     */
    public function headlessProduction(): callable
    {
        return function () {
            $this->get('production-orders', [ProductionOrderController::class, 'index'])->name('headless.production-orders.index');
            $this->post('production-orders', [ProductionOrderController::class, 'store'])->name('headless.production-orders.store');
            $this->get('production-orders/{productionOrder}', [ProductionOrderController::class, 'show'])->name('headless.production-orders.show');
            $this->put('production-orders/{productionOrder}', [ProductionOrderController::class, 'update'])->name('headless.production-orders.update');
            $this->delete('production-orders/{productionOrder}', [ProductionOrderController::class, 'destroy'])->name('headless.production-orders.destroy');
            $this->post('production-orders/{productionOrder}/consume', [ProductionOrderController::class, 'consume'])->name('headless.production-orders.consume');
            $this->post('production-orders/{productionOrder}/complete', [ProductionOrderController::class, 'complete'])->name('headless.production-orders.complete');
        };
    }
}
