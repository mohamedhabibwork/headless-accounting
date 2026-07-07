<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Customer;
use Headless\Accounting\Models\Invoice;
use Headless\Accounting\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'number' => 'INV-'.date('Y').'-'.$this->faker->unique()->numerify('######'),
            'order_id' => Order::factory(),
            'customer_id' => Customer::factory(),
            'currency' => 'EUR',
            'state' => Invoice::STATE_DRAFT,
            'subtotal_minor' => 0,
            'tax_total_minor' => 0,
            'grand_total_minor' => 0,
            'paid_minor' => 0,
            'balance_minor' => 0,
            'issued_at' => null,
            'due_at' => null,
            'lines' => [],
        ];
    }

    public function forOrder(int $orderId): static
    {
        return $this->state(['order_id' => $orderId]);
    }

    public function forCustomer(int $customerId): static
    {
        return $this->state(['customer_id' => $customerId]);
    }

    public function issued(): static
    {
        return $this->state([
            'state' => Invoice::STATE_ISSUED,
            'issued_at' => now()->toDateString(),
            'due_at' => now()->addDays(30)->toDateString(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(function (array $attrs) {
            $total = (int) $attrs['grand_total_minor'];

            return [
                'state' => Invoice::STATE_PAID,
                'paid_minor' => $total,
                'balance_minor' => 0,
            ];
        });
    }

    public function partiallyPaid(int $paidMinor): static
    {
        return $this->state([
            'state' => Invoice::STATE_PARTIAL,
            'paid_minor' => $paidMinor,
        ]);
    }

    public function void(): static
    {
        return $this->state(['state' => Invoice::STATE_VOID]);
    }

    public function cancelled(): static
    {
        return $this->state(['state' => Invoice::STATE_CANCELLED]);
    }

    public function overdue(): static
    {
        return $this->state([
            'state' => Invoice::STATE_ISSUED,
            'issued_at' => now()->subDays(60)->toDateString(),
            'due_at' => now()->subDays(30)->toDateString(),
        ]);
    }

    public function withTotals(int $subtotal, int $grandTotal, int $tax = 0): static
    {
        return $this->state([
            'subtotal_minor' => $subtotal,
            'tax_total_minor' => $tax,
            'grand_total_minor' => $grandTotal,
            'balance_minor' => $grandTotal,
        ]);
    }

    public function withLines(array $lines): static
    {
        return $this->state(['lines' => $lines]);
    }

    public function dueInDays(int $days): static
    {
        return $this->state(['due_at' => now()->addDays($days)->toDateString()]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }
}
