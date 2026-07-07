<?php

declare(strict_types=1);

namespace Headless\Accounting\Accounting;

use Carbon\CarbonImmutable;
use Headless\Accounting\Exceptions\ConfigurationException;
use Headless\Accounting\Exceptions\UnbalancedJournalException;
use Headless\Accounting\Models\Account;
use Headless\Accounting\Models\AccountingPeriod;
use Headless\Accounting\Models\JournalEntry;
use Headless\Accounting\Models\Posting;
use Headless\Accounting\Support\Config;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal — utility for posting balanced, source-tagged journal entries.
 *
 *   $je = (new Journal)->post(
 *       source:    $order,
 *       postings:  [
 *           ['account' => '1200', 'debit' => 10000, 'memo' => 'AR'],
 *           ['account' => '4000', 'credit' => 10000, 'memo' => 'Sale'],
 *       ],
 *       currency:  'EUR',
 *       description: 'Order #1234 placed',
 *   );
 *
 * Always balanced — throws {@see UnbalancedJournalException} when debit
 * != credit per currency.
 */
final class Journal
{
    public function __construct(private readonly ChartOfAccounts $chart) {}

    /**
     * @param  array<int, array<string,mixed>>  $postings
     *                                                     Each row supports:
     *                                                     - 'account'   => string account code, OR 'account_id' => int
     *                                                     - 'debit'     => int (minor units) OR 'credit' => int
     *                                                     - 'currency'  => string (optional, defaults to journal currency)
     *                                                     - 'memo'      => string
     */
    public function post(
        Model $source,
        array $postings,
        ?string $currency = null,
        string $description = '',
        ?bool $autoPosted = null,
        ?CarbonImmutable $at = null,
    ): JournalEntry {
        $at ??= CarbonImmutable::now();
        $currency ??= Config::string('headless-accounting.accounting.default_currency');
        $autoPosted ??= Config::bool('headless-accounting.accounting.auto_post', true);

        $entry = JournalEntry::create([
            'number' => $this->nextNumber(),
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'period_id' => $this->resolvePeriod($at)?->id,
            'currency' => $currency,
            'description' => $description,
            'auto_posted' => $autoPosted,
            'posted_at' => $at->toDateString(),
        ]);

        $resolvedByCode = $this->chart->map();

        foreach ($postings as $row) {
            $accountId = $row['account_id']
                ?? ($resolvedByCode[$row['account']] ?? null)
                ?? throw new ConfigurationException('Unknown account: '.($row['account'] ?? 'n/a'));

            Posting::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accountId,
                'debit_minor' => (int) ($row['debit'] ?? 0),
                'credit_minor' => (int) ($row['credit'] ?? 0),
                'currency' => $row['currency'] ?? $currency,
                'memo' => $row['memo'] ?? null,
            ]);
        }

        $entry->assertBalanced();

        return $entry->fresh('postings');
    }

    private function nextNumber(): string
    {
        $today = CarbonImmutable::now()->format('Ymd');
        $count = JournalEntry::query()->whereDate('created_at', CarbonImmutable::now()->toDateString())->count() + 1;
        $prefix = Config::string('headless-accounting.number_prefixes.journal_entry', 'JE');

        return sprintf('%s-%s-%05d', $prefix, $today, $count);
    }

    private function resolvePeriod(CarbonImmutable $at): ?AccountingPeriod
    {
        return AccountingPeriod::query()
            ->whereDate('starts_at', '<=', $at->toDateString())
            ->whereDate('ends_at', '>=', $at->toDateString())
            ->first();
    }
}
