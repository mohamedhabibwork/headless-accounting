<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()->latest();

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->input('customer_id'));
        }
        if ($request->filled('order_id')) {
            $query->where('order_id', (int) $request->input('order_id'));
        }
        if ($request->filled('state')) {
            $query->where('state', $request->input('state'));
        }

        $invoices = $query->paginate();

        return new JsonResponse([
            'data' => $invoices->items(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'last_page' => $invoices->lastPage(),
            ],
        ]);
    }

    public function show(int $invoiceId): JsonResponse
    {
        $invoice = Invoice::query()
            ->with(['payments', 'creditNotes', 'customer', 'order'])
            ->findOrFail($invoiceId);

        return new JsonResponse([
            'id' => $invoice->id,
            'number' => $invoice->number,
            'state' => $invoice->state,
            'currency' => $invoice->currency,
            'customer_id' => $invoice->customer_id,
            'order_id' => $invoice->order_id,
            'totals' => [
                'subtotal' => $invoice->subtotal_minor,
                'tax' => $invoice->tax_total_minor,
                'grand' => $invoice->grand_total_minor,
                'paid' => $invoice->totalPaid(),
                'balance' => $invoice->balanceDue(),
            ],
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'due_at' => $invoice->due_at?->toIso8601String(),
            'lines' => $invoice->lines,
            'payments' => $invoice->payments->map(fn ($p) => [
                'id' => $p->id,
                'number' => $p->number,
                'state' => $p->state,
                'amount_minor' => $p->amount_minor,
                'currency' => $p->currency,
                'driver' => $p->driver,
            ])->all(),
            'credit_notes' => $invoice->creditNotes->map(fn ($cn) => [
                'id' => $cn->id,
                'number' => $cn->number,
                'amount_minor' => $cn->amount_minor,
            ])->all(),
            'created_at' => $invoice->created_at?->toIso8601String(),
        ]);
    }
}
