<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Carbon\CarbonImmutable;
use Headless\Accounting\Accounting\Ledger;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Support\Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ReportsController extends Controller
{
    public function __construct(private readonly Ledger $ledger) {}

    public function trialBalance(Request $request): JsonResponse
    {
        $currency = $request->input('currency', Config::string('headless-accounting.currency.default'));
        $asOf = $request->filled('as_of') ? CarbonImmutable::parse($request->input('as_of')) : null;

        return new JsonResponse([
            'currency' => $currency,
            'as_of' => optional($asOf)->toIso8601String() ?? now()->toIso8601String(),
            'rows' => $this->ledger->trialBalance($currency, $asOf),
        ]);
    }

    public function incomeStatement(Request $request): JsonResponse
    {
        $currency = $request->input('currency', Config::string('headless-accounting.currency.default'));
        $from = CarbonImmutable::parse($request->input('from', now()->startOfYear()->toDateString()));
        $to = CarbonImmutable::parse($request->input('to', now()->endOfYear()->toDateString()));

        return new JsonResponse([
            'currency' => $currency,
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'result' => $this->ledger->incomeStatement($currency, $from, $to),
        ]);
    }

    /**
     * sales-by-channel — the canonical merchant summary promised in the
     * README. Per-channel aggregates of placed/paid orders in a window.
     */
    public function salesByChannel(Request $request): JsonResponse
    {
        $from = CarbonImmutable::parse($request->input('from', now()->subDays(30)->startOfDay()->toDateString()));
        $to = CarbonImmutable::parse($request->input('to', now()->endOfDay()->toDateString()));
        $currency = $request->input('currency');

        $query = Order::query()
            ->whereIn('state', [
                Order::STATE_PLACED,
                Order::STATE_PAID,
                Order::STATE_FULFILLED,
                Order::STATE_CLOSED,
            ])
            ->whereBetween('placed_at', [$from, $to]);

        if ($currency) {
            $query->where('currency', $currency);
        }

        $rows = $query
            ->selectRaw('channel_code, currency, COUNT(*) as order_count, SUM(grand_total_minor) as gross_minor, SUM(item_count) as item_count')
            ->groupBy('channel_code', 'currency')
            ->orderBy('channel_code')
            ->get();

        return new JsonResponse([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'currency' => $currency,
            'channels' => $rows->map(fn ($r) => [
                'channel' => $r->channel_code,
                'currency' => $r->currency,
                'order_count' => (int) $r->order_count,
                'item_count' => (int) $r->item_count,
                'gross_minor' => (int) $r->gross_minor,
            ])->all(),
        ]);
    }
}
