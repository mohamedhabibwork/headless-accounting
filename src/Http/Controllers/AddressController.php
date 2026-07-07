<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Http\Requests\StoreAddressRequest;
use Headless\Accounting\Http\Requests\UpdateAddressRequest;
use Headless\Accounting\Models\Address;
use Headless\Accounting\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class AddressController extends Controller
{
    public function index(int $customerId): JsonResponse
    {
        $customer = Customer::query()->findOrFail($customerId);
        $addresses = $customer->addresses()->orderByDesc('is_default')->orderBy('id')->get();

        return new JsonResponse([
            'data' => $addresses->map(fn ($a) => $a->formatted())->all(),
        ]);
    }

    public function show(int $customerId, int $addressId): JsonResponse
    {
        $address = $this->findAddress($customerId, $addressId);

        return new JsonResponse($address->formatted());
    }

    public function store(StoreAddressRequest $request, int $customerId): JsonResponse
    {
        $customer = Customer::query()->findOrFail($customerId);

        $address = $customer->addresses()->create(array_merge(
            $request->validated(),
            ['owner_type' => $customer->getMorphClass(), 'owner_id' => $customer->getKey()],
        ));

        return new JsonResponse($address->formatted(), 201);
    }

    public function update(UpdateAddressRequest $request, int $customerId, int $addressId): JsonResponse
    {
        $address = $this->findAddress($customerId, $addressId);
        $address->update($request->validated());

        return new JsonResponse($address->fresh()->formatted());
    }

    public function destroy(int $customerId, int $addressId): JsonResponse
    {
        $address = $this->findAddress($customerId, $addressId);
        $address->delete();

        return new JsonResponse(['ok' => true], 204);
    }

    private function findAddress(int $customerId, int $addressId): Address
    {
        $customer = Customer::query()->findOrFail($customerId);

        return Address::query()
            ->where('owner_type', $customer->getMorphClass())
            ->where('owner_id', $customer->getKey())
            ->where('id', $addressId)
            ->firstOrFail();
    }
}
