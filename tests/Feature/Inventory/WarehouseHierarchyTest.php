<?php

declare(strict_types=1);

use Headless\Accounting\Models\Warehouse;

describe('Warehouse hierarchy', function () {

    it('supports parent_id for nested warehouses', function () {
        $parent = Warehouse::factory()->create(['code' => 'WH-PAR', 'name' => 'Parent']);
        $child = Warehouse::factory()->create(['code' => 'WH-CHD', 'name' => 'Child', 'parent_id' => $parent->id]);

        $loaded = $child->fresh();

        expect($loaded->parent)->not->toBeNull();
        expect($loaded->parent->is($parent))->toBeTrue();
        expect($parent->fresh()->children->contains('id', $child->id))->toBeTrue();
    });

    it('marks consignment warehouses via flag and helper', function () {
        $consignment = Warehouse::factory()->create(['code' => 'WH-CONS', 'consignment' => true]);

        expect($consignment->fresh()->isConsignment())->toBeTrue();
    });

    it('marks virtual warehouses via flag and helper', function () {
        $virtual = Warehouse::factory()->create(['code' => 'WH-VIRT', 'virtual' => true]);

        expect($virtual->fresh()->isVirtual())->toBeTrue();
    });
});
