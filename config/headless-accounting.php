<?php

declare(strict_types=1);

use Headless\Accounting\Accounting\DefaultChartOfAccounts;
use Headless\Accounting\Discounts\BuyXGetYDiscount;
use Headless\Accounting\Discounts\Conditions\CategoryInCondition;
use Headless\Accounting\Discounts\Conditions\ChannelCondition;
use Headless\Accounting\Discounts\Conditions\CountryCondition;
use Headless\Accounting\Discounts\Conditions\CouponCodeCondition;
use Headless\Accounting\Discounts\Conditions\CustomerGroupCondition;
use Headless\Accounting\Discounts\Conditions\DateRangeCondition;
use Headless\Accounting\Discounts\Conditions\DayOfWeekCondition;
use Headless\Accounting\Discounts\Conditions\MinItemQuantityCondition;
use Headless\Accounting\Discounts\Conditions\MinOrderAmountCondition;
use Headless\Accounting\Discounts\Conditions\PaymentMethodCondition;
use Headless\Accounting\Discounts\Conditions\ProductInCondition;
use Headless\Accounting\Discounts\FixedAmountDiscount;
use Headless\Accounting\Discounts\Limitations\MaxApplicationsPerOrderLimitation;
use Headless\Accounting\Discounts\Limitations\MaxDiscountAmountLimitation;
use Headless\Accounting\Discounts\Limitations\MaxUsesPerCustomerLimitation;
use Headless\Accounting\Discounts\Limitations\PerItemLimitLimitation;
use Headless\Accounting\Discounts\Limitations\TimeWindowLimitation;
use Headless\Accounting\Discounts\Limitations\TotalUsageLimitLimitation;
use Headless\Accounting\Discounts\PercentageDiscount;
use Headless\Accounting\Models\Asset;
use Headless\Accounting\Models\Batch;
use Headless\Accounting\Models\Bill;
use Headless\Accounting\Models\Budget;
use Headless\Accounting\Models\CreditNote;
use Headless\Accounting\Models\Customer;
use Headless\Accounting\Models\Discount;
use Headless\Accounting\Models\DisposalOrder;
use Headless\Accounting\Models\ExpenseClaim;
use Headless\Accounting\Models\GoodsIssue;
use Headless\Accounting\Models\Invoice;
use Headless\Accounting\Models\JournalEntry;
use Headless\Accounting\Models\Loan;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\Payment;
use Headless\Accounting\Models\Product;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\Project;
use Headless\Accounting\Models\SerialNumber;
use Headless\Accounting\Models\Shipment;
use Headless\Accounting\Models\StockWriteOff;
use Headless\Accounting\Models\Subscription;
use Headless\Accounting\Models\Vendor;
use Headless\Accounting\Payments\Drivers\AdyenDriver;
use Headless\Accounting\Payments\Drivers\BankTransferDriver;
use Headless\Accounting\Payments\Drivers\BraintreeDriver;
use Headless\Accounting\Payments\Drivers\CashOnDeliveryDriver;
use Headless\Accounting\Payments\Drivers\CheckDriver;
use Headless\Accounting\Payments\Drivers\MollieDriver;
use Headless\Accounting\Payments\Drivers\PayPalDriver;
use Headless\Accounting\Payments\Drivers\StripeDriver;
use Headless\Accounting\Payments\Manager;
use Headless\Accounting\Procurement\GoodsReceipt;
use Headless\Accounting\Procurement\PurchaseOrder;
use Headless\Accounting\Procurement\PurchaseRequest;
use Headless\Accounting\Procurement\PurchaseReturn;
use Headless\Accounting\Sales\DeliveryNote;
use Headless\Accounting\Sales\Quotation;
use Headless\Accounting\Sales\SalesOrder;
use Headless\Accounting\Sales\SalesReturn;
use Headless\Accounting\Support\RoundingMode;
use Headless\Accounting\Tax\Providers\EcbExchangeRateProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Table prefix
    |--------------------------------------------------------------------------
    | All package tables will be prefixed with this value so they live cleanly
    | alongside application tables. Override per-environment. Changes require
    | fresh migrations; not safe to flip on a populated database.
    */
    'table_prefix' => env('HEADLESS_TABLE_PREFIX', 'ha_'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    | Currencies the package knows about (ISO-4217). The default is the
    | currency used when no explicit one is provided on a Checkout.
    */
    'currency' => [
        'default' => env('HEADLESS_CURRENCY', 'EUR'),
        'allowed' => ['EUR', 'USD', 'EGP', 'SAR', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD', 'SEK'],
        'rounding' => env('HEADLESS_CURRENCY_ROUNDING', RoundingMode::HalfEven->value),
    ],

    /*
    |--------------------------------------------------------------------------
    | Locale
    |--------------------------------------------------------------------------
    */
    'locale' => [
        'default' => env('HEADLESS_LOCALE', 'en'),
        'allowed' => ['en', 'fr', 'ar', 'es', 'de', 'it', 'nl', 'pt', 'ru', 'zh'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Channels
    |--------------------------------------------------------------------------
    | A "channel" is a sales surface (web, mobile, POS, marketplace) with
    | its own currency, locale, allowed countries and tax zone.
    */
    'channels' => [
        'default' => env('HEADLESS_CHANNEL', 'web'),
        'list' => [
            'web' => [
                'name' => 'Web Storefront',
                'currency' => env('HEADLESS_WEB_CURRENCY', 'EUR'),
                'locale' => env('HEADLESS_WEB_LOCALE', 'en'),
                'tax_zone' => env('HEADLESS_WEB_TAX_ZONE', 'eu-vat'),
                'allowed_countries' => [],
            ],
            'pos' => [
                'name' => 'Point of Sale',
                'currency' => env('HEADLESS_POS_CURRENCY', 'EUR'),
                'locale' => env('HEADLESS_POS_LOCALE', 'en'),
                'tax_zone' => env('HEADLESS_POS_TAX_ZONE'),
                'allowed_countries' => [],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Discounts
    |--------------------------------------------------------------------------
    | Map of discount types → driver class. Conditions and limitations are
    | resolved from the database; you may add new ones to the lists below.
    */
    'discounts' => [
        'default_priority' => (int) env('HEADLESS_DISCOUNT_DEFAULT_PRIORITY', 100),
        'stackable' => (bool) env('HEADLESS_DISCOUNT_STACKABLE', true),
        'drivers' => [
            'percentage' => PercentageDiscount::class,
            'fixed' => FixedAmountDiscount::class,
            'buy_x_get_y' => BuyXGetYDiscount::class,
        ],
        'conditions' => [
            'min_order_amount' => MinOrderAmountCondition::class,
            'min_item_quantity' => MinItemQuantityCondition::class,
            'product_in' => ProductInCondition::class,
            'category_in' => CategoryInCondition::class,
            'customer_group' => CustomerGroupCondition::class,
            'channel' => ChannelCondition::class,
            'date_range' => DateRangeCondition::class,
            'day_of_week' => DayOfWeekCondition::class,
            'coupon_code' => CouponCodeCondition::class,
            'country' => CountryCondition::class,
            'payment_method' => PaymentMethodCondition::class,
        ],
        'limitations' => [
            'max_per_order' => MaxApplicationsPerOrderLimitation::class,
            'max_per_customer' => MaxUsesPerCustomerLimitation::class,
            'total_usage' => TotalUsageLimitLimitation::class,
            'max_amount' => MaxDiscountAmountLimitation::class,
            'time_window' => TimeWindowLimitation::class,
            'per_item' => PerItemLimitLimitation::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax
    |--------------------------------------------------------------------------
    */
    'taxes' => [
        'inclusive' => (bool) env('HEADLESS_TAX_INCLUSIVE', false),
        'round' => env('HEADLESS_TAX_ROUND', RoundingMode::HalfEven->value),
        'default_zone' => env('HEADLESS_TAX_DEFAULT_ZONE', 'eu-vat'),
        'resolver_strategy' => env('HEADLESS_TAX_RESOLVER_STRATEGY', 'highest_rate_wins'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment gateways
    |--------------------------------------------------------------------------
    | Drivers listed here are auto-registered. Add your own entries to plug
    | additional providers; only "class" is mandatory.
    */
    'payments' => [
        'default' => env('HEADLESS_PAYMENT_DRIVER', 'stripe'),
        'manager' => Manager::class,
        'drivers' => [
            'stripe' => [
                'class' => StripeDriver::class,
                'api_url' => env('STRIPE_API_URL', 'https://api.stripe.com/v1'),
                'api_version' => env('STRIPE_API_VERSION', '2024-06-20'),
                'public_key' => env('STRIPE_PUBLIC_KEY'),
                'secret_key' => env('STRIPE_SECRET_KEY'),
                'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
                'timeout' => (float) env('STRIPE_TIMEOUT', 10.0),
                'event_prefix' => env('STRIPE_EVENT_PREFIX', 'stripe_'),
            ],
            'adyen' => [
                'class' => AdyenDriver::class,
                'sandbox_url' => env('ADYEN_SANDBOX_URL', 'https://checkout-test.adyen.com/v71'),
                'live_url' => env('ADYEN_LIVE_URL', 'https://checkout-live.adyen.com/v71'),
                'api_key' => env('ADYEN_API_KEY'),
                'merchant_account' => env('ADYEN_MERCHANT_ACCOUNT'),
                'hmac_key' => env('ADYEN_HMAC_KEY'),
                'sandbox' => (bool) env('ADYEN_SANDBOX', true),
                'timeout' => (float) env('ADYEN_TIMEOUT', 10.0),
                'event_prefix' => env('ADYEN_EVENT_PREFIX', 'adyen_'),
            ],
            'paypal' => [
                'class' => PayPalDriver::class,
                'sandbox_url' => env('PAYPAL_SANDBOX_URL', 'https://api-m.sandbox.paypal.com'),
                'live_url' => env('PAYPAL_LIVE_URL', 'https://api-m.paypal.com'),
                'client_id' => env('PAYPAL_CLIENT_ID'),
                'secret' => env('PAYPAL_CLIENT_SECRET'),
                'sandbox' => (bool) env('PAYPAL_SANDBOX', true),
                'timeout' => (float) env('PAYPAL_TIMEOUT', 10.0),
                'event_prefix' => env('PAYPAL_EVENT_PREFIX', 'paypal_'),
            ],
            'mollie' => [
                'class' => MollieDriver::class,
                'api_url' => env('MOLLIE_API_URL', 'https://api.mollie.com/v2'),
                'api_key' => env('MOLLIE_API_KEY'),
                'timeout' => (float) env('MOLLIE_TIMEOUT', 10.0),
                'event_prefix' => env('MOLLIE_EVENT_PREFIX', 'mollie_'),
            ],
            'braintree' => [
                'class' => BraintreeDriver::class,
                'sandbox_url' => env('BRAINTREE_SANDBOX_URL', 'https://api.sandbox.braintreegateway.com'),
                'live_url' => env('BRAINTREE_LIVE_URL', 'https://api.braintreegateway.com'),
                'merchant_id' => env('BRAINTREE_MERCHANT_ID'),
                'public_key' => env('BRAINTREE_PUBLIC_KEY'),
                'private_key' => env('BRAINTREE_PRIVATE_KEY'),
                'sandbox' => (bool) env('BRAINTREE_SANDBOX', true),
                'timeout' => (float) env('BRAINTREE_TIMEOUT', 10.0),
                'event_prefix' => env('BRAINTREE_EVENT_PREFIX', 'bt_'),
            ],
            'bank_transfer' => [
                'class' => BankTransferDriver::class,
                'iban' => env('BANK_IBAN'),
                'bic' => env('BANK_BIC'),
                'reference_prefix' => env('BANK_REFERENCE_PREFIX', 'ORD-'),
                'creditor_name' => env('BANK_CREDITOR', 'Headless Co.'),
                'event_prefix' => env('BANK_EVENT_PREFIX', 'wire_'),
            ],
            'cash_on_delivery' => [
                'class' => CashOnDeliveryDriver::class,
            ],
            'check' => [
                'class' => CheckDriver::class,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency conversion
    |--------------------------------------------------------------------------
    */
    'currency_conversion' => [
        'provider' => env('HEADLESS_FX_PROVIDER', EcbExchangeRateProvider::class),
        'cache' => ['ttl' => (int) env('HEADLESS_FX_CACHE_TTL', 3600)],
        'ecb' => [
            'feed_url' => env('HEADLESS_ECB_FEED_URL', 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml'),
            'timeout' => (float) env('HEADLESS_ECB_TIMEOUT', 5.0),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Accounting
    |--------------------------------------------------------------------------
    */
    'accounting' => [
        'default_currency' => env('HEADLESS_ACCOUNTING_CURRENCY', 'EUR'),
        'rounding_mode' => env('HEADLESS_ACCOUNTING_ROUNDING', RoundingMode::HalfEven->value),
        'auto_post' => (bool) env('HEADLESS_ACCOUNTING_AUTO_POST', true),
        'chart_of_accounts' => DefaultChartOfAccounts::class,
        'accounts' => [
            'sales_revenue' => env('HEADLESS_ACCOUNTING_ACCOUNT_SALES_REVENUE', '4000'),
            'inventory' => env('HEADLESS_ACCOUNTING_ACCOUNT_INVENTORY', '1400'),
            'inventory_raw' => env('HEADLESS_ACCOUNTING_ACCOUNT_INVENTORY_RAW', '1410'),
            'wip' => env('HEADLESS_ACCOUNTING_ACCOUNT_WIP', '1420'),
            'finished_goods' => env('HEADLESS_ACCOUNTING_ACCOUNT_FINISHED_GOODS', '1430'),
            'inventory_in_transit' => env('HEADLESS_ACCOUNTING_ACCOUNT_INVENTORY_IN_TRANSIT', '1440'),
            'inventory_consignment' => env('HEADLESS_ACCOUNTING_ACCOUNT_INVENTORY_CONSIGNMENT', '1450'),
            'grni' => env('HEADLESS_ACCOUNTING_ACCOUNT_GRNI', '2010'),
            'consignment_payable' => env('HEADLESS_ACCOUNTING_ACCOUNT_CONSIGNMENT_PAYABLE', '2020'),
            'cogs' => env('HEADLESS_ACCOUNTING_ACCOUNT_COGS', '5000'),
            'inventory_shrinkage' => env('HEADLESS_ACCOUNTING_ACCOUNT_INVENTORY_SHRINKAGE', '5100'),
            'inventory_damage' => env('HEADLESS_ACCOUNTING_ACCOUNT_INVENTORY_DAMAGE', '5200'),
            'production_variance' => env('HEADLESS_ACCOUNTING_ACCOUNT_PRODUCTION_VARIANCE', '5300'),
            'inventory_revaluation' => env('HEADLESS_ACCOUNTING_ACCOUNT_INVENTORY_REVALUATION', '5400'),
            'inventory_gain' => env('HEADLESS_ACCOUNTING_ACCOUNT_INVENTORY_GAIN', '4400'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Inventory & Warehouse
    |--------------------------------------------------------------------------
    | Per-company inventory valuation method and warehouse-specific tuning.
    | The valuation method can be overridden per company via the
    | `account_policies` table (key: `inventory_valuation_method`).
    */
    'inventory' => [
        /*
         * Default valuation method when no `account_policies` row exists:
         *   fifo | lifo | weighted_average | standard
         */
        'valuation_method' => env('HEADLESS_INVENTORY_METHOD', 'fifo'),

        /*
         * How long stock_reservations stay valid (minutes) when created
         * by the generic ReserveStock action. Cart/checkout reservations
         * still override this with their own expiry.
         */
        'reservation_ttl_minutes' => (int) env('HEADLESS_INVENTORY_RESERVATION_TTL_MINUTES', 15),

        /*
         * Number of days before expiration to flag a batch as near-expiry.
         */
        'near_expiry_days' => (int) env('HEADLESS_INVENTORY_NEAR_EXPIRY_DAYS', 30),

        /*
         * Auto-quarantine expired batches: when true the nightly
         * expiration sweep also quarantines the stock (does not dispose).
         */
        'auto_quarantine_expired' => (bool) env('HEADLESS_INVENTORY_AUTO_QUARANTINE', true),

        /*
         * Replenishment: enable EOQ suggestions and automatic draft PO
         * generation. StockLow events are dispatched whenever a stock
         * item crosses its reorder_point.
         */
        'replenishment' => [
            'enabled' => (bool) env('HEADLESS_INVENTORY_REPLENISHMENT', true),
            'auto_create_draft_po' => (bool) env('HEADLESS_INVENTORY_AUTO_DRAFT_PO', false),
        ],

        /*
         * FEFO (First-Expired-First-Out) is the default pick strategy
         * for batch-tracked variants; non-batch variants fall back to
         * FIFO through cost layers.
         */
        'fefo_default' => (bool) env('HEADLESS_INVENTORY_FEFO_DEFAULT', true),

        /*
         * Capacity enforcement: refuse receipts/picks when the bin
         * would exceed its declared capacity_units or max_weight_grams.
         */
        'enforce_bin_capacity' => (bool) env('HEADLESS_INVENTORY_ENFORCE_BIN_CAPACITY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Number prefixes
    |--------------------------------------------------------------------------
    | Document-number prefixes used by every action/controller that mints
    | human-friendly IDs (orders, invoices, journal entries, fulfillment
    | documents, inventory documents, etc.). Centralised here so the host
    | application can rebrand or co-exist with a legacy numbering scheme
    | without forking the package.
    */
    'number_prefixes' => [
        // Sales
        'order' => env('HEADLESS_PREFIX_ORDER', 'ORD'),
        'sales_order' => env('HEADLESS_PREFIX_SALES_ORDER', 'SO'),
        'invoice' => env('HEADLESS_PREFIX_INVOICE', 'INV'),
        'credit_note' => env('HEADLESS_PREFIX_CREDIT_NOTE', 'CN'),
        'shipment' => env('HEADLESS_PREFIX_SHIPMENT', 'SH'),

        // Procurement
        'purchase_request' => env('HEADLESS_PREFIX_PURCHASE_REQUEST', 'PR'),
        'purchase_order' => env('HEADLESS_PREFIX_PURCHASE_ORDER', 'PO'),

        // Inventory documents
        'goods_receipt' => env('HEADLESS_PREFIX_GOODS_RECEIPT', 'GR'),
        'goods_issue' => env('HEADLESS_PREFIX_GOODS_ISSUE', 'GI'),
        'inventory_transfer' => env('HEADLESS_PREFIX_INVENTORY_TRANSFER', 'TR'),
        'inventory_adjustment' => env('HEADLESS_PREFIX_INVENTORY_ADJUSTMENT', 'ADJ'),
        'stock_write_off' => env('HEADLESS_PREFIX_STOCK_WRITE_OFF', 'WO'),
        'disposal_order' => env('HEADLESS_PREFIX_DISPOSAL_ORDER', 'DSP'),
        'production_order' => env('HEADLESS_PREFIX_PRODUCTION_ORDER', 'PROD'),
        'batch' => env('HEADLESS_PREFIX_BATCH', 'BATCH'),

        // Fulfillment
        'fulfillment_plan' => env('HEADLESS_PREFIX_FULFILLMENT_PLAN', 'FP'),
        'pick_list' => env('HEADLESS_PREFIX_PICK_LIST', 'PL'),
        'pack_station' => env('HEADLESS_PREFIX_PACK_STATION', 'PK'),

        // Accounting
        'journal_entry' => env('HEADLESS_PREFIX_JOURNAL_ENTRY', 'JE'),

        // Inventory — unit-level (serial-tracked) identifiers.
        'serial_number' => env('HEADLESS_PREFIX_SERIAL_NUMBER', 'SN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */
    'http' => [
        'base_path' => env('HEADLESS_HTTP_BASE_PATH', 'api/v1/headless'),
        'middleware' => ['api', 'throttle:headless-accounting'],
        'rate_limit' => [
            'per_minute' => (int) env('HEADLESS_HTTP_RATE_LIMIT', 120),
        ],
        /*
         * When true (default) the service provider automatically calls
         * Route::headless() from routes/api.php during boot(), mounting
         * every package endpoint under `http.base_path` / `http.middleware`.
         *
         * Set to false to opt out and call the mixin yourself from your
         * host application's own routes file — e.g. so you can mount the
         * catalog group at one prefix and the webhooks group at another.
         */
        'auto_register_routes' => (bool) env('HEADLESS_HTTP_AUTO_REGISTER', true),

        /*
         * Per-group auto-registration switches. Each entry is the boolean
         * that the `Route::headless()` mixin consults to decide whether
         * to register the corresponding route group. Explicit options
         * passed to the mixin (`Route::headless(['webhooks' => false])`)
         * always take precedence over these defaults.
         *
         * Use cases:
         *  - mount only the read-only catalog/pricing groups on a public
         *    storefront and keep checkout / payments / reports on a
         *    separate prefix via your own routes file;
         *  - run an integration suite that mounts only `webhooks`;
         *  - gradually enable groups as you wire middleware for them.
         */
        'groups' => [
            'catalog' => (bool) env('HEADLESS_ROUTES_CATALOG', true),
            'pricing' => (bool) env('HEADLESS_ROUTES_PRICING', true),
            'cart' => (bool) env('HEADLESS_ROUTES_CART', true),
            'checkout' => (bool) env('HEADLESS_ROUTES_CHECKOUT', true),
            'orders' => (bool) env('HEADLESS_ROUTES_ORDERS', true),
            'payments' => (bool) env('HEADLESS_ROUTES_PAYMENTS', true),
            'invoices' => (bool) env('HEADLESS_ROUTES_INVOICES', true),
            'discounts' => (bool) env('HEADLESS_ROUTES_DISCOUNTS', true),
            'taxes' => (bool) env('HEADLESS_ROUTES_TAXES', true),
            'customers' => (bool) env('HEADLESS_ROUTES_CUSTOMERS', true),
            'addresses' => (bool) env('HEADLESS_ROUTES_ADDRESSES', true),
            'reports' => (bool) env('HEADLESS_ROUTES_REPORTS', true),
            'workflow' => (bool) env('HEADLESS_ROUTES_WORKFLOW', true),
            'webhooks' => (bool) env('HEADLESS_ROUTES_WEBHOOKS', true),
            'warehouses' => (bool) env('HEADLESS_ROUTES_WAREHOUSES', true),
            'fulfillment' => (bool) env('HEADLESS_ROUTES_FULFILLMENT', true),
            'stocktakes' => (bool) env('HEADLESS_ROUTES_STOCKTAKES', true),
            'inventory' => (bool) env('HEADLESS_ROUTES_INVENTORY', true),
            'batches' => (bool) env('HEADLESS_ROUTES_BATCHES', true),
            'serials' => (bool) env('HEADLESS_ROUTES_SERIALS', true),
            'production' => (bool) env('HEADLESS_ROUTES_PRODUCTION', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Morph map
    |--------------------------------------------------------------------------
    | Aliases used in polymorphic relations so IDs stay portable when models
    | are renamed or moved between schemas.
    */
    'morph_map' => [
        'order' => Order::class,
        'invoice' => Invoice::class,
        'credit_note' => CreditNote::class,
        'product' => Product::class,
        'variant' => ProductVariant::class,
        'customer' => Customer::class,
        'payment' => Payment::class,
        'discount' => Discount::class,
        'shipment' => Shipment::class,
        'vendor' => Vendor::class,
        'bill' => Bill::class,
        'quotation' => Quotation::class,
        'sales_order' => SalesOrder::class,
        'delivery_note' => DeliveryNote::class,
        'sales_return' => SalesReturn::class,
        'purchase_request' => PurchaseRequest::class,
        'purchase_order' => PurchaseOrder::class,
        'goods_receipt' => GoodsReceipt::class,
        'purchase_return' => PurchaseReturn::class,
        'project' => Project::class,
        'asset' => Asset::class,
        'budget' => Budget::class,
        'expense_claim' => ExpenseClaim::class,
        'subscription' => Subscription::class,
        'loan' => Loan::class,
        'journal_entry' => JournalEntry::class,
        'goods_issue' => GoodsIssue::class,
        'stock_write_off' => StockWriteOff::class,
        'disposal_order' => DisposalOrder::class,
        'batch' => Batch::class,
        'serial_number' => SerialNumber::class,
        'production_order' => ProductionOrder::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Precision map (display)
    |--------------------------------------------------------------------------
    | Per-currency minor-unit count used by display formatters and payment
    | drivers when serialising amounts. Mirrors ISO-4217 defaults; override
    | only when a non-standard listing is required.
    */
    'precision' => [
        'EUR' => 2,
        'USD' => 2,
        'GBP' => 2,
        'CHF' => 2,
        'CAD' => 2,
        'AUD' => 2,
        'SEK' => 2,
        'JPY' => 0,
    ],
];
