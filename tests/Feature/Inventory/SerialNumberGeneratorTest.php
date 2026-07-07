<?php

declare(strict_types=1);

use Headless\Accounting\Enums\Inventory\SerialNumberStatus;
use Headless\Accounting\Inventory\SerialNumberGenerator;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\SerialNumber;
use Headless\Accounting\Tenancy\Company;

describe('SerialNumberGenerator', function () {

    it('mints identifiers using the configured number_prefixes.serial_number prefix', function () {
        config()->set('headless-accounting.number_prefixes.serial_number', 'SN');

        $gen = app(SerialNumberGenerator::class);

        expect($gen->prefix())->toBe('SN');
        expect($gen->next())->toMatch('/^SN-\d{4}-\d{6}$/');
    });

    it('respects an overridden prefix from the config', function () {
        config()->set('headless-accounting.number_prefixes.serial_number', 'IMEI');

        $gen = app(SerialNumberGenerator::class);

        expect($gen->prefix())->toBe('IMEI');
        expect($gen->next())->toStartWith('IMEI-');
    });

    it('increments the counter on each call', function () {
        $gen = app(SerialNumberGenerator::class);

        $first = $gen->next();
        $second = $gen->next();
        $third = $gen->next();

        $suffix = fn (string $s) => (int) substr($s, strrpos($s, '-') + 1);
        expect($suffix($second))->toBe($suffix($first) + 1);
        expect($suffix($third))->toBe($suffix($second) + 1);
    });

    it('peeks at the next counter without incrementing it', function () {
        $gen = app(SerialNumberGenerator::class);

        $peek = $gen->peek();
        $gen->next();
        $gen->next();

        expect($peek)->toBe($gen->peek() - 2);
    });

    it('keeps counters isolated per company', function () {
        $gen = app(SerialNumberGenerator::class);
        $a = Company::create(['code' => 'A', 'name' => 'A', 'base_currency' => 'EUR']);
        $b = Company::create(['code' => 'B', 'name' => 'B', 'base_currency' => 'EUR']);

        $aFirst = $gen->next($a->id);
        $bFirst = $gen->next($b->id);
        $aSecond = $gen->next($a->id);

        $suffix = fn (string $s) => (int) substr($s, strrpos($s, '-') + 1);
        expect($suffix($aFirst))->toBe(1);
        expect($suffix($bFirst))->toBe(1);
        expect($suffix($aSecond))->toBe(2);
    });

    it('mints a contiguous batch via generateMany', function () {
        $gen = app(SerialNumberGenerator::class);

        $batch = $gen->generateMany(5);

        expect($batch)->toHaveCount(5);
        expect(array_unique($batch))->toHaveCount(5);

        $suffix = fn (string $s) => (int) substr($s, strrpos($s, '-') + 1);
        $suffixes = array_map($suffix, $batch);
        expect(min($suffixes))->toBe(max($suffixes) - 4);
    });

    it('returns an empty array when generateMany is called with zero', function () {
        $gen = app(SerialNumberGenerator::class);

        expect($gen->generateMany(0))->toBe([]);
        expect($gen->generateMany(-3))->toBe([]);
    });

    it('registers a SerialNumber row via register()', function () {
        $variant = ProductVariant::factory()->serialTracked()->create();
        $gen = app(SerialNumberGenerator::class);

        $row = $gen->register($variant->id);

        expect($row)->toBeInstanceOf(SerialNumber::class);
        expect($row->variant_id)->toBe($variant->id);
        expect($row->serial)->toMatch('/^SN-\d{4}-\d{6}$/');
        expect($row->status)->toBe(SerialNumberStatus::InStock);
        expect($row->exists)->toBeTrue();
    });

    it('applies caller overrides to the registered SerialNumber row', function () {
        $variant = ProductVariant::factory()->serialTracked()->create();
        $gen = app(SerialNumberGenerator::class);

        $row = $gen->register($variant->id, [
            'warranty_terms' => ['duration_months' => 24],
            'manufacturing_date' => now()->subMonths(2),
        ]);

        expect($row->warranty_terms)->toBe(['duration_months' => 24]);
        expect($row->manufacturing_date?->toDateString())->toBe(now()->subMonths(2)->toDateString());
    });

    it('keeps the host NumberSeries table in sync across mints', function () {
        $gen = app(SerialNumberGenerator::class);

        $gen->next();
        $gen->next();
        $gen->next();

        $row = \Headless\Accounting\Tenancy\NumberSeries::query()
            ->where('prefix', $gen->prefix())
            ->firstOrFail();

        $year = (int) date('Y');
        expect($row->next_number)->toBe(4);
        expect($row->last_reset_year)->toBe($year);
    });
});