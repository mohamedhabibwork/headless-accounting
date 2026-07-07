# Changelog

All notable changes to **Headless Accounting** are documented in this
file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Initial release of `mohamedhabibwork/headless-accounting`.
- Polymorphic Order, Payment, Discount, Invoice, and JournalEntry models.
- Money / Currency / ExchangeRate value objects and persistence.
- Discount engine supporting percentage, fixed, and buy-X-get-Y drivers.
- 11 built-in discount conditions (date range, day of week, coupon, …).
- 6 built-in discount limitations (per-order, per-customer, total-use cap, …).
- Tax engine (classes, zones, zone members, multiple rates, compound tax).
- Multi-channel + multi-currency pricing with localized formatting.
- First-party payment drivers: Stripe, PayPal, Mollie, Braintree, bank
  transfer, cash-on-delivery, check.
- Extensible `Driver` contract with normalized `PaymentResponse` & `WebhookEvent`.
- Double-entry accounting: ChartOfAccounts, Journal, Postings, Ledger reports.
- Order state machine (`OrderStateMachine`) with auditable transitions.
- Event stream table for outbox-style integrations.
- Headless HTTP API: catalog / pricing / checkout / orders / payments /
  reports / webhooks.
- Eloquent factories for testing.
- Pest-friendly test suite with unit, feature, and integration tests.
- **Multi-warehouse fulfillment with stocktaking.**
  - `Warehouse` model with hierarchy, capabilities, opening hours,
    coordinates and a many-to-many of `WarehouseZone`s.
  - `WarehouseZone` and `WarehouseBin` models (receiving, storage,
    pick-face, packing, shipping, quarantine, returns, cross-dock).
  - `Carrier` and `ShippingRateCard` models with per-warehouse /
    per-service / per-destination rate matrices and free-shipping
    thresholds.
  - `FulfillmentPlan` model that splits an order across warehouses by
    strategy (`cheapest`, `fastest`, `closest`, `priority`, `manual`)
    and ranks carrier service options.
  - `AllocationEngine` and `CarrierRateShopper` services.
  - `FulfillmentPlanBuilder` orchestrator that combines allocation +
    rate shopping and ranks the top three carrier options.
  - `PickList` / `PickListLine` workflow (open → picking → picked →
    packed) with optimized pick sequences and shortage tracking.
  - `PackStation` workflow (open → packed → sealed → shipped) with
    carton dimensions and volumetric-weight calculation.
  - Pick / pack / ship actions: `CreatePickList`, `PickStock`,
    `PackShipment`, `ShipOrder`, `MarkDelivered`. Each one writes the
    corresponding `StockMovement` and updates the parent
    `FulfillmentPlan` / `Order` state machine.
  - `Stocktake` model with a six-state lifecycle (`draft` →
    `counting` → `counted` → `under_review` → `approved` → `posted`,
    plus `cancelled`) and `StocktakeLine` records with recount
    support, variance computation, and approved-by audit fields.
  - Stocktake actions: `CreateStocktake` (pre-populates lines from the
    current `StockItem` balances, optionally restricted by scope),
    `RecordCount` (and recounts), `SubmitStocktakeForApproval`,
    `ApproveStocktake`, `PostStocktake` (writes an `InventoryAdjustment`
    + balanced journal entry and adjusts `StockItem.on_hand`),
    `CancelStocktake`.
  - `varianceSummary()` helper that buckets counts into shortages and
    overages by SKU.
  - Shipment metadata enrichment (carrier service code, tracking URL
    template resolution, dimensions, label URL).
  - New domain events: `FulfillmentPlanCreated`, `StockPicked`,
    `ShipmentPacked`, `ShipmentShipped`, `StocktakeCreated`,
    `StocktakeApproved`, `StocktakePosted`.
  - New typed exceptions: `FulfillmentException`, `StocktakeException`.
  - HTTP API additions: `/warehouses`, `/orders/{order}/fulfillment-plan`,
    `/fulfillment/{...}`, `/stocktakes/{...}` (each behind its own
    `http.groups.*` feature flag).
  - 49 Pest tests covering warehouses, zones, bins, carriers, rate
    shopping, allocation (priority / proximity / manual), the full
    pick-pack-ship-deliver workflow, and the full stocktake lifecycle
    (with posting → balanced journal entry → InventoryAdjustment).

[Unreleased]: https://github.com/headless-accounting/headless-accounting
