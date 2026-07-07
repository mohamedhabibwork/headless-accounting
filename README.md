# Headless Accounting — Laravel Package

> A **headless**, **polymorphic**, **multi-channel**, **multi-currency**
> commerce, ERP and accounting engine for **Laravel 13 / PHP 8.4**.

`headless/accounting` is a domain layer you drop into any Laravel
application — B2C storefront, B2B portal, marketplace, POS back-office,
SaaS billing pipeline or full ERP — to expose a single, REST-shaped
JSON API for products, orders, customers, invoices, payments,
discounts, taxes, journals, inventory, fulfillment, banking,
projects, fixed assets, payroll, approvals, and reporting.

It ships **no UI** and has **no rendering dependencies**. Every
operation is exposed as a JSON resource, a domain event, or a small
action class that you orchestrate from controllers, queues,
console commands, or another service.

---

## Table of contents

1.  [Highlights](#highlights)
2.  [Capability matrix](#capability-matrix)
3.  [Installation](#installation)
4.  [Quick start](#quick-start)
5.  [Architecture](#architecture)
6.  [Polymorphic domain & MorphMap](#polymorphic-domain--morphmap)
7.  [Action pattern (CQRS-lite)](#action-pattern-cqrs-lite)
8.  [Catalog: products, variants, options, attributes](#catalog-products-variants-options-attributes)
9.  [Pricing: multi-channel, multi-currency, localized](#pricing-multi-channel-multi-currency-localized)
10. [Discount engine](#discount-engine)
11. [Tax engine](#tax-engine)
12. [Cart, checkout & orders](#cart-checkout--orders)
13. [Invoicing & credit notes](#invoicing--credit-notes)
14. [Payment system](#payment-system)
15. [Accounting: double-entry, journals, ledger, periods](#accounting-double-entry-journals-ledger-periods)
16. [Inventory & fulfillment](#inventory--fulfillment)
17. [Sales & procurement documents](#sales--procurement-documents)
18. [Multi-company tenancy](#multi-company-tenancy)
19. [Enterprise modules](#enterprise-modules)
20. [Customers & addresses](#customers--addresses)
21. [Reporting & exports](#reporting--exports)
22. [Headless HTTP API](#headless-http-api)
23. [Configuration](#configuration)
24. [Extending the package](#extending-the-package)
25. [Testing](#testing)
26. [Roadmap](#roadmap)

---

## Highlights

| Pillar         | What you get                                                                                       |
|----------------|----------------------------------------------------------------------------------------------------|
| Headless       | Zero rendering dependencies. Every operation is a JSON resource, an action class, or a domain event. |
| Polymorphic    | `Order`, `Payment`, `Discount`, `JournalEntry`, `Address`, `DiscountTarget` all bind to *any* subject via MorphMap. |
| Extensible     | Every gateway, discount type, condition, limitation, tax rule, FX provider, FX rate, report, approval workflow, and document renderer is a class behind a contract. |
| Composable     | One-action-per-class (`CreateOrder`, `AddItemToOrder`, `CapturePayment`, `PostStocktake`, …) you orchestrate. |
| Auditable      | Every state change writes an immutable `event_stream` row and posts to the journal.                 |
| Multi-channel  | Each `Channel` has its own currency, locale, allowed countries, tax zone and price lists.          |
| Multi-currency | Native ISO-4217 Money in minor units, ECB-backed FX, currency revaluation & realized gain/loss.    |
| Multi-company  | `Company` / `Branch` / `BusinessUnit` / `Department` / `CostCenter` / `ProfitCenter` scope with `NumberSeries`. |
| Testable       | Money math, condition evaluation, limitation clipping, state machines are pure value objects.       |
| Modern PHP 8.4 | `readonly`, enums, `match`, `never`, asymmetric visibility, native `intdiv`/`bcmul` for money.    |
| Framework-native | Service provider, config, migrations, factories, Pest tests — drop-in.                            |

---

## Capability matrix

| Domain              | Capabilities                                                                                                                                |
|---------------------|---------------------------------------------------------------------------------------------------------------------------------------------|
| **Catalog**         | Products, variants, options/values, attributes, barcodes, categories (closure-table), channels, master data.                                |
| **Pricing**         | Tiered prices, customer-group prices, channel prices, sale prices, tax-inclusive/exclusive, localized labels, FX-aware.                     |
| **Discounts**       | Percentage, fixed, **Buy-X-Get-Y**, line-item and order-level. 11 conditions + 6 limitations, stacking/priority, coupon codes.            |
| **Taxes**           | TaxClass × TaxZone × TaxRate; zone members (country/state/postcode/IP); compound tax; inclusive/exclusive; tax exemptions by zone.        |
| **Cart / Order**    | Persisted carts (anonymous token or customer), `Order` state machine, draft → placed → paid → fulfilled → closed (or cancelled/refunded).   |
| **Invoicing**       | Sequential numbering per legal entity, credit notes (full/partial), debits, payment schedules.                                              |
| **Payments**        | Stripe, Adyen, PayPal, Mollie, Braintree, Bank Transfer, Cash on Delivery, Check — all behind a normalized `Driver` contract + idempotent webhooks. |
| **Accounting**      | Chart of accounts, journal entries with balanced postings, ledger, fiscal year & period close, trial balance, income statement, recurring journals. |
| **Inventory**       | Multi-warehouse, zones & bins, reservations with TTL, stock movements, batch & lot tracking with FEFO, serial numbers, cost layers (FIFO/LIFO/WAC/standard), valuation. |
| **Fulfillment**     | `FulfillmentPlan` with allocation strategies (cheapest/fastest/closest/priority/manual), carrier rate-shopping, pick lists, pack stations, shipments, delivery. |
| **Stocktakes**      | Full lifecycle `draft → counting → counted → under_review → approved → posted`, recounts, variance summary, automatic journal posting.    |
| **Production**      | BoM, production orders with consume / complete, finished-goods receipt, production variance.                                               |
| **Sales**           | Quotation → SalesOrder → DeliveryNote → SalesReturn → (optional) CustomerDebitNote.                                                          |
| **Procurement**     | PurchaseRequest → PurchaseOrder → GoodsReceipt → PurchaseReturn → VendorBill → VendorCreditNote.                                            |
| **Banking**         | Bank transfers, bank reconciliation, cash position snapshots, multi-bank account balances.                                                 |
| **Multi-currency**  | Currency revaluation, realized gain/loss on settlement, FX rate provider abstraction (ECB by default).                                      |
| **Budget**          | Budget vs actual service with monthly/quarterly rollups and per-account tracking.                                                          |
| **Projects**        | Projects with milestones, tasks, time billing, WIP, revenue recognition.                                                                   |
| **Fixed assets**    | Asset categories, depreciation (straight-line/declining), revaluation, maintenance, transfer, disposal.                                    |
| **HR / Payroll**    | Employees, payroll calculator, expense claims, employee vehicles.                                                                            |
| **Loans**           | Loan accounts with amortization schedules (equal principal / annuity), installments, interest accruals.                                     |
| **Approvals**       | Multi-step workflow engine, definitions, instances, delegations, action log, personal inbox.                                                |
| **Documents**       | Versioned documents, attachments, email templates, document service.                                                                        |
| **Automation**      | Recurring journal rules with runner, scheduled reports, change history.                                                                    |
| **Tenancy**         | `Company`, `Branch`, `BusinessUnit`, `Department`, `CostCenter`, `ProfitCenter`, `NumberSeries` per scope.                                 |
| **Integration**     | Webhook dispatcher, SaaS resolver, normalized payment `WebhookEvent`.                                                                       |
| **Reporting**       | Trial balance, income statement, sales-by-channel, AR/AP aging, tax liability, inventory valuation, project profitability.                  |
| **Localization**    | Per-locale names/descriptions, translated attributes, localized money formatting.                                                            |
| **API**             | Versioned JSON-only API (`/api/v1/headless/…`) with per-group opt-in/opt-out.                                                              |

---

## Installation

```bash
composer require headless/accounting
```

Publish the config and migrations:

```bash
php artisan vendor:publish --tag=headless-accounting-config
php artisan vendor:publish --tag=headless-accounting-migrations
php artisan migrate
```

Install the default chart of accounts and open fiscal periods:

```bash
php artisan headless:install-chart
php artisan headless:install-periods
```

The service provider is auto-discovered. The default route mixin
mounts every package endpoint under `api/v1/headless`. To mount it
elsewhere or split groups across prefixes, see
[Headless HTTP API](#headless-http-api).

---

## Quick start

```php
use Headless\Accounting\Actions\Order\CreateOrder;
use Headless\Accounting\Actions\Order\AddItemToOrder;
use Headless\Accounting\Actions\Order\CalculateOrderTotals;
use Headless\Accounting\Actions\Order\PlaceOrder;
use Headless\Accounting\Actions\Payment\CapturePayment;
use Headless\Accounting\Payments\PaymentRequest;

$order = (new CreateOrder)->execute(
    customer: $request->user('customer'),
    channel:  'web',
    currency: 'EUR',
);

foreach ($request->items() as $line) {
    (new AddItemToOrder)->execute($order, $line);
}

(new CalculateOrderTotals)->execute($order);
(new PlaceOrder)->execute($order);

$payment = app(\Headless\Accounting\Payments\Contracts\Gateway::class)
    ->resolveFor($order)
    ->capture(new PaymentRequest(
        payable:  $order,
        amount:   $order->totalDue(),
        currency: $order->currency,
        method:   'stripe',
        token:    $request->paymentMethodId,
        metadata: ['channel' => 'web'],
    ));
```

A complete end-to-end example lives at
`docs/example-full-flow.php`.

---

## Architecture

```text
┌───────────────────────────────────────────────────────────────────────┐
│                       HTTP layer (headless)                          │
│   Route::headless() mixin · Controllers · JsonResource · Middleware   │
├───────────────────────────────────────────────────────────────────────┤
│                            Action layer                              │
│   CreateOrder · AddItem · CapturePayment · PostStocktake · …         │
│   (one class per operation, public execute(...): mixed)              │
├───────────────────────────────────────────────────────────────────────┤
│                          Domain engines                              │
│   PricingResolver · DiscountEngine · TaxEngine · PaymentGateway ·    │
│   AllocationEngine · FulfillmentPlanBuilder · WorkflowEngine · …     │
├───────────────────────────────────────────────────────────────────────┤
│                             Models (Eloquent)                        │
│   polymorphic relations · event trail · audit fields · company scope  │
├───────────────────────────────────────────────────────────────────────┤
│                  Infrastructure (drivers / providers)                │
│   Stripe · Adyen · PayPal · Mollie · Braintree · EcbFx · Webhook …  │
└───────────────────────────────────────────────────────────────────────┘
```

* **HTTP layer** — never assumes a view. Returns `JsonResource`.
  Routes are registered through a `laravel/ui`-style mixin
  (`Headless\Accounting\Http\HeadlessRouteMethods`) so the host can
  pick a custom prefix, middleware or namespace.
* **Action layer** — one `public function execute(...): mixed` per
  class. Easy to call from HTTP, queues, console, or another service.
  Each action opens its own `DB::transaction()` so it is safe to
  dispatch from a queue.
* **Domain engines** — stateless services that act on aggregate
  roots loaded through repositories / Eloquent. Engines are
  registered as singletons in the service provider.
* **Models** — use MorphMap (`Relation::enforceMorphMap`) and the
  package's own concerns (`BelongsToCompany`, `RecordsEvents`,
  `CompanyScope`).
* **Drivers / providers** — payment, FX, storage, search, rendering —
  all behind a contract. Add yours in config and the service provider
  boots them for you.

---

## Polymorphic domain & MorphMap

The same five polymorphic pivots power almost every relation in the
package:

```
Owner      morph:  Customer  | Company | Vendor | Employee
Subject   morph:  Product   | Variant | Subscription | Service
Target    morph:  Order     | Invoice | Quotation | Subscription | Loan
Payable   morph:  Order     | Invoice | Bill | Subscription | LoanInstallment
Source    morph:  OrderPayment | RefundSettlement | InventoryAdjustment
                     | Stocktake | ProductionOrder | BankTransfer | …
```

A configurable MorphMap keeps IDs portable across deployments:

```php
// config/headless-accounting.php
'morph_map' => [
    'order'        => \Headless\Accounting\Models\Order::class,
    'invoice'      => \Headless\Accounting\Models\Invoice::class,
    'product'      => \Headless\Accounting\Models\Product::class,
    'customer'     => \Headless\Accounting\Models\Customer::class,
    'sales_order'  => \Headless\Accounting\Sales\SalesOrder::class,
    'purchase_order'=> \Headless\Accounting\Procurement\PurchaseOrder::class,
    // …
],
```

The service provider also pins four internal aliases
(`ha_cart`, `ha_order_item`, `ha_payment_refund`,
`ha_webhook_event`) so the legacy Eloquent polymorphic columns stay
stable across upgrades.

---

## Action pattern (CQRS-lite)

Every domain operation is a single class under
`Headless\Accounting\Actions\*` with one `execute(...): mixed`
method. Actions accept serializable input, return a domain object,
fire domain events, and are invokable from queues, console, HTTP,
or another service:

```php
namespace App\Http\Controllers\Api;

use Headless\Accounting\Actions\Order\CreateOrder;
use Headless\Accounting\Actions\Order\AddItemToOrder;
use Headless\Accounting\Actions\Order\CalculateOrderTotals;

class CheckoutController
{
    public function store(CreateOrderRequest $req, CreateOrder $create)
    {
        $order = $create->execute(
            customer: $req->user('customer'),
            channel:  $req->channel(),
            currency: $req->currency(),
        );

        foreach ($req->items() as $line) {
            (new AddItemToOrder)->execute($order, $line);
        }

        return (new CalculateOrderTotals)->execute($order);
    }
}
```

Available actions include
`CreateOrder`, `AddItemToOrder`, `CalculateOrderTotals`,
`PlaceOrder`, `MarkOrderPaid`, `CancelOrder`,
`CreateDiscount`, `ApplyDiscount`, `ValidateCoupon`,
`CapturePayment`, `AllocatePayment`, `RefundPayment`,
`CheckCreditLimit`,
`CalculateLineTax`, `UpsertTaxRate`, `UpsertTaxZone`,
`PostJournalEntry`, `ReconcileAccount`, `ClosePeriod`,
`ReceiveGoods`, `IssueGoods`, `TransferStock`,
`AdjustStock`, `AdjustInventory`, `ReserveStock`,
`ReleaseExpiredReservation`, `QuarantineExpiredStock`,
`PostWriteOff`, `PostDisposalOrder`, `PostProductionOutput`,
`MaterialIssue`, `RecordSerialEvent`,
`CreateFulfillmentPlan`, `CreatePickList`, `PickStock`,
`PackShipment`, `ShipOrder`, `MarkDelivered`,
`CreateStocktake`, `RecordCount`, `SubmitStocktakeForApproval`,
`ApproveStocktake`, `PostStocktake`, `CancelStocktake`.

Each action fires a domain event (`OrderCreated`, `OrderPaid`,
`PaymentCaptured`, `JournalPosted`, `FulfillmentPlanCreated`,
`StockPicked`, `ShipmentPacked`, `StocktakeApproved`,
`StocktakePosted`, …) which drives notifications, webhooks,
audit, analytics, and ledger posting.

---

## Catalog: products, variants, options, attributes

* `Product` has many `ProductVariant`s, `ProductOption`s,
  `ProductBarcode`s and `AttributeDefinition`s.
* Variants carry their own SKU, weight, dimensions, stock and
  prices.
* Options/values (`ProductOption` / `ProductOptionValue`) drive
  variant selection.
* Attributes are key/value pairs (per locale) — supports translated
  names.
* `Category` is a self-referencing tree (closure-table) with
  channel scoping.
* `Channel` defines a sales surface (web, POS, marketplace, …)
  with its own currency, locale, allowed countries and tax zone.

```php
$shirt = Product::create([
    'name'        => 'T-Shirt',
    'sku'         => 'TS-001',
    'type'        => 'physical',
    'channel_id'  => $webChannel->id,
]);

ProductVariant::create([
    'product_id' => $shirt->id,
    'sku'        => 'TS-001-RED-M',
    'barcode'    => '4006381333931',
    'weight_grams' => 180,
]);
```

---

## Pricing: multi-channel, multi-currency, localized

Price resolution walks a resolution graph:

```
final_price =
    PriceListPrice(list, currency, customerGroup, qty, date)
  → ProductTierPrice(currency, qty)
  → VariantBasePrice(currency)
  → ProductBasePrice(currency)
```

* `PriceList` is a collection of `Price` rows bound to a `Channel`
  and/or `CustomerGroup`.
* `Money` is a value object (`amount_minor`, `currency`). All
  persistence is in **minor units** (integer cents) — no floats
  anywhere. Rounding mode is configurable (`half_even` by default).
* `CurrencyConverter` resolves FX through a pluggable
  `ExchangeRateProvider` (ECB by default; cache TTL configurable).
* `compareAt` supports strikethrough pricing.
* `tax_inclusive` is stored per price list.
* `LocalizedPrice` lets you format `"$9.99 / month"` /
  `"9,99 € / mois"`.

```php
$price = app(\Headless\Accounting\Pricing\PricingResolver::class)
    ->resolve(variant: $variant, currency: 'EUR', locale: 'fr-FR');

$price->amount;             // Money(999, EUR)
$price->compareAt;          // Money(1499, EUR) — strikethrough
$price->format('fr-FR');    // '9,99 €'
```

---

## Discount engine

A `Discount` is a *strategy* with optional *guards* (`Condition`s)
and *throttles* (`Limitation`s). Every discount has a polymorphic
target (`discountable`), a type driver, conditions, limitations,
priority, and a `stackable` flag.

```text
Discount
├── type         : PercentageDiscount | FixedAmountDiscount | BuyXGetYDiscount
├── target       : polymorphic (Order | OrderItem | Customer | Channel | Category | Variant)
├── stackable    : bool
├── priority     : int
├── conditions   : Condition[]   — passes → discount applies
└── limitations  : Limitation[]  — per-applicability caps
```

### Discount types

| Type              | Description                                                                            |
|-------------------|----------------------------------------------------------------------------------------|
| `PercentageDiscount` | `percent` 0–100, optionally bounded by `maximum_discount_amount` (minor units).     |
| `FixedAmountDiscount`| Flat reduction in a specific currency.                                              |
| `BuyXGetYDiscount`   | Buy 2 get 1 free / 50% off, with `selection` (cheapest / most_expensive / specific). |
| *Custom*            | Implement `Headless\Accounting\Discounts\Contracts\DiscountDriver` and register.    |

```php
Discount::create([
    'name' => 'Spring 10%',
    'type' => \Headless\Accounting\Discounts\PercentageDiscount::class,
    'percent' => 10,
    'maximum_discount_amount' => 5000,
    'stackable' => true,
    'priority'  => 100,
]);
```

### Conditions

Conditions are reusable, declarative, and registered in
`config('headless-accounting.discounts.conditions')`. Every
condition must return `true` for the discount to apply.

| Class                          | Purpose                                          |
|--------------------------------|--------------------------------------------------|
| `MinOrderAmountCondition`      | Order total ≥ X                                  |
| `MinItemQuantityCondition`     | Quantity of a product / category ≥ X             |
| `ProductInCondition`           | Cart contains any of these products              |
| `CategoryInCondition`          | Cart contains any product in these categories    |
| `CustomerGroupCondition`       | Customer belongs to group(s)                     |
| `ChannelCondition`             | Sale happens on channel(s)                       |
| `DateRangeCondition`           | Today between start and end                      |
| `DayOfWeekCondition`           | e.g., weekends only                              |
| `CouponCodeCondition`          | Cart includes a matching coupon                  |
| `CountryCondition`             | Ship-to country in list                          |
| `PaymentMethodCondition`       | Customer pays with a specific gateway            |

### Limitations

Limitations cap *how much / how often* the discount can apply, even
when its conditions pass.

| Class                                | Effect                                       |
|--------------------------------------|----------------------------------------------|
| `MaxApplicationsPerOrderLimitation`  | e.g., Buy-X-Get-Y only N times per cart      |
| `MaxUsesPerCustomerLimitation`       | One-shot coupons                             |
| `TotalUsageLimitLimitation`          | First N redemptions overall                  |
| `MaxDiscountAmountLimitation`        | Cap the discount at X                        |
| `TimeWindowLimitation`               | Only valid in HH:MM–HH:MM (lunchtime promo)  |
| `PerItemLimitLimitation`             | One-per-line discount                        |

Limitations live as JSON in `discount_limitations` so non-developers
can tune them. The engine returns a structured `DiscountApplication`
describing which limitations clipped the result.

---

## Tax engine

```text
TaxClass (1) ──< TaxRates (n) ──> TaxZone (1) ──< ZoneMembers (n)
                                          (country / state / postcode / ip range)
```

* `TaxClass` — groups taxable products. e.g., Standard, Reduced,
  Zero-rated, Digital services.
* `TaxZone` — a jurisdiction (country, state, postcode prefix,
  IP range, or a combination via `ZoneMember` union/intersection).
* `TaxRate` — percent of the line subtotal; can be `compound`
  (tax-on-tax) and target one or many `TaxClass`es.
* `TaxInclusive` flag on `Channel` decides whether list prices are
  tax-included (EU mode) or tax-exclusive (US mode).
* `default_zone` and `resolver_strategy` are configurable.

```php
$tax = app(\Headless\Accounting\Tax\TaxEngine::class)->resolve(
    subject: $variant,
    shipTo:  $address,
    billTo:  $billingAddress,
    context: ['customer_vat_id' => 'FR123…'],
);

$tax->lines();   // [TaxLine { rate: 20%, amount: Money(200, EUR) }, …]
$tax->total();   // Money(200, EUR)
```

The engine emits a full breakdown suitable for invoice PDFs and
OSS / One-Stop-Shop reports (`TaxReports`).

---

## Cart, checkout & orders

* `Cart` is a persisted entity keyed by an anonymous token or by
  customer.
* `Order` is the aggregate root created on `Cart → Checkout`. It
  owns `OrderItem`s, `OrderAdjustment`s (discount lines),
  `OrderTaxLine`s, `Shipment`s, and `OrderStateTransition`s.
* State machine (in `States\OrderStateMachine`):
  `cart → draft → placed → paid → partially_fulfilled →
  fulfilled → closed` (and `cancelled`, `refunded` at any point).
* Every transition records actor, timestamp, reason and writes an
  immutable event.
* `OrderNumber` is generated through a configurable
  `NumberSeries` and locked at placement.

```php
(new \Headless\Accounting\Actions\Order\PlaceOrder)->execute($order);
(new \Headless\Accounting\Actions\Order\MarkOrderPaid)->execute($order);
(new \Headless\Accounting\Actions\Order\CancelOrder)->execute($order, reason: 'customer_request');
```

---

## Invoicing & credit notes

* `Invoice` belongs to an `Order` or directly to a `Customer`.
* Sequential numbering per legal entity (`NumberSeries` /
  `InvoiceSequence`).
* `CreditNote` reverses an `Invoice` (full or partial).
* `CustomerDebitNote` / `VendorDebitNote` for adjustments.
* `PaymentSchedule` for instalments.

PDF rendering is **out of scope** by design — the package emits
JSON, an adapter layer (e.g. a Blade helper or a Spatie
`media-library` integration) can render it.

---

## Payment system

A first-class polymorphic payment engine with pluggable drivers.

### Gateway contract

```php
namespace Headless\Accounting\Payments\Contracts;

interface Gateway
{
    public function driver(string $name): Driver;
    public function drivers(): array;
    public function register(string $name, Driver $driver): void;
    public function resolveFor(string|\Headless\Accounting\Contracts\Payable $payable): Driver;
}

interface Driver
{
    public function name(): string;
    public function authorize(PaymentRequest $req): PaymentResponse;
    public function capture(PaymentRequest $req): PaymentResponse;
    public function refund(RefundRequest $req): PaymentResponse;
    public function void(PaymentRequest $req): PaymentResponse;
    public function handleWebhook(array $payload, ?string $signature): WebhookEvent;
    public function isConfigured(): bool;
}
```

A `Payable` is anything exposing a `payable(): MorphMany` of
`Payment` and a `totalDue()` accessor returning a `Money`. Drivers
are configured in `config('headless-accounting.payments.drivers')`
and auto-loaded by the service provider via `afterResolving()`.

### Drivers (first-party)

| Provider         | Capabilities                                                         |
|------------------|----------------------------------------------------------------------|
| Stripe           | Auth, capture, refund, void, 3DS, webhooks, Apple/Google Pay          |
| Adyen            | Auth + capture, refunds, webhooks, 3DS                                |
| PayPal           | Orders API v2, capture, refund, webhooks                              |
| Mollie           | All common methods, refunds, webhooks                                 |
| Braintree        | Auth + capture, vault, refunds, webhooks                             |
| Bank Transfer    | Manual reconciliation, SEPA references                                |
| Cash on Delivery | Offline, settle on delivery                                           |
| Check            | Offline, scan/OCR ready metadata                                      |

Each driver lives in `Headless\Accounting\Payments\Drivers\*Driver`
and may publish config under
`config('headless-accounting.payments')`.

### Webhooks

A single `POST /webhooks/payments/{driver}` endpoint accepts every
provider's payload, validates the signature, normalizes it to a
`WebhookEvent`, and dispatches it. Idempotency is enforced through
the `webhook_events` table (provider, event_id, received_at). The
`Integration\WebhookDispatcher` also supports outbound webhook fan-out
for arbitrary domain events.

---

## Accounting: double-entry, journals, ledger, periods

Behind the scenes the engine speaks double-entry.

* `Account` — chart of accounts (assets, liabilities, equity,
  revenue, expense). Hierarchical via a `parent_id` tree.
  Ships a `DefaultChartOfAccounts` you can install with
  `php artisan headless:install-chart`.
* `JournalEntry` — groups `Posting`s that share a date, currency,
  source and reference. **Always balanced**
  (Σ debits == Σ credits, per currency).
* `Posting` — one debit or credit on an `Account`. Immutable once
  posted.
* `Ledger` — convenience view aggregating all postings per account.
* `FiscalYear` / `AccountingPeriod` — close a period, lock entries
  (`Actions\Accounting\ClosePeriod`).
* `RecurringRule` — recurring journal templates with a
  `RecurringJournalRunner` that materialises entries on schedule.
* `ReconcileAccount` action ties bank statement lines to postings.

Domain events automatically post to the journal. Examples:

| Event                | Debit                          | Credit                            |
|----------------------|--------------------------------|-----------------------------------|
| Order placed         | Accounts Receivable            | Sales Revenue + VAT               |
| Order paid           | Cash / Stripe Clearing         | Accounts Receivable               |
| Refund captured      | Refunds (contra-revenue)       | Stripe Clearing                   |
| Inventory sold       | COGS                           | Inventory Asset                   |
| Inventory received   | Inventory Asset                | Accounts Payable / GRNI           |
| Tax due              | VAT Payable (output)           | (already credited at sale)        |
| Asset depreciation   | Depreciation Expense           | Accumulated Depreciation          |
| Currency revaluation | Unrealized FX Loss             | Unrealized FX Gain                |

`TrialBalance`, `IncomeStatement`, `BalanceSheet`,
`CashFlowStatement` helpers (`Reporting\FinancialStatements`) walk
the ledger to produce reports.

---

## Inventory & fulfillment

First-class warehouse, carrier, picking, packing, shipping,
stocktake, batch, serial and production primitives on top of the
catalog and order layer.

### Warehouses, zones, bins

```text
Warehouse ──┬─ has many ──> WarehouseZone (receiving | storage |
            │              pick_face | packing | shipping |
            │              quarantine | returns | cross_dock)
            └─< has many ─> WarehouseBin  (aisle / rack / shelf / level / position)

Warehouse
├─ capabilities: hazmat, cold_chain, oversized, virtual, consignment, …
├─ fulfillment_enabled, stocktake_enabled, is_default, priority
├─ coordinates (latitude / longitude) for great-circle distance
└─ opening_hours, timezone, contact
```

Bin capacity (`capacity_units`, `max_weight_grams`) is enforced on
receipt and pick when `inventory.enforce_bin_capacity = true`.

### Carriers & rate cards

* `Carrier` — DHL / UPS / GLS / FedEx / DPD / Colissimo / custom.
* `ShippingRateCard` — per (carrier, warehouse, service,
  destination, weight tier) with `base_cost_minor`,
  `per_kg_cost_minor`, `free_shipping_threshold_minor`.
* `CarrierRateShopper::shop($warehouse, $country, $weight, $value,
  $mode)` returns the ranked list — by `cost`, `fastest` or `eta`.

### Allocation strategies

```php
$plan = (new \Headless\Accounting\Actions\Fulfillment\CreateFulfillmentPlan(
    app(\Headless\Accounting\Fulfillment\AllocationEngine::class)
))->execute(
    order:    $order,
    lines:    [['variant_id' => 1, 'quantity' => 3, 'weight_grams' => 500]],
    strategy: \Headless\Accounting\Models\FulfillmentPlan::STRATEGY_CHEAPEST,
    // also: fastest, closest, priority, manual
);

$plan->allocations;       // [['warehouse_id' => …, 'variant_id' => 1, 'quantity' => 3], …]
$plan->shipping_options;  // top-three carrier quotes (cheapest selected)
```

### Pick / pack / ship / deliver

```text
FulfillmentPlan ──> PickList (per warehouse) ──> PickListLine
              └──> PackStation ──> Shipment ──> delivered
```

```php
(new \Headless\Accounting\Actions\Fulfillment\CreatePickList)->execute($plan);
(new \Headless\Accounting\Actions\Fulfillment\PickStock)->execute($pickList, $variantId, $qtyPicked);
(new \Headless\Accounting\Actions\Fulfillment\PackShipment)->execute($pickList, 'box-m', 1500, 300, 200, 100);
(new \Headless\Accounting\Actions\Fulfillment\ShipOrder)->execute($pack, 'dhl', 'express', 'TRK-001');
(new \Headless\Accounting\Actions\Fulfillment\MarkDelivered)->execute($shipment);
```

Every action writes the corresponding `StockMovement` (`pick` /
`ship` / `stocktake`) and updates the parent `FulfillmentPlan`
and `Order` state machines.

### Stocktakes

```text
draft → counting → counted → under_review → approved → posted
                                                      (or cancelled)
```

```php
$stocktake = (new \Headless\Accounting\Actions\Stocktake\CreateStocktake)->execute($warehouse);
(new \Headless\Accounting\Actions\Stocktake\RecordCount)->execute($stocktake, $variantId, countedQty: 27);
(new \Headless\Accounting\Actions\Stocktake\SubmitStocktakeForApproval)->execute($stocktake);
(new \Headless\Accounting\Actions\Stocktake\ApproveStocktake)->execute($stocktake);
(new \Headless\Accounting\Actions\Stocktake\PostStocktake)->execute($stocktake);
// → InventoryAdjustment + balanced journal entry,
//   StockItem.on_hand adjusted, StockMovement stamped.
```

`StocktakeLine` supports recounts (`count_round` counter) and
per-line reasons (`damaged`, `lost`, `found`, …).
`Stocktake::varianceSummary()` rolls up shortages / overages by
SKU.

### Batches, serials & production

* `Batch` + `BatchStock` with lot/expiry tracking, FEFO picking,
  near-expiry scan, manual quarantine.
* `SerialNumber` + `SerialEvent` with state machine
  (`available → assigned → returned → repair → retired`).
* `Bom` / `BomLine`, `ProductionOrder` with `consume` /
  `complete` actions, `PostProductionOutput` writes a balanced
  journal entry.

### Cost methods & valuation

* `StockItem` per variant per `Location`.
* `StockReservation` ties inventory to a draft order, with TTL.
* `StockMovement` is the immutable audit log (receive, pick,
  ship, adjust, stocktake).
* `CostLayer` + `CostMethods` (FIFO / LIFO / weighted average /
  standard) drive cost-of-goods.
* `InventoryValuationService` walks cost layers per warehouse.
* `Backorder` / `PreOrder` policies configurable per variant.

---

## Sales & procurement documents

A complete source-document flow:

```text
Sales:     Quotation → SalesOrder → DeliveryNote → (Invoice) → SalesReturn
                                                            └→ CustomerDebitNote
Procurement: PurchaseRequest → PurchaseOrder → GoodsReceipt → VendorBill
                                              └→ VendorCreditNote / VendorDebitNote
                                                  PurchaseReturn
```

Every document has its own state machine, number series
(`NumberSeries`) and balanced journal posting on post.

```php
$salesOrder  = \Headless\Accounting\Sales\SalesOrder::create([...]);
$deliveryNote = (new \Headless\Accounting\Sales\PostDeliveryNote)->execute($salesOrder);
$receipt      = (new \Headless\Accounting\Procurement\PostGoodsReceipt)->execute($purchaseOrder, $lines);
```

---

## Multi-company tenancy

Every aggregate carries a `company_id`. The `CompanyScope` global
scope and `BelongsToCompany` concern keep queries isolated per
`CompanyContext`.

* `Company` — legal entity (chart of accounts, fiscal year,
  base currency).
* `Branch` — physical location under a company.
* `BusinessUnit` — operational grouping.
* `Department`, `CostCenter`, `ProfitCenter` — reporting slices.
* `NumberSeries` — per-(company, document-type) numbering with
  prefixes, padding, year suffix.
* `AccountPolicy` — per-company overrides (valuation method,
  rounding mode, auto-post flag, …).
* `CompanyContext` — runtime-scoped accessor available in jobs,
  console commands and HTTP middleware.

```php
app(\Headless\Accounting\Tenancy\CompanyContext::class)->setCurrent($companyId);
```

A `CompanyScopeMiddleware` is registered for HTTP scoping and
listens to the `Route::headless()` mixin.

---

## Enterprise modules

| Module             | Notes                                                                                          |
|--------------------|------------------------------------------------------------------------------------------------|
| **Banking**        | `PostBankTransfer`, `BankReconciliationService`, `CashPositionSnapshot`, multi-bank balances. |
| **Budget**         | `Budget` + `BudgetLine` per period/account; `BudgetVsActualService` reports deltas.            |
| **Projects**       | `Project`, `ProjectMilestone`, `ProjectTask`, `ProjectTimeBill`, `ProjectWip`, `RevenueRecognition`. |
| **Fixed assets**   | `Asset`, `AssetCategory`, `DepreciationLine`, `AssetRevaluation`, `AssetMaintenance`, `AssetTransfer`, `AssetDisposal`, `DisposeAsset`, `DepreciationEngine`. |
| **HR / Payroll**   | `Employee`, `PayrollCalculator`, `ExpenseClaim`, `ExpenseLine`, `EmployeeVehicle`.             |
| **Loans**          | `Loan`, `LoanInstallment`, `AmortizationSchedule` (equal principal / annuity).                  |
| **Approvals**      | `WorkflowDefinition` / `Step` / `Instance` / `Action` / `Delegation`, `WorkflowEngine`.       |
| **Documents**      | `DocumentVersion`, `DocumentAttachment`, `EmailTemplate`, `DocumentService`.                   |
| **Automation**     | `RecurringRule` + `RecurringJournalRunner`, `ScheduledReport`, `ChangeHistory`.                |
| **Multi-currency** | `CurrencyRevaluationService` (unrealized FX), `RealizedGainLoss` (on settlement).              |
| **Integration**    | `WebhookDispatcher` (outbound), `SaasResolver` (per-tenant resolution).                        |

---

## Customers & addresses

* `Customer` is a polymorphic `Payable` and an `Ownable`.
* `Address` belongs to a customer **or** appears as a one-shot
  bill-to / ship-to on an order (snapshotted — orders never
  depend on current address data).
* `CustomerGroup` powers B2B tiers, tax exemptions,
  customer-specific prices and discount eligibility.
* `VatId` / `Ein` / `Abn` validated per region and stored for
  tax resolution.
* `CustomerDebitNote` for AR adjustments.

---

## Reporting & exports

Built-in `Reporter` interface ships with:

* `Reporting\FinancialStatements` — TrialBalance, IncomeStatement,
  BalanceSheet, CashFlowStatement.
* `Reporting\TaxReports` — tax liability per zone/rate, OSS export.
* `Reporting\AgingReport` — AR/AP aging (30/60/90).
* `Reporting\InventoryValuationReport` — per-warehouse, per-method.
* `Reporting\ProjectProfitabilityReport` — revenue, cost, margin.

Each accepts a structured `ReportQuery`, returns a `ReportResult`,
and can render to CSV / JSON / array. `ScheduledReport` lets you
deliver any of them on a cron.

---

## Headless HTTP API

A versioned, JSON-only API mounted via the `Route::headless()`
mixin. Default prefix `api/v1/headless`, default middleware
`['api', 'throttle:headless-accounting']`.

### Mounting

```php
// routes/api.php (host application) — opt-out of auto-registration
// and mount the groups you want under your own prefix / middleware.

Route::prefix('api/billing/v1')->middleware(['api', 'auth'])->group(function () {
    Route::headlessCatalog();
    Route::headlessCheckout();
    Route::headlessPayments();
});
```

Or control the whole mixin through config:

```php
// config/headless-accounting.php
'http' => [
    'auto_register_routes' => false,           // mount it yourself
    'groups' => [
        'catalog'    => true,
        'pricing'    => true,
        'cart'       => true,
        'checkout'   => true,
        'orders'     => true,
        'payments'   => true,
        'invoices'   => true,
        'discounts'  => true,
        'taxes'      => true,
        'customers'  => true,
        'addresses'  => true,
        'reports'    => true,
        'workflow'   => true,
        'webhooks'   => true,
        'warehouses' => true,
        'fulfillment'=> true,
        'stocktakes' => true,
        'inventory'  => true,
        'batches'    => true,
        'serials'    => true,
        'production' => true,
    ],
],
```

Per-group opt-out via env: `HEADLESS_ROUTES_CHECKOUT=false`,
`HEADLESS_ROUTES_PAYMENTS=false`, etc.

### Route groups

| Group        | Sample paths                                                                                              |
|--------------|-----------------------------------------------------------------------------------------------------------|
| catalog      | `GET /catalog/products`, `GET /catalog/products/{id}`                                                     |
| pricing      | `GET /pricing/resolve`                                                                                    |
| cart         | `POST /cart`, `GET /cart/{cartId}`, `DELETE /cart/{cartId}`, `POST /cart/{cartId}/items/{variantId}`       |
| checkout     | `POST /checkout`, `POST /orders/{orderId}/place`                                                          |
| orders       | `GET /orders/{orderId}`, `POST /orders/{orderId}/items/{variantId}`, `POST /orders/{orderId}/recalc`, `POST /orders/{orderId}/discounts`, `POST /orders/{orderId}/refunds` |
| payments     | `POST /orders/{orderId}/payments`, `POST /payments/{paymentId}/refund`                                     |
| invoices     | `GET /invoices`, `GET /invoices/{invoiceId}`                                                              |
| discounts    | `GET /discounts`, `POST /discounts`, `GET /discounts/{id}`, `PUT /discounts/{id}`, `DELETE /discounts/{id}` |
| taxes        | `GET/POST /tax/zones`, `GET/POST /tax/classes`, `GET/POST /tax/rates` (full CRUD)                          |
| customers    | `GET /customers`, `POST /customers`, `GET/PUT/DELETE /customers/{id}`                                      |
| addresses    | nested CRUD under `customers/{id}/addresses/{id}`                                                         |
| reports      | `GET /reports/trial-balance`, `GET /reports/income-statement`, `GET /reports/sales-by-channel`            |
| workflow     | definitions / steps / instances / decisions / cancel / actions / inbox / delegations                      |
| warehouses   | CRUD + `POST /warehouses/{id}/rate-shop`                                                                  |
| fulfillment  | plans, pick-lists, pack, ship, deliver                                                                    |
| stocktakes   | full lifecycle (create → count → submit → approve → post / cancel) + variance                             |
| inventory    | valuation, availability, expiring, sweep; bins; goods issues, write-offs, disposal, transfers, adjustments, replenishment |
| batches      | CRUD + near-expiry + manual quarantine                                                                    |
| serials      | CRUD + assign / return / repair / retire + history                                                        |
| production   | CRUD + consume / complete                                                                                 |
| webhooks     | `POST /webhooks/payments/{driver}` (public)                                                               |

All endpoints respond with `JsonResource` subclasses that hide
sensitive fields and add `included` relationships.

---

## Configuration

`config/headless-accounting.php` controls:

```php
return [
    'table_prefix'       => env('HEADLESS_TABLE_PREFIX', 'ha_'),

    'currency' => [
        'default'  => 'EUR',
        'allowed'  => ['EUR', 'USD', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD', 'SEK'],
        'rounding' => RoundingMode::HalfEven->value,
    ],

    'locale' => [
        'default' => 'en',
        'allowed' => ['en', 'fr', 'de', 'es', 'it', 'ja'],
    ],

    'channels' => [
        'default' => 'web',
        'list'    => [
            'web' => ['currency' => 'EUR', 'locale' => 'en', 'tax_zone' => 'eu-vat'],
            'pos' => ['currency' => 'EUR', 'locale' => 'en', 'tax_zone' => null],
        ],
    ],

    'discounts' => [
        'default_priority' => 100,
        'stackable'        => true,
        'drivers'          => [
            'percentage'  => PercentageDiscount::class,
            'fixed'       => FixedAmountDiscount::class,
            'buy_x_get_y' => BuyXGetYDiscount::class,
        ],
        'conditions'  => [ /* 11 entries */ ],
        'limitations' => [ /* 6 entries */ ],
    ],

    'taxes' => [
        'inclusive'         => false,
        'round'             => RoundingMode::HalfEven->value,
        'default_zone'      => 'eu-vat',
        'resolver_strategy' => 'highest_rate_wins',
    ],

    'payments' => [
        'default'  => 'stripe',
        'manager'  => Manager::class,
        'drivers'  => [
            'stripe'   => [/* env-driven */],
            'adyen'    => [/* env-driven */],
            'paypal'   => [/* env-driven */],
            'mollie'   => [/* env-driven */],
            'braintree'=> [/* env-driven */],
            'bank_transfer' => [/* env-driven */],
            'cash_on_delivery' => [/* env-driven */],
            'check'    => [/* env-driven */],
        ],
    ],

    'currency_conversion' => [
        'provider' => EcbExchangeRateProvider::class,
        'cache'    => ['ttl' => 3600],
        'ecb'      => ['feed_url' => '…', 'timeout' => 5.0],
    ],

    'accounting' => [
        'default_currency' => 'EUR',
        'rounding_mode'    => RoundingMode::HalfEven->value,
        'auto_post'        => true,
        'chart_of_accounts'=> DefaultChartOfAccounts::class,
        'accounts'         => [/* sales_revenue, inventory, cogs, … */],
    ],

    'inventory' => [
        'valuation_method'        => 'fifo',   // fifo|lifo|weighted_average|standard
        'reservation_ttl_minutes' => 15,
        'near_expiry_days'        => 30,
        'auto_quarantine_expired' => true,
        'replenishment'           => ['enabled' => true, 'auto_create_draft_po' => false],
        'fefo_default'            => true,
        'enforce_bin_capacity'    => true,
    ],

    'number_prefixes' => [
        // Sales
        'order'         => 'ORD',  'sales_order'  => 'SO',  'invoice'   => 'INV',
        'credit_note'   => 'CN',   'shipment'     => 'SH',
        // Procurement
        'purchase_request' => 'PR',  'purchase_order' => 'PO',
        // Inventory documents
        'goods_receipt'       => 'GR',   'goods_issue'        => 'GI',
        'inventory_transfer'  => 'TR',   'inventory_adjustment'=> 'ADJ',
        'stock_write_off'     => 'WO',   'disposal_order'     => 'DSP',
        'production_order'    => 'PROD', 'batch'              => 'BATCH',
        // Fulfillment
        'fulfillment_plan'    => 'FP',   'pick_list'          => 'PL',
        'pack_station'        => 'PK',
        // Accounting
        'journal_entry'       => 'JE',
    ],

    'http' => [
        'base_path'  => 'api/v1/headless',
        'middleware' => ['api', 'throttle:headless-accounting'],
        'rate_limit' => ['per_minute' => 120],
        'auto_register_routes' => true,
        'groups' => [/* per-group opt-in/opt-out */],
    ],

    'morph_map' => [/* order, invoice, sales_order, purchase_order, … */],
    'precision' => ['EUR' => 2, 'USD' => 2, 'JPY' => 0, /* … */],
];
```

---

## Extending the package

| Extension point              | Contract                                                                |
|------------------------------|-------------------------------------------------------------------------|
| Custom discount type         | `Headless\Accounting\Discounts\Contracts\DiscountDriver`               |
| Custom condition             | `Headless\Accounting\Discounts\Contracts\ConditionEvaluator`           |
| Custom limitation            | `Headless\Accounting\Discounts\Contracts\Limitation`                    |
| Custom tax resolver          | `Headless\Accounting\Tax\Contracts\TaxResolver`                         |
| Custom payment driver        | `Headless\Accounting\Payments\Contracts\Driver`                         |
| Custom FX provider           | `Headless\Accounting\Currency\Contracts\ExchangeRateProvider`           |
| Custom invoice renderer      | `Headless\Accounting\Contracts\InvoiceRenderer`                         |
| Custom workflow step         | `Headless\Accounting\Approve\Contracts\StepHandler`                     |
| Custom document service      | extend `Headless\Accounting\Documents\DocumentService`                  |
| Custom action                | subclass `Headless\Accounting\Actions\Action`, place under `Actions\*`, auto-resolved by container |
| Custom MorphMap alias        | add to `config('headless-accounting.morph_map')` — no migration needed  |
| Custom rounding              | `Headless\Accounting\Support\RoundingMode` enum value                   |
| Custom webhook dispatcher    | extend `Headless\Accounting\Integration\WebhookDispatcher`              |
| Custom number-series prefix  | per-document-type entry in `number_prefixes` / `NumberSeries` |

A worked example for adding your own payment driver lives at
`docs/example-custom-driver.php`.

---

## Testing

The package ships a full Pest suite. The host application can run
the same suite against an in-memory SQLite, or against its own
test database, by reusing the package's `Tests\TestCase`.

```bash
composer test              # vendor/bin/pest
composer test:unit         # vendor/bin/pest --testsuite=Unit
composer test:feature      # vendor/bin/pest --testsuite=Feature
```

Suites covered:

* **Unit** — Money, Numbers, Currency, OrderStateMachine,
  PaymentResponse, every discount driver / condition / limitation.
* **Feature** — `OrderLifecycle`, `TaxEngine`,
  `PricingResolver`, `DiscountEngine`, `PaymentDrivers`,
  `PaymentActions`, `WebhookIdempotency`, `StocktakeLifecycle`,
  warehouse / pick / pack / ship / deliver, batches, serials,
  bins, replenishment, reservations, production orders, stock
  actions, transfers, write-offs, disposals, accounting actions.
* **Integration** — full `CheckoutFlow` (cart → place → pay →
  fulfill), multi-channel pricing, multi-currency conversion,
  tax integration, period close, multi-warehouse fulfillment +
  stocktake.
* **Enterprise** — tenancy, sales & procurement, reports,
  projects, payroll, multi-currency, inventory, fixed assets,
  documents, budget, banking, approval workflows.

The package follows [Laravel Boost](../AGENTS.md) guidelines: every
change is programmatically tested. Run `vendor/bin/pint --dirty`
before committing.

---

## Roadmap

1. Subscription billing engine (recurring schedules, proration,
   dunning) — primitives already shipped
   (`Subscription`, `SubscriptionPlan`, `SubscriptionBillingService`,
   `SubscriptionInvoice`, `SaasSubscription`, `SaasPlan`).
2. Multi-vendor / marketplace splits with per-vendor settlements.
3. More valuation methods (specific-identification, retail method).
4. Plug-in ESC/POS receipt driver for POS.
5. Native ERP connectors (Datev, Odoo, QuickBooks, Sage).
6. Country tax feeds (Vertex, Avalara, TaxJar) behind a single
   `TaxResolver`.
7. Workflow automation engine (rules + signals) on top of
   `RecurringRule`.
8. Shipping label rendering adapters (DHL Express, FedEx, UPS).
9. Public OpenAPI 3.1 spec generated from controllers.

---

© Headless Accounting Authors — Released under the MIT License.