<?php

declare(strict_types=1);

namespace Headless\Accounting\Inventory;

use Headless\Accounting\Support\Config;
use Illuminate\Support\Facades\DB;

/**
 * InventoryPolicyService — single point of truth for inventory-related
 * configuration: per-company valuation method (overrides the global
 * default), the document-level inventory tunables, and the chart of
 * accounts code lookups.
 */
final class InventoryPolicyService
{
    public function method(int $companyId): string
    {
        $table = Config::string('headless-accounting.table_prefix', 'ha_').'account_policies';

        try {
            $value = DB::table($table)
                ->where('company_id', $companyId)
                ->where('key', 'inventory_valuation_method')
                ->value('value');
        } catch (\Throwable) {
            $value = null;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return Config::string('headless-accounting.inventory.valuation_method', 'fifo');
    }

    public function nearExpiryDays(): int
    {
        return Config::int('headless-accounting.inventory.near_expiry_days', 30);
    }

    public function autoQuarantineExpired(): bool
    {
        return Config::bool('headless-accounting.inventory.auto_quarantine_expired', true);
    }

    public function reservationTtlMinutes(): int
    {
        return Config::int('headless-accounting.inventory.reservation_ttl_minutes', 15);
    }

    public function fefoEnabled(): bool
    {
        return Config::bool('headless-accounting.inventory.fefo_default', true);
    }

    public function binCapacityEnforced(): bool
    {
        return Config::bool('headless-accounting.inventory.enforce_bin_capacity', true);
    }

    public function replenishmentEnabled(): bool
    {
        return Config::bool('headless-accounting.inventory.replenishment.enabled', true);
    }

    public function autoCreateDraftPo(): bool
    {
        return Config::bool('headless-accounting.inventory.replenishment.auto_create_draft_po', false);
    }

    public function accountCode(string $key): string
    {
        return Config::string('headless-accounting.accounting.accounts.'.$key, '');
    }

    /**
     * Resolve the inventory asset account code for a given product
     * item type: 'raw_material' → 1410, 'finished_good' → 1430,
     * 'semi_finished' / 'wip' → 1420, else the generic 1400.
     */
    public function inventoryAccountFor(?string $itemType): string
    {
        $key = match ($itemType) {
            'raw_material' => 'inventory_raw',
            'finished_good' => 'finished_goods',
            'semi_finished' => 'wip',
            default => 'inventory',
        };

        $code = $this->accountCode($key);

        return $code !== '' ? $code : '1400';
    }
}
