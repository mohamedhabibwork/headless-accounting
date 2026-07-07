# Headless Accounting — Architecture Notes

This document captures the *why* behind the package design. The
`README.md` of the package is the canonical spec; this file is a
companion that explains a few decisions that don't fit there.

## 1. Why "Actions"

We deliberately avoided the classic Laravel pattern of "Service classes
that contain many methods" and the Laravel-native "Models that
contain many methods", and instead chose one-action-per-class with
input and output DTOs.

Reasons:

| Reason                | Benefit                                                   |
|-----------------------|-----------------------------------------------------------|
| Testability           | Each action can be unit-tested with no Eloquent context.  |
| Composability         | HTTP controllers / queues / console all call `execute()`.  |
| Discovery             | Any IDE jump-to-symbol finds the next operation.           |
| Audit                 | Action classes can be serialized for replay / queues.      |
| Extensibility         | Override an action by extending the class, not the model.  |

## 2. Why Polygon Polymorphic Everywhere

Polymorphic relations were chosen over single-table-inheritance (STI)
or class-table-inheritance (CTI) because:

* Coupling with the host application: Orders can be attached to *any*
  customer-like model without forcing the package to ship a fixed
  `App\User` shape.
* Same idea for `DiscountTarget`, `OrderAdjustment`, `Address`,
  `JournalEntry::source` — they all stay portable.

`Relation::enforceMorphMap` is set in the service provider to keep
IDs stable when migrations rename or restructure.

## 3. Why "Money in Minor Units"

* Floats lose pennies; currencies are smaller than that now.
* BC math / int math makes `Money` testable in isolation, with no
  Eloquent or locale fuss.
* All display layer code (`format`) is opt-in — never automatic.

## 4. Why a Separate PaymentGateway

We considered implementing payments on top of Eloquent's "manager"
pattern (Cashier-style), and decided against it. Reasons:

* Calls to remote APIs side-effect; they belong behind a tested
  interface with idempotency.
* Drivers come and go (Stripe → Adyen → PayPal → bank…); the
  abstraction must not depend on any provider's specific shape.
* Webhooks need their own DTO (`WebhookEvent`); treating them as
  Eloquent events makes testing painful.

## 5. Why Two Tables Per Aggregate

We split each main aggregate into *header* + *lines* + *events*:

```
Order             OrderItem            EventStream
Invoice           (lines in JSON)      Payment
Payment           PaymentRefund
JournalEntry      Posting
```

This pattern keeps prices, addresses, and behaviour immutable on
the *header* once placed, while still allowing line-level mutations
before place.

## 6. Why JSON `lines` on `Invoice`

Storing line-items as JSON (rather than a separate `invoice_items`
table) keeps the package headless-friendly: invoices are typically
generated from the Order and not mutated afterwards.

If you need per-line mutations, generate Order → Invoice(s) where
each Invoice gets its own `InvoiceLine` table you add on top.

## 7. Why an Event Stream Table

Every aggregate produces immutable events in `ha_event_stream` — this
gives you:

* Audit trail for compliance.
* Outbox-style fan-out to webhooks, email, analytics.
* Deterministic replay in tests.

## 8. Why `headless.discounts` is a *Strategy*

Each discount is a class implementing `DiscountDriver`, configured
declaratively. This makes them:

* Discoverable through the config `drivers` map.
* Composable with any combination of `Condition` / `Limitation`.
* Trivial to test (no DB needed for the math).

## 9. Why A Static State Machine

The `OrderStateMachine` lives in a separate class (not on the Order)
and is pure: it only mutates state when invoked. That separation
makes it:

* Easy to reason about: this is "what's allowed", the action class
  is "what to do".
* Re-usable: it's also the input to other tooling, e.g. UI progress
  badges.

## 10. Why Polling vs Webhooks

Every provider returns a *webhook* through the same handler.
Long-poll/refresh endpoints stay available for back-office but the
canonical state machine listens to webhooks for end-user flows.

## 11. PHP 8.4 Features in Use

* `readonly` on value objects (`Money`, `PaymentRequest`, …).
* Enums (driver state in `PaymentResponse::driverState`).
* `#[AllowDynamicProperties]`-style attributes where appropriate.
* `match` for fast lookups.
* `never` return type for code that throws.

## 12. Open Threads

* Currency conversion caching — cached per (base, quote, day).
* Driver bootstrap — `afterResolving` ensures config is available;
  switch to Laravel's `register()` in real apps for clarity.
* Concurrency — `Action::execute()` uses `DB::transaction()`; pass an
  `atomic` flag or use Laravel's `withoutOverlapping()` for queues.

---

Questions, found a bug? Open an issue — pull requests welcome.
