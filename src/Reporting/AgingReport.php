<?php

declare(strict_types=1);

namespace Headless\Accounting\Reporting;

use Carbon\CarbonImmutable;
use Headless\Accounting\Models\Bill;
use Headless\Accounting\Models\Customer;
use Headless\Accounting\Models\Invoice;
use Headless\Accounting\Models\Vendor;
use Illuminate\Database\Eloquent\Collection;

/**
 * AgingReport — bucketed A/R and A/P aging for customers and vendors.
 *
 *   $report = (new AgingReport)->arAgingFor($customer);
 *   // returns: ['current' => X, '1_30' => Y, '31_60' => Z, ...]
 *
 *     or for the whole ledger:
 *   $report = (new AgingReport)->arAging(companyId: $co, asOf: $at);
 *   // returns: [ ['party_id' => X, 'party_name' => 'Y', 'current' => …], … ]
 */
class AgingReport
{
    public function arAging(int $companyId, ?CarbonImmutable $asOf = null, int $buckets = 4): array
    {
        $asOf ??= CarbonImmutable::today();

        // open invoices = state in (issued, partial, paid but balance > 0?)
        $invoices = Invoice::query()
            ->where('company_id', $companyId)
            ->whereIn('state', [Invoice::STATE_ISSUED, Invoice::STATE_PARTIAL])
            ->whereDate('due_at', '<=', $asOf->toDateString())
            ->with('customer')
            ->get();

        return $this->bucketize($invoices, $asOf, 'customer_id', 'customer.fullName()', $buckets);
    }

    public function apAging(int $companyId, ?CarbonImmutable $asOf = null, int $buckets = 4): array
    {
        $asOf ??= CarbonImmutable::today();

        $bills = Bill::query()
            ->where('company_id', $companyId)
            ->whereIn('state', [Bill::STATE_RECEIVED, Bill::STATE_PARTIAL])
            ->whereDate('due_date', '<=', $asOf->toDateString())
            ->with('vendor')
            ->get();

        return $this->bucketize($bills, $asOf, 'vendor_id', 'vendor.name', $buckets);
    }

    /** @return array{label:string, amount_minor:int}[] */
    public function arAgingFor(Customer $customer, ?CarbonImmutable $asOf = null, int $buckets = 4): array
    {
        $asOf ??= CarbonImmutable::today();
        $rows = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('state', [Invoice::STATE_ISSUED, Invoice::STATE_PARTIAL])
            ->get();

        return $this->bucketRows($rows, $asOf, 'due_at', $buckets);
    }

    public function apAgingFor(Vendor $vendor, ?CarbonImmutable $asOf = null, int $buckets = 4): array
    {
        $asOf ??= CarbonImmutable::today();
        $rows = Bill::query()
            ->where('vendor_id', $vendor->id)
            ->whereIn('state', [Bill::STATE_RECEIVED, Bill::STATE_PARTIAL])
            ->get();

        return $this->bucketRows($rows, $asOf, 'due_date', $buckets);
    }

    /**
     * @param  Collection  $rows
     * @return array<int, array{customer_id?:int,vendor_id?:int,name:string,buckets:array}>
     */
    private function bucketize($rows, CarbonImmutable $asOf, string $partyKey, string $nameResolver, int $buckets): array
    {
        $parties = [];
        foreach ($rows as $row) {
            $key = data_get($row, $partyKey);
            $parties[$key] ??= [
                $partyKey => $key,
                'name' => data_get($row, str_replace('_id', '.', $partyKey).'name') ?? (string) $key,
                'buckets' => $this->emptyBuckets($buckets),
            ];
            $diff = (int) $asOf->diffInDays(CarbonImmutable::parse($row->due_at ?? $row->due_date), true);
            $idx = $this->bucketIndex($diff, $buckets);
            $parties[$key]['buckets'][$idx]['amount_minor'] += (int) $row->balance_minor;
        }

        return array_values($parties);
    }

    private function bucketRows($rows, CarbonImmutable $asOf, string $dateField, int $buckets): array
    {
        $buckets = $this->emptyBuckets($buckets);
        foreach ($rows as $row) {
            $diff = (int) $asOf->diffInDays(CarbonImmutable::parse($row->{$dateField}), true);
            $idx = $this->bucketIndex($diff, $buckets);
            $buckets[$idx]['amount_minor'] += (int) $row->balance_minor;
        }

        return $buckets;
    }

    private function emptyBuckets(int $n): array
    {
        $labels = ['0-current', '1-30', '31-60', '61-90', '90+'];
        $buckets = [];
        for ($i = 0; $i < $n; $i++) {
            $buckets[] = ['label' => $labels[$i] ?? ('plus_'.(30 * $i + 1)), 'amount_minor' => 0];
        }

        return $buckets;
    }

    private function bucketIndex(int $daysOverdue, int $total): int
    {
        if ($daysOverdue <= 0) {
            return 0;
        }
        if ($daysOverdue <= 30) {
            return 1;
        }
        if ($daysOverdue <= 60) {
            return 2;
        }
        if ($daysOverdue <= 90) {
            return 3;
        }

        return 4;
    }
}
