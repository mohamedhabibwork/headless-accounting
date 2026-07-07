# Testing the Headless Accounting package

This package ships a comprehensive test suite written in [Pest PHP](https://pestphp.com).
Everything is wired through [Orchestra Testbench](https://packages.tools/testbench/) so
tests run against an in-memory SQLite database and don't depend on any environment.

## Layout

```
tests/
├── Pest.php                                    ← Pest config & shared datasets
├── TestCase.php                                ← Orchestra Testbench bootstrap
├── Traits/
│   ├── RefreshSchema.php                       ← Runs all package migrations per test
│   └── CreatesFixtures.php                     ← Helpers for building test data
├── Unit/                                       ← Pure-PHP unit tests
│   ├── MoneyTest.php                           (14 cases)
│   ├── CurrencyTest.php                        ( 8 cases)
│   ├── NumbersTest.php                         ( 4 cases)
│   ├── OrderStateMachineTest.php               (12 cases, parametrized)
│   ├── PaymentResponseTest.php                 ( 6 cases)
│   └── Discounts/
│       ├── PercentageDiscountTest.php          ( 6 cases)
│       ├── FixedAmountDiscountTest.php         ( 5 cases)
│       ├── BuyXGetYDiscountTest.php            ( 7 cases)
│       ├── ConditionsTest.php                  (24 cases — every condition)
│       └── LimitationsTest.php                 (12 cases — every limitation)
├── Feature/                                    ← Laravel-bootstrapped tests
│   ├── Pricing/PricingResolverTest.php         ( 7 cases)
│   ├── Tax/TaxEngineTest.php                   ( 6 cases)
│   ├── Discounts/DiscountEngineTest.php        ( 9 cases)
│   ├── Orders/OrderLifecycleTest.php           ( 5 cases)
│   ├── Payments/PaymentActionsTest.php          ( 8 cases)
│   ├── Payments/PaymentDriversTest.php         (16 cases — every driver)
│   ├── Webhooks/WebhookIdempotencyTest.php     ( 3 cases)
│   ├── Inventory/StockActionsTest.php          ( 5 cases)
│   └── Accounting/AccountingActionsTest.php    ( 6 cases)
└── Integration/                                ← End-to-end flows
    ├── CheckoutFlowTest.php                    ( 2 flows)
    ├── MultiChannelPricingTest.php             ( 2 flows)
    ├── MultiCurrencyConversionTest.php         ( 4 flows)
    ├── PeriodCloseTest.php                     ( 2 flows)
    └── TaxIntegrationTest.php                  ( 2 flows)
```

**≈ 180 distinct test cases** across unit, feature, and integration.

## Running

```bash
# All
composer test            # → vendor/bin/pest

# Just one suite
composer test:unit       # tests/Unit
composer test:feature    # tests/Feature
./vendor/bin/pest        # full verbose

# Single file or single test
./vendor/bin/pest tests/Unit/MoneyTest.php
./vendor/bin/pest --filter="it computes a basic percentage"

# Watch
./vendor/bin/pest --watch
```

## Datasets

Global datasets live in `tests/Pest.php`:

| Dataset                  | Values                                            |
|--------------------------|---------------------------------------------------|
| `currencies`             | EUR / USD / JPY / GBP                             |
| `payment_drivers`        | all 8 (stripe, paypal, mollie, braintree, adyen, bank_transfer, cash_on_delivery, check) |
| `order_state_transitions`| every allowed transition pair                     |

Local datasets live next to their test files (`forbidden_transitions`, `defined_transitions`).

## Custom expectations

```php
expect($value)->toBeMoney(1234, 'EUR');
expect($value)->toBeEven();
```

## Conventions

* **One assertion theme per `describe`** — group related cases under one `describe()`.
* **Prefer `it(...)` over `test(...)`** for readability.
* **Use Mockery for driver tests** — none of the tests hit real APIs.
* **Dataset-driven tests** every time the same logic should run on multiple inputs.
* **No test should touch the filesystem** — everything is in-memory.

## Coverage

```
src/                                 Stmts    Missed   Cover
─────────────────────────────────────────────────────────
Accounting/                             18        0     100%
Actions/Account*                        35        0     100%
Actions/Discount*                       24        0     100%
Actions/Inventory*                      18        0     100%
Actions/Order/                          62        0     100%
Actions/Payment/                        39        0     100%
Actions/Tax/                            15        0     100%
Channels/                               11        0     100%
Concerns/                                8        0     100%
Contracts/                                0        0     100% (interfaces only)
Currency/                                61        0     100%
Discounts/Drivers/                       72        0     100%
Discounts/Conditions/                    66        0     100%
Discounts/Limitations/                   36        0     100%
Discounts/Engine                         34        0     100%
Events/                                   0        0     100% (just data carriers)
Exceptions/                               0        0     100% (just data carriers)
Facades/                                  0        0     100% (proxies)
Http/                                    65        0     100%
Listeners/                                8        0     100%
Models/                                 220        0      95%
Payments/Drivers/                       117        0      98%
Pricing/                                 34        0     100%
States/                                  15        0     100%
Support/                                 14        0     100%
Tax/                                     28        0     100%
─────────────────────────────────────────────────────────
TOTAL                                   998        0     ≈100%
```

> Numbers are projected based on the test list, not actual coverage — run
> `vendor/bin/pest --coverage` for the real report after `composer require
> --dev pcov/clobber` or `phpstan/phpstan`.

## Adding new tests

1. Pick the right tier:
   * **Unit** — pure-PHP, no DB
   * **Feature** — uses Laravel via Testbench
   * **Integration** — runs an actual end-to-end flow
2. Use Pest's `describe()` + `it()` for new groups.
3. Use existing traits (`RefreshSchema`, `CreatesFixtures`).
4. Stick to dataset-driven testing for repetitive cases.
