<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Default package API routes
|--------------------------------------------------------------------------
|
| Loaded automatically by HeadlessAccountingServiceProvider::loadRoutes()
| during `boot()`, provided `config('headless-accounting.http.auto_register_routes')`
| is truthy (default: true).
|
| The single line below delegates to the HeadlessRouteMethods mixin, which
| mirrors laravel/ui's AuthRouteMethods. The mixin groups routes by
| domain — catalog, pricing, checkout, payments, reports, webhooks — and
| wraps them in the prefix + middleware stack defined in
| `config('headless-accounting.http')`.
|
| Per-group opt-out via config:
|
|     // config/headless-accounting.php
|     'http' => [
|         'groups' => [
|             'catalog' => true,
|             'pricing' => true,
|             'checkout' => false,    // disabled
|             'payments' => false,    // disabled
|             'reports' => true,
|             'webhooks' => true,
|         ],
|     ],
|
| Or via environment variables:
|
|     HEADLESS_ROUTES_CHECKOUT=false
|     HEADLESS_ROUTES_PAYMENTS=false
|
| Explicit options passed to the mixin always win, e.g.:
|
|         Route::headless(['webhooks' => false]);
|
| To take full control of where the routes live (custom prefix, custom
| middleware), set `HEADLESS_HTTP_AUTO_REGISTER=false` and call the
| mixin methods yourself from your own routes file:
|
|         // routes/api.php (host application)
|         Route::prefix('api/billing/v1')->middleware(['api', 'auth'])->group(function () {
|             Route::headlessCatalog();
|             Route::headlessCheckout();
|             Route::headlessPayments();
|         });
*/

Route::headless();
