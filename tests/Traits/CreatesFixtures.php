<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Traits;

use Headless\Accounting\Accounting\DefaultChartOfAccounts;
use Headless\Accounting\Models\Address;
use Headless\Accounting\Models\Channel;
use Headless\Accounting\Models\Currency as CurrencyModel;
use Headless\Accounting\Models\Customer;
use Headless\Accounting\Models\PriceList;
use Headless\Accounting\Models\Product;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\TaxClass;
use Headless\Accounting\Models\TaxRate;
use Headless\Accounting\Models\TaxZone;

/**
 * CreatesFixtures — convenience builders for tests that need a "ready
 * to use" stack: currencies, channel, customer, product + variant, tax
 * setup, address.
 */
trait CreatesFixtures
{
    public function makeCurrency(string $code = 'EUR', string $symbol = '€', int $decimals = 2): CurrencyModel
    {
        return CurrencyModel::query()->updateOrCreate(
            ['code' => $code],
            ['name' => $code, 'symbol' => $symbol, 'decimals' => $decimals, 'active' => true],
        );
    }

    public function makeChannel(string $code = 'web', string $currency = 'EUR', string $locale = 'en'): Channel
    {
        return Channel::query()->updateOrCreate(
            ['code' => $code],
            ['name' => ucfirst($code), 'currency' => $currency, 'locale' => $locale, 'active' => true],
        );
    }

    public function makeCustomer(?string $email = null): Customer
    {
        return Customer::factory()->create(['email' => $email ?? 'customer+'.uniqid().'@example.com']);
    }

    public function makeProduct(string $name = 'T-Shirt', int $priceMinor = 1999): ProductVariant
    {
        $product = Product::factory()->create(['name' => $name]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
        ]);

        PriceList::query()->create([
            'name' => 'Default EU',
            'code' => 'web-default-'.uniqid(),
            'scope' => 'channel',
            'scope_ref' => 'web',
            'currency' => 'EUR',
            'priority' => 100,
            'active' => true,
        ])->prices()->create([
            'subject_type' => $variant->getMorphClass(),
            'subject_id' => $variant->id,
            'amount_minor' => $priceMinor,
            'currency' => 'EUR',
            'min_quantity' => 1,
            'tax_inclusive' => false,
        ]);

        return $variant;
    }

    public function makeAddress(string $countryCode = 'FR', ?string $region = null, ?string $postal = null, ?Customer $customer = null): Address
    {
        return Address::create([
            'owner_type' => $customer ? $customer->getMorphClass() : null,
            'owner_id' => $customer?->getKey(),
            'type' => 'shipping',
            'first_name' => 'Test',
            'last_name' => 'User',
            'line1' => '1 rue Test',
            'city' => 'Paris',
            'region' => $region,
            'postal_code' => $postal,
            'country_code' => $countryCode,
        ]);
    }

    public function installChart(): void
    {
        (new DefaultChartOfAccounts)->install();
    }

    public function makeTaxZone(string $code, string $name, array $countries = []): TaxZone
    {
        $zone = TaxZone::query()->create([
            'code' => $code, 'name' => $name, 'active' => true,
        ]);
        foreach ($countries as $country) {
            $zone->members()->create([
                'country_code' => $country,
                'operator' => 'or',
            ]);
        }

        return $zone;
    }

    public function makeTaxClass(string $slug = 'standard', string $name = 'Standard'): TaxClass
    {
        return TaxClass::query()->create(['name' => $name, 'slug' => $slug]);
    }

    public function makeTaxRate(TaxZone $zone, TaxClass $class, float $percent, bool $compound = false): TaxRate
    {
        return TaxRate::create([
            'zone_id' => $zone->id,
            'tax_class_id' => $class->id,
            'name' => "{$percent}% {$zone->code}",
            'percent' => $percent,
            'compound' => $compound,
            'priority' => 1,
            'active' => true,
        ]);
    }
}
