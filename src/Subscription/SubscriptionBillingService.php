<?php

declare(strict_types=1);

namespace Headless\Accounting\Subscription;

use Carbon\CarbonImmutable;
use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Models\Invoice;
use Headless\Accounting\Models\Subscription;
use Headless\Accounting\Models\SubscriptionInvoice;
use Headless\Accounting\Support\Config;
use Headless\Accounting\Support\RoundingMode;

/**
 * SubscriptionBillingService — issues invoices for every Subscription
 * whose `current_period_ends_at` <= today (the runner that drives
 * recurring billing), and handles the deferred-revenue recognition
 * pattern.
 */
class SubscriptionBillingService
{
    public function __construct(private readonly Journal $journal) {}

    /**
     * @return SubscriptionInvoice[]
     */
    public function billDue(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $subs = Subscription::query()
            ->whereNull('cancelled_at')
            ->where(function ($q) use ($today) {
                $q->whereDate('current_period_ends_at', '<=', $today)
                    ->orWhereNull('current_period_ends_at');
            })
            ->with('plan')
            ->get();

        $issued = [];
        foreach ($subs as $sub) {
            // Skip if an open invoice already exists for the period.
            $already = $sub->invoices()
                ->where('state', 'pending')
                ->whereDate('issue_at', '>=', $sub->current_period_starts_at ?? $today)
                ->exists();
            if ($already) {
                continue;
            }

            $amount = (int) RoundingMode::roundWith((float) $sub->plan->price_minor * (float) $sub->quantity);
            $invoice = $this->createInvoice($sub, $amount, $today);

            $this->journal->post(
                source: $sub, currency: $sub->currency,
                description: 'Subscription deferred revenue ('.$sub->plan->name.')',
                autoPosted: true,
                postings: [
                    ['account' => '1200', 'debit' => $amount, 'memo' => 'AR'],
                    ['account' => '2300', 'credit' => $amount, 'memo' => 'Customer prepayment'],
                ],
            );

            $sub->deferred_revenue_minor = (int) $sub->deferred_revenue_minor + $amount;
            $sub->current_period_starts_at = $sub->current_period_ends_at
                ? CarbonImmutable::parse($sub->current_period_ends_at)->addDay()->toDateString()
                : $today->toDateString();
            $sub->current_period_ends_at = $sub->current_period_starts_at
                ? CarbonImmutable::parse($sub->current_period_starts_at)->addMonth()->toDateString()
                : $today->addMonth()->toDateString();
            $sub->save();

            $issued[] = SubscriptionInvoice::create([
                'subscription_id' => $sub->id,
                'invoice_id' => $invoice->id,
                'issue_at' => $today->toDateString(),
                'currency' => $sub->currency,
                'amount_minor' => $amount,
                'recognized_minor' => 0,
                'state' => 'pending',
            ]);
        }

        return $issued;
    }

    /**
     * Recognizes $amountMinor of revenue from the deferred-revenue
     * pool on a daily basis (call this from a scheduler).
     */
    public function recognizeRevenue(Subscription $sub, int $amountMinor, ?CarbonImmutable $at = null): bool
    {
        $at ??= CarbonImmutable::today();
        if ($amountMinor > $sub->deferred_revenue_minor) {
            $amountMinor = $sub->deferred_revenue_minor;
        }
        if ($amountMinor <= 0) {
            return false;
        }

        $this->journal->post(
            source: $sub, currency: $sub->currency,
            description: 'Subscription revenue recognition',
            autoPosted: true,
            postings: [
                ['account' => '2300', 'debit' => $amountMinor, 'memo' => 'Customer prepayment cleared'],
                ['account' => Config::string('headless-accounting.accounting.accounts.sales_revenue'), 'credit' => $amountMinor, 'memo' => 'Subscription revenue'],
            ],
        );

        $sub->deferred_revenue_minor = (int) $sub->deferred_revenue_minor - $amountMinor;
        $sub->save();

        return true;
    }

    private function createInvoice(Subscription $sub, int $amount, CarbonImmutable $at): Invoice
    {
        return Invoice::create([
            'company_id' => $sub->company_id,
            'order_id' => null,
            'customer_id' => $sub->customer_id,
            'currency' => $sub->currency,
            'state' => Invoice::STATE_ISSUED,
            'subtotal_minor' => $amount,
            'tax_total_minor' => 0,
            'grand_total_minor' => $amount,
            'paid_minor' => 0,
            'balance_minor' => $amount,
            'issued_at' => $at->toDateString(),
            'due_at' => $at->addDays(14)->toDateString(),
            'lines' => [],
        ]);
    }
}
