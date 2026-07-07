<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pest Configuration
|--------------------------------------------------------------------------
|
| Every Pest test in the Feature/ and Integration/ folders boots the
| package through Orchestra Testbench against an in-memory SQLite database.
| Unit tests run without any framework container.
*/

use Headless\Accounting\Currency\Money;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Tests\TestCase;
use Headless\Accounting\Tests\Traits\CreatesFixtures;

uses(TestCase::class, CreatesFixtures::class)
    ->beforeEach(function () {
        $this->refreshSchema();
    })
    ->in('Feature', 'Integration', 'Enterprise');

// Unit tests are pure-PHP — no Laravel.
uses()->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/
expect()->extend('toBeMoney', function (int $amount, string $currency) {
    expect($this->value)->toBeInstanceOf(Money::class);
    expect($this->value->amount)->toBe($amount);
    expect($this->value->currency)->toBe($currency);

    return $this;
});

expect()->extend('toBeEven', function () {
    expect($this->value % 2)->toBe(0);

    return $this;
});

/*
|--------------------------------------------------------------------------
| Datasets
|--------------------------------------------------------------------------
*/
dataset('currencies', [
    'EUR' => ['EUR', 2],
    'USD' => ['USD', 2],
    'JPY' => ['JPY', 0],
    'GBP' => ['GBP', 2],
]);

dataset('payment_drivers', [
    'stripe' => ['stripe'],
    'paypal' => ['paypal'],
    'mollie' => ['mollie'],
    'braintree' => ['braintree'],
    'adyen' => ['adyen'],
    'bank_transfer' => ['bank_transfer'],
    'cash_on_delivery' => ['cash_on_delivery'],
    'check' => ['check'],
]);

dataset('order_state_transitions', [
    'cart→draft' => [Order::STATE_CART,    Order::STATE_DRAFT],
    'draft→placed' => [Order::STATE_DRAFT,   Order::STATE_PLACED],
    'placed→paid' => [Order::STATE_PLACED,  Order::STATE_PAID],
    'placed→fulfilled' => [Order::STATE_PLACED,  Order::STATE_FULFILLED],
    'placed→cancelled' => [Order::STATE_PLACED,  Order::STATE_CANCELLED],
    'paid→fulfilled' => [Order::STATE_PAID,    Order::STATE_FULFILLED],
    'paid→refunded' => [Order::STATE_PAID,    Order::STATE_REFUNDED],
    'fulfilled→closed' => [Order::STATE_FULFILLED, Order::STATE_CLOSED],
    'partially→fulfilled' => [Order::STATE_PARTIALLY_FULFILLED, Order::STATE_FULFILLED],
    'refunded→closed' => [Order::STATE_REFUNDED,  Order::STATE_CLOSED],
]);
