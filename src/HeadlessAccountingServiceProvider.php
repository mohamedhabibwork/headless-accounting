<?php

declare(strict_types=1);

namespace Headless\Accounting;

use Headless\Accounting\Accounting\ChartOfAccounts;
use Headless\Accounting\Accounting\DefaultChartOfAccounts;
use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Accounting\Ledger;
use Headless\Accounting\Actions\Payment\AllocatePayment;
use Headless\Accounting\Actions\Payment\CheckCreditLimit;
use Headless\Accounting\Approve\WorkflowEngine;
use Headless\Accounting\Automation\RecurringJournalRunner;
use Headless\Accounting\Banking\BankReconciliationService;
use Headless\Accounting\Banking\CashPositionSnapshot;
use Headless\Accounting\Banking\PostBankTransfer;
use Headless\Accounting\Budget\BudgetVsActualService;
use Headless\Accounting\Console\InstallChartCommand;
use Headless\Accounting\Console\InstallPeriodsCommand;
use Headless\Accounting\Console\InventorySweepCommand;
use Headless\Accounting\Currency\Contracts\ExchangeRateProvider;
use Headless\Accounting\Currency\CurrencyConverter;
use Headless\Accounting\Discounts\ConditionFactory;
use Headless\Accounting\Discounts\DiscountEngine;
use Headless\Accounting\Discounts\LimitationFactory;
use Headless\Accounting\Documents\DocumentService;
use Headless\Accounting\FixedAssets\DepreciationEngine;
use Headless\Accounting\FixedAssets\DisposeAsset;
use Headless\Accounting\Fulfillment\AllocationEngine;
use Headless\Accounting\Fulfillment\CarrierRateShopper;
use Headless\Accounting\Fulfillment\FulfillmentPlanBuilder;
use Headless\Accounting\HR\PayrollCalculator;
use Headless\Accounting\Http\HeadlessRouteMethods;
use Headless\Accounting\Http\Middleware\CompanyScopeMiddleware;
use Headless\Accounting\Integration\SaasResolver;
use Headless\Accounting\Integration\WebhookDispatcher;
use Headless\Accounting\Inventory\BatchService;
use Headless\Accounting\Inventory\CostMethods;
use Headless\Accounting\Inventory\InventoryPolicyService;
use Headless\Accounting\Inventory\InventoryValuationService;
use Headless\Accounting\Inventory\ReplenishmentService;
use Headless\Accounting\Inventory\SerialNumberGenerator;
use Headless\Accounting\Inventory\SerialService;
use Headless\Accounting\Listeners\PaymentWebhookListener;
use Headless\Accounting\Loans\AmortizationSchedule;
use Headless\Accounting\Models\Cart;
use Headless\Accounting\Models\OrderItem;
use Headless\Accounting\Models\PaymentRefund;
use Headless\Accounting\MultiCurrency\CurrencyRevaluationService;
use Headless\Accounting\MultiCurrency\RealizedGainLoss;
use Headless\Accounting\Payments\Contracts\Gateway as GatewayContract;
use Headless\Accounting\Payments\Manager as PaymentsManager;
use Headless\Accounting\Payments\WebhookEvent;
use Headless\Accounting\Pricing\PricingResolver;
use Headless\Accounting\Procurement\PostGoodsReceipt;
use Headless\Accounting\Projects\RevenueRecognition;
use Headless\Accounting\Reporting\AgingReport;
use Headless\Accounting\Reporting\FinancialStatements;
use Headless\Accounting\Reporting\InventoryValuationReport;
use Headless\Accounting\Reporting\ProjectProfitabilityReport;
use Headless\Accounting\Reporting\TaxReports;
use Headless\Accounting\Sales\PostDeliveryNote;
use Headless\Accounting\Subscription\SubscriptionBillingService;
use Headless\Accounting\Tax\Providers\EcbExchangeRateProvider;
use Headless\Accounting\Tax\TaxEngine;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class HeadlessAccountingServiceProvider extends ServiceProvider
{
    public const VERSION = '1.0.0';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/headless-accounting.php', 'headless-accounting');

        // Pricing
        $this->app->singleton(PricingResolver::class);

        // Tax
        $this->app->singleton(TaxEngine::class, fn ($app) => new TaxEngine(
            (array) config('headless-accounting.taxes', [])
        ));

        // Discounts
        $this->app->singleton(ConditionFactory::class, fn () => new ConditionFactory(
            (array) config('headless-accounting.discounts.conditions', [])
        ));
        $this->app->singleton(LimitationFactory::class, fn () => new LimitationFactory(
            (array) config('headless-accounting.discounts.limitations', [])
        ));
        $this->app->singleton(DiscountEngine::class, fn ($app) => new DiscountEngine(
            $app->make(ConditionFactory::class),
            $app->make(LimitationFactory::class),
            (array) config('headless-accounting.discounts', []),
        ));
        $this->app->alias(DiscountEngine::class, 'headless.discounts');

        // FX — provider comes from config; default currency used as triangulation pivot
        $this->app->bind(ExchangeRateProvider::class, function ($app) {
            $providerClass = config(
                'headless-accounting.currency_conversion.provider',
                EcbExchangeRateProvider::class
            );

            if ($providerClass === EcbExchangeRateProvider::class) {
                return $app->makeWith(EcbExchangeRateProvider::class, [
                    'feedUrl' => (string) config('headless-accounting.currency_conversion.ecb.feed_url'),
                    'timeout' => (float) config('headless-accounting.currency_conversion.ecb.timeout', 5.0),
                ]);
            }

            return $app->make($providerClass);
        });
        $this->app->singleton(CurrencyConverter::class, fn ($app) => new CurrencyConverter(
            $app->make(ExchangeRateProvider::class),
            $app->make(CacheRepository::class),
            (array) config('headless-accounting.currency_conversion', []) + [
                'default_currency' => config('headless-accounting.currency.default'),
            ],
        ));

        // Accounting
        $this->app->bind(ChartOfAccounts::class, config(
            'headless-accounting.accounting.chart_of_accounts',
            DefaultChartOfAccounts::class
        ));
        $this->app->singleton(Journal::class, fn ($app) => new Journal($app->make(ChartOfAccounts::class)));
        $this->app->singleton(Ledger::class, fn () => new Ledger);
        $this->app->alias(Journal::class, 'headless.accounting.journal');
        $this->app->bind('headless.accounting', fn ($app) => new class($app)
        {
            public function __construct(private readonly Application $app) {}

            public function journal(): Journal
            {
                return $this->app->make(Journal::class);
            }

            public function ledger(): Ledger
            {
                return $this->app->make(Ledger::class);
            }
        });

        // Taxes (facade)
        $this->app->alias(TaxEngine::class, 'headless.tax');

        // Payments
        $this->app->singleton(PaymentsManager::class, fn ($app) => new PaymentsManager(
            (array) config('headless-accounting.payments', [])
        ));
        $this->app->afterResolving(PaymentsManager::class, function (PaymentsManager $mgr) {
            // Late-bind drivers so their configs can be injected.
            $factories = [];
            foreach (config('headless-accounting.payments.drivers', []) as $name => $cfg) {
                $class = $cfg['class'] ?? null;
                if ($class && class_exists($class)) {
                    $factories[$name] = app($class, ['config' => $cfg]);
                }
            }
            $mgr->bootstrap($factories);
        });
        $this->app->alias(PaymentsManager::class, GatewayContract::class);
        $this->app->alias(PaymentsManager::class, 'headless.payments.gateway');

        // MorphMap
        $this->registerMorphMap();

        // Factory resolver — map Headless\Accounting\Models\* → Headless\Accounting\Database\Factories\*Factory
        Factory::guessFactoryNamesUsing(function (string $modelName) {
            if (str_starts_with($modelName, 'Headless\\Accounting\\Models\\')) {
                $shortName = substr($modelName, strlen('Headless\\Accounting\\Models\\'));

                return 'Headless\\Accounting\\Database\\Factories\\'.$shortName.'Factory';
            }

            return $modelName.'Factory';
        });

        // Disable the strict morph-map check for new polymorphic models that
        // are not yet registered in the package's morph map.
        Relation::requireMorphMap(false);

        // Event listeners
        Event::listen(WebhookEvent::class, [PaymentWebhookListener::class, 'handle']);

        // Enterprise services
        $this->app->singleton(InventoryValuationService::class);
        $this->app->singleton(CostMethods::class);
        $this->app->singleton(InventoryPolicyService::class);
        $this->app->singleton(BatchService::class);
        $this->app->singleton(SerialService::class);
        $this->app->singleton(SerialNumberGenerator::class);
        $this->app->singleton(ReplenishmentService::class);

        // Fulfillment
        $this->app->singleton(AllocationEngine::class);
        $this->app->singleton(CarrierRateShopper::class);
        $this->app->singleton(FulfillmentPlanBuilder::class, fn ($app) => new FulfillmentPlanBuilder(
            $app->make(AllocationEngine::class),
            $app->make(CarrierRateShopper::class),
        ));
        $this->app->singleton(DepreciationEngine::class);
        $this->app->singleton(DisposeAsset::class);
        $this->app->singleton(PayrollCalculator::class);
        $this->app->singleton(AmortizationSchedule::class);
        $this->app->singleton(SubscriptionBillingService::class);
        $this->app->singleton(WorkflowEngine::class);
        $this->app->singleton(BankReconciliationService::class);
        $this->app->singleton(CashPositionSnapshot::class);
        $this->app->singleton(PostBankTransfer::class);
        $this->app->singleton(PostDeliveryNote::class);
        $this->app->singleton(PostGoodsReceipt::class);
        $this->app->singleton(RecurringJournalRunner::class);
        $this->app->singleton(RevenueRecognition::class);
        $this->app->singleton(BudgetVsActualService::class);
        $this->app->singleton(CurrencyRevaluationService::class);
        $this->app->singleton(RealizedGainLoss::class);
        $this->app->singleton(WebhookDispatcher::class);
        $this->app->singleton(SaasResolver::class);
        $this->app->singleton(DocumentService::class);
        $this->app->singleton(AllocatePayment::class);
        $this->app->singleton(CheckCreditLimit::class);

        // Reporting
        $this->app->bind(AgingReport::class);
        $this->app->bind(FinancialStatements::class);
        $this->app->bind(TaxReports::class);
        $this->app->bind(InventoryValuationReport::class);
        $this->app->bind(ProjectProfitabilityReport::class);

        // Middleware
        $this->app->singleton(CompanyScopeMiddleware::class);
    }

    public function boot(Router $router): void
    {
        // Publish assets
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/headless-accounting.php' => config_path('headless-accounting.php'),
            ], 'headless-accounting-config');

            $this->publishesMigrations([
                __DIR__.'/Database/Migrations' => database_path('migrations'),
            ], 'headless-accounting-migrations');

            $this->commands([
                InstallChartCommand::class,
                InstallPeriodsCommand::class,
                InventorySweepCommand::class,
            ]);
        }

        // Surface the package on `php artisan about` so consumers can
        // verify installation, version, and config status at a glance.
        AboutCommand::add('Headless Accounting', fn () => [
            'Version' => self::VERSION,
            'Migrations' => $this->migrationsAreLoaded() ? 'loaded' : 'not loaded',
            'Routes' => (bool) config('headless-accounting.http.auto_register_routes', true) ? 'auto' : 'manual',
        ]);

        // Register the package's HTTP rate limiter (consumed by 'throttle:headless-accounting')
        $this->registerRateLimiter();

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations/Enterprise');

        // Register the Route mixin (mirrors laravel/ui's UiServiceProvider pattern).
        // After this point, hosts can call Route::headless(),
        // Route::headlessCatalog(), Route::headlessCheckout(), etc. from any
        // of their own routes files, with their own prefix / middleware.
        Route::mixin(new HeadlessRouteMethods);

        // Load the package's default routes via the canonical
        // `loadRoutesFrom` helper (no-op if routes are already cached).
        // The file delegates to the mixin above so hosts that want a
        // different layout can opt out and call the mixin methods
        // themselves — see HeadlessRouteMethods for the available entry
        // points.
        if ((bool) config('headless-accounting.http.auto_register_routes', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }
    }

    private function registerRateLimiter(): void
    {
        RateLimiter::for('headless-accounting', function (Request $request) {
            $perMinute = (int) config('headless-accounting.http.rate_limit.per_minute', 120);

            return Limit::perMinute($perMinute)->by(
                optional($request->user())->getAuthIdentifier() ?? $request->ip()
            );
        });
    }

    private function migrationsAreLoaded(): bool
    {
        // The test suite binds a sentinel that signals migrations are
        // being managed manually; in every other runtime we let the
        // service provider own the schema.
        return ! $this->app->bound('headless-accounting.test.skip_migrations');
    }

    private function registerMorphMap(): void
    {
        // Eloquent ships an enum-typed alias resolver; we use the legacy array API.
        $map = array_merge(
            (array) config('headless-accounting.morph_map', []),
            [
                'ha_cart' => Cart::class,
                'ha_order_item' => OrderItem::class,
                'ha_payment_refund' => PaymentRefund::class,
                'ha_webhook_event' => Models\WebhookEvent::class,
            ],
        );
        Relation::enforceMorphMap($map);
    }
}
