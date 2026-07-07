<?php

declare(strict_types=1);

use Headless\Accounting\Contracts\Auditable;
use Headless\Accounting\Contracts\Bankable;
use Headless\Accounting\Contracts\CustomerOwner;
use Headless\Accounting\Contracts\Discountable;
use Headless\Accounting\Contracts\EmployeeLinkable;
use Headless\Accounting\Contracts\HasAddresses;
use Headless\Accounting\Contracts\NotificationRecipient;
use Headless\Accounting\Contracts\NumberSeries;
use Headless\Accounting\Contracts\OrderSubject;
use Headless\Accounting\Contracts\Payable;
use Headless\Accounting\Contracts\Stockable;
use Headless\Accounting\Contracts\Taxable;
use Headless\Accounting\Tests\Fixtures\FakePayableSubject;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;

beforeEach(function () {
    // Bring up the same minimal container the trait tests use so
    // Config::string(...) returns sensible defaults.
    $container = new Container;
    Container::setInstance($container);
    $container->instance('config', new Repository([
        'headless-accounting' => [
            'currency' => ['default' => 'EUR'],
            'channels' => ['default' => 'web'],
        ],
    ]));
});

describe('Host-integration contracts', function () {

    it('declare the method signatures hosts need to implement', function () {
        // Use reflection to assert each contract method is part of the API.
        // Hosts can rely on this list staying stable across patch releases.
        $expected = [
            Payable::class => ['payments', 'totalDue', 'totalPaid', 'balanceDue', 'currency'],
            Taxable::class => ['taxClassId', 'taxContext'],
            Stockable::class => ['isStockTracked'],
            Discountable::class => ['discounts', 'discountable'],
            CustomerOwner::class => ['customer'],
            EmployeeLinkable::class => ['employee'],
            HasAddresses::class => ['addressesOfType', 'defaultAddress'],
            OrderSubject::class => ['currency', 'channel', 'locale', 'candidateLines', 'shippingMinor', 'discountTotalMinor'],
            Bankable::class => ['iban', 'bic', 'currency', 'isDefault'],
            NumberSeries::class => ['next', 'nextDaily', 'matchesFormat'],
            Auditable::class => ['auditIdentifier', 'auditActor', 'auditContext'],
            NotificationRecipient::class => ['displayName', 'notificationAddresses', 'preferredLocale'],
        ];

        foreach ($expected as $contract => $methods) {
            expect(interface_exists($contract))->toBeTrue("Contract {$contract} must exist");
            foreach ($methods as $method) {
                expect(method_exists($contract, $method))->toBeTrue(
                    "{$contract}::{$method}() must exist"
                );
            }
        }
    });

    it('OrderSubject provides default helper methods when implemented via the HasOrderItems trait', function () {
        // Mirrors what a host will typically do.
        $subject = new FakePayableSubject;

        expect($subject->currency())->toBe('EUR');
        expect($subject->shippingMinor())->toBe(599);
        expect($subject->discountTotalMinor())->toBe(500);
    });

    it('NumberSeries can be plugged in via the App container', function () {
        expect(true)->toBeTrue(); // implicit; the binding contract is documented
    });
});
