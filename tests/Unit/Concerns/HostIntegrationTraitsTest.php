<?php

declare(strict_types=1);

use Headless\Accounting\Banking\BankAccount;
use Headless\Accounting\Contracts\Auditable;
use Headless\Accounting\Contracts\Bankable;
use Headless\Accounting\Contracts\CustomerOwner;
use Headless\Accounting\Contracts\Discountable;
use Headless\Accounting\Contracts\EmployeeLinkable;
use Headless\Accounting\Contracts\InvoiceRenderer;
use Headless\Accounting\Contracts\NotificationRecipient;
use Headless\Accounting\Contracts\OrderSubject;
use Headless\Accounting\Contracts\Payable;
use Headless\Accounting\Contracts\Stockable;
use Headless\Accounting\Contracts\Taxable;
use Headless\Accounting\Contracts\TaxRateResolver;
use Headless\Accounting\Models\Discount;
use Headless\Accounting\Models\Invoice;
use Headless\Accounting\Tests\Fixtures\FakeAddressOwner;
use Headless\Accounting\Tests\Fixtures\FakeApprovalSubject;
use Headless\Accounting\Tests\Fixtures\FakeAuditable;
use Headless\Accounting\Tests\Fixtures\FakeBankableProxy;
use Headless\Accounting\Tests\Fixtures\FakeCarrierResolver;
use Headless\Accounting\Tests\Fixtures\FakeChangeHistory;
use Headless\Accounting\Tests\Fixtures\FakeCustomerOwner;
use Headless\Accounting\Tests\Fixtures\FakeDiscountable;
use Headless\Accounting\Tests\Fixtures\FakeEmployeeOwner;
use Headless\Accounting\Tests\Fixtures\FakeEventStreamer;
use Headless\Accounting\Tests\Fixtures\FakeHasDocumentsModel;
use Headless\Accounting\Tests\Fixtures\FakeHasPayments;
use Headless\Accounting\Tests\Fixtures\FakeInvoiceRenderer;
use Headless\Accounting\Tests\Fixtures\FakeModel;
use Headless\Accounting\Tests\Fixtures\FakeNotificationRecipient;
use Headless\Accounting\Tests\Fixtures\FakeNumberSeries;
use Headless\Accounting\Tests\Fixtures\FakePayable;
use Headless\Accounting\Tests\Fixtures\FakePayableCustomAttributes;
use Headless\Accounting\Tests\Fixtures\FakePayableSubject;
use Headless\Accounting\Tests\Fixtures\FakeStockable;
use Headless\Accounting\Tests\Fixtures\FakeTaxable;
use Headless\Accounting\Tests\Fixtures\FakeTaxableCustom;
use Headless\Accounting\Tests\Fixtures\FakeTaxableProxyHost;
use Headless\Accounting\Tests\Fixtures\FakeTaxRateResolver;
use Headless\Accounting\Tests\Fixtures\FakeWorkspace;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Events\Dispatcher;

beforeEach(function () {
    // Register a minimal container with the package's default config
    // so Config::string(...) returns sensible values when called from
    // the trait helpers.
    $container = new Container;
    Container::setInstance($container);
    $container->instance('config', new Repository([
        'headless-accounting' => [
            'table_prefix' => 'ha_',
            'currency' => ['default' => 'EUR', 'rounding' => 'half_even'],
            'channels' => ['default' => 'web'],
            'locale' => ['default' => 'en'],
        ],
    ]));

    // Set up a minimal in-memory database so Eloquent relation methods
    // (morphOne, morphMany, …) can be instantiated without Testbench.
    $capsule = new Capsule($container);
    $capsule->setEventDispatcher(new Dispatcher($container));
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
});

describe('Host-integration traits', function () {

    it('ImplementsPayable makes a host model a first-class Payable', function () {
        $model = new FakePayable([
            'total_minor' => 4999,
            'paid_minor' => 1999,
            'currency' => 'EUR',
        ]);

        expect($model)->toBeInstanceOf(Payable::class);
        expect($model->totalDue())->toBe(4999);
        expect($model->totalPaid())->toBe(1999);
        expect($model->balanceDue())->toBe(3000);
        expect($model->currency())->toBe('EUR');
    });

    it('ImplementsPayable respects custom attribute overrides', function () {
        $model = new FakePayableCustomAttributes([
            'total_minor' => 1000,
            'currency' => 'USD',
        ]);

        expect($model->totalDue())->toBe(1000);
        expect($model->currency())->toBe('USD');
    });

    it('ImplementsTaxable returns the configured tax class id', function () {
        $model = new FakeTaxable(['tax_class_id' => 42]);

        expect($model)->toBeInstanceOf(Taxable::class);
        expect($model->taxClassId())->toBe(42);
    });

    it('ImplementsTaxable honors a custom attribute name', function () {
        $model = new FakeTaxableCustom(['vat_class_id' => 99]);

        expect($model->taxClassId())->toBe(99);
    });

    it('ImplementsStockable reports tracking decisions from columns', function () {
        $model = new FakeStockable([
            'stock_tracked' => true,
            'batch_tracked' => true,
            'serial_tracked' => false,
            'safety_stock' => 12,
        ]);

        expect($model)->toBeInstanceOf(Stockable::class);
        expect($model->isStockTracked())->toBeTrue();
        expect($model->isBatchTracked())->toBeTrue();
        expect($model->isSerialTracked())->toBeFalse();
        expect($model->safetyStock())->toBe(12);
    });

    it('ImplementsDiscountable wires the two relations expected by the engine', function () {
        $model = new FakeDiscountable;

        expect($model)->toBeInstanceOf(Discountable::class);
        expect($model->discounts())->toBeInstanceOf(MorphMany::class);
        expect($model->discountable())->toBeInstanceOf(MorphTo::class);
        expect($model->acceptsDiscount(new Discount))->toBeTrue();
    });

    it('HasCustomer exposes a polymorphic 1:1 to the package Customer', function () {
        $user = new FakeCustomerOwner;

        expect($user)->toBeInstanceOf(CustomerOwner::class);
        expect($user->customerRelation())->toBeInstanceOf(MorphOne::class);
        expect($user->customer())->toBeInstanceOf(MorphOne::class);
    });

    it('HasManyCustomers + HasVendor expose MorphMany relations on workspaces', function () {
        $workspace = new FakeWorkspace;

        expect($workspace->customers())->toBeInstanceOf(MorphMany::class);
        expect($workspace->vendors())->toBeInstanceOf(MorphMany::class);
    });

    it('HasAddresses wires a polymorphic Address book', function () {
        $owner = new FakeAddressOwner;

        expect($owner->addresses())->toBeInstanceOf(MorphMany::class);
        expect($owner->addressesOfType('billing'))->toBeInstanceOf(MorphMany::class);
    });

    it('HasEmployee exposes the polymorphic 1:1 Employee relation', function () {
        $user = new FakeEmployeeOwner;

        expect($user)->toBeInstanceOf(EmployeeLinkable::class);
        expect($user->employee())->toBeInstanceOf(MorphOne::class);
    });

    it('BankableProxy produces a Bankable-shaped object backed by host columns', function () {
        $model = new FakeBankableProxy;
        $bankable = $model->bankAccountProxy();

        expect($bankable)->toBeInstanceOf(Bankable::class);
        expect($bankable->iban())->toBe('FR1420041010050500013M02606');
        expect($bankable->bic())->toBe('CRLYFRPP');
        expect($bankable->currency())->toBe('EUR');
        expect($bankable->isDefault())->toBeTrue();
    });

    it('TaxableProxy produces a Taxable-shaped object on demand', function () {
        $host = new FakeTaxableProxyHost;

        expect($host->proxyTaxable())->toBeInstanceOf(Taxable::class);
        expect($host->proxyTaxable()->taxClassId())->toBe(7);
        expect($host->proxyTaxable()->taxContext())->toBe(['digital' => true]);
    });

    it('the package BankAccount implementation satisfies Bankable for free', function () {
        $bank = new BankAccount([
            'currency' => 'EUR',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'is_default' => true,
        ]);

        expect($bank)->toBeInstanceOf(Bankable::class);
        expect($bank->iban())->toBe('DE89370400440532013000');
        expect($bank->bic())->toBe('COBADEFFXXX');
        expect($bank->currency())->toBe('EUR');
        expect($bank->isDefault())->toBeTrue();
    });

    it('TaxRateResolver contract exposes name and configuration flag', function () {
        $resolver = new FakeTaxRateResolver;

        expect($resolver)->toBeInstanceOf(TaxRateResolver::class);
        expect($resolver->name())->toBe('fake');
        expect($resolver->isConfigured())->toBeTrue();
        expect(iterator_to_array($resolver->resolve([])))->toBe([]);
    });

    it('CarrierResolver contract returns a structured quote list', function () {
        $resolver = new FakeCarrierResolver;

        $quotes = iterator_to_array($resolver->quote([
            'origin_country' => 'FR',
            'origin_postal' => '75001',
            'destination_country' => 'DE',
            'destination_postal' => '10115',
            'weight_grams' => 1500,
            'length_mm' => null,
            'width_mm' => null,
            'height_mm' => null,
            'quantity' => 1,
            'declared_value_minor' => 4999,
            'currency' => 'EUR',
        ]));

        expect($resolver->name())->toBe('fake');
        expect($quotes)->toHaveCount(1);
        expect($quotes[0]['carrier'])->toBe('fake-courier');
        expect($quotes[0]['cost_minor'])->toBe(699);
        expect($quotes[0]['currency'])->toBe('EUR');
        expect($quotes[0]['eta_days_to'])->toBe(3);
    });

    it('NumberSeries contract drives a fully configurable sequence', function () {
        $series = new FakeNumberSeries;

        expect($series->next('order', FakeModel::class))->toBe('ORDER-'.date('Y').'-000001');
        expect($series->next('order', FakeModel::class, ['prefix' => 'SO']))->toBe('SO-'.date('Y').'-000002');

        $day = $series->nextDaily('invoice', FakeModel::class, ['prefix' => 'INV', 'padding' => 4]);
        expect($day)->toStartWith('INV-'.date('Ymd').'-');
        expect(strlen($day))->toBe(strlen('INV-'.date('Ymd').'-0001'));

        expect($series->matchesFormat('order', 'ORD-2026-000123'))->toBeTrue();
        expect($series->matchesFormat('order', 'custom-string'))->toBeFalse();
    });

    it('OrderSubject contract describes a host-side booking to the package', function () {
        $subject = new FakePayableSubject;

        expect($subject)->toBeInstanceOf(OrderSubject::class);
        expect($subject->currency())->toBe('EUR');
        expect($subject->channel())->toBeString();
        expect($subject->shippingMinor())->toBe(599);
        expect($subject->discountTotalMinor())->toBe(500);

        $lines = iterator_to_array($subject->candidateLines());
        expect($lines)->toHaveCount(2);
        expect($lines[0])->toMatchArray([
            'variant_id' => 1,
            'quantity' => 2,
            'unit_price_minor' => 1500,
            'currency' => 'EUR',
        ]);
    });

    it('HasPayments hosts get a polymorphic payments() relation', function () {
        $model = new FakeHasPayments;

        expect($model->payments())->toBeInstanceOf(MorphMany::class);
        expect(method_exists($model, 'isPaid'))->toBeTrue();
    });

    it('HasChangeHistory exposes the polymorphic history relation', function () {
        $model = new FakeChangeHistory;
        expect($model->changeHistory())->toBeInstanceOf(MorphMany::class);
    });

    it('RecordsDomainEvents exposes the package event-stream table', function () {
        $model = new FakeEventStreamer;
        expect(method_exists($model, 'events'))->toBeTrue();
        expect(method_exists($model, 'recordEvent'))->toBeTrue();
    });

    it('HasDocuments exposes a polymorphic relation to DocumentAttachment', function () {
        $model = new FakeHasDocumentsModel;
        expect($model->documents())->toBeInstanceOf(MorphMany::class);
    });

    it('HasApprovals exposes the ApprovalInstance relation', function () {
        $model = new FakeApprovalSubject;
        expect($model->approvalInstances())->toBeInstanceOf(MorphMany::class);
    });

    it('Auditable contract surfaces identifier, actor and context', function () {
        $model = new FakeAuditable(['id' => 42]);

        expect($model)->toBeInstanceOf(Auditable::class);
        expect($model->auditIdentifier())->toBe('FAKE-42');
        expect($model->auditActor())->toBeNull();
        expect($model->auditContext())->toBe(['source' => 'phpunit']);
    });

    it('NotificationRecipient contract returns addresses and locale', function () {
        $recipient = new FakeNotificationRecipient;

        expect($recipient)->toBeInstanceOf(NotificationRecipient::class);
        expect($recipient->displayName())->toBe('Test User');
        expect(iterator_to_array($recipient->notificationAddresses()))->toBe(['test@example.com', '+33000000000']);
        expect($recipient->preferredLocale())->toBe('fr-FR');
    });

    it('InvoiceRenderer contract supports the chosen output format', function () {
        $renderer = new FakeInvoiceRenderer;

        $invoice = new Invoice(['number' => 'INV-2026-000001']);
        expect($renderer)->toBeInstanceOf(InvoiceRenderer::class);
        expect($renderer->supportsFormat('pdf'))->toBeTrue();
        expect($renderer->supportsFormat('csv'))->toBeFalse();
        expect($renderer->render($invoice, 'pdf', 'en'))->toBe('FAKE-pdf-en-INV-2026-000001');
    });
});
