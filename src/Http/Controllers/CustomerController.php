<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Http\Requests\StoreCustomerRequest;
use Headless\Accounting\Http\Requests\UpdateCustomerRequest;
use Headless\Accounting\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CustomerController extends Controller
{
    public function index(): JsonResponse
    {
        $customers = Customer::query()->orderByDesc('id')->paginate();

        return new JsonResponse([
            'data' => $customers->items(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'last_page' => $customers->lastPage(),
            ],
        ]);
    }

    public function show(int $customerId): JsonResponse
    {
        $customer = Customer::query()
            ->with(['addresses', 'orders', 'invoices', 'payments', 'groups'])
            ->findOrFail($customerId);

        return new JsonResponse($this->serialize($customer));
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::query()->create($request->validated());

        return new JsonResponse($this->serialize($customer), 201);
    }

    public function update(UpdateCustomerRequest $request, int $customerId): JsonResponse
    {
        $customer = Customer::query()->findOrFail($customerId);
        $customer->update($request->validated());

        return new JsonResponse($this->serialize($customer->fresh()));
    }

    public function destroy(int $customerId): JsonResponse
    {
        Customer::query()->findOrFail($customerId)->delete();

        return new JsonResponse(['ok' => true], 204);
    }

    private function serialize(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'email' => $customer->email,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'company' => $customer->company,
            'vat_id' => $customer->vat_id,
            'phone' => $customer->phone,
            'default_currency' => $customer->default_currency,
            'default_locale' => $customer->default_locale,
            'email_verified_at' => $customer->email_verified_at?->toIso8601String(),
            'metadata' => $customer->metadata,
            'addresses' => $customer->addresses->map(fn ($a) => $a->formatted())->all(),
            'created_at' => $customer->created_at?->toIso8601String(),
        ];
    }
}
