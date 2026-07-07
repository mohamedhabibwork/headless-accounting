<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Discount;
use Headless\Accounting\Models\DiscountTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiscountTargetFactory extends Factory
{
    protected $model = DiscountTarget::class;

    public function definition(): array
    {
        return [
            'discount_id' => Discount::factory(),
            'target_type' => null,
            'target_id' => null,
        ];
    }

    public function forDiscount(int $discountId): static
    {
        return $this->state(['discount_id' => $discountId]);
    }

    public function product(int $productId): static
    {
        return $this->state([
            'target_type' => 'product',
            'target_id' => $productId,
        ]);
    }

    public function variant(int $variantId): static
    {
        return $this->state([
            'target_type' => 'variant',
            'target_id' => $variantId,
        ]);
    }

    public function category(int $categoryId): static
    {
        return $this->state([
            'target_type' => 'category',
            'target_id' => $categoryId,
        ]);
    }

    public function collection(int $collectionId): static
    {
        return $this->state([
            'target_type' => 'collection',
            'target_id' => $collectionId,
        ]);
    }

    public function target(string $type, int $id): static
    {
        return $this->state([
            'target_type' => $type,
            'target_id' => $id,
        ]);
    }
}
