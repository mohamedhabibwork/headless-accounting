<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts;

use Headless\Accounting\Currency\Money;

/**
 * DiscountApplication — the *outcome* of a successful discount pass.
 * Contains one or more Money adjustments, one per affected line, plus the
 * accumulated total. Limitations may have clipped the impact; the struct
 * keeps both requested and applied values so reports stay honest.
 */
final class DiscountApplication
{
    /** @var array<int, array{variant_id:int|null, product_id:int|null, amount:Money, requested:Money, clipped_by:string|null}> */
    private array $lines = [];

    public function __construct(
        public readonly int $discountId,
        public readonly string $discountName,
        public readonly Money $total,
        public readonly Money $requested,
    ) {}

    /**
     * @param  array{variant_id?:int|null,product_id?:int|null}  $subject
     */
    public function addLine(Money $amount, Money $requested, array $subject = [], ?string $clippedBy = null): void
    {
        $this->lines[] = [
            'variant_id' => $subject['variant_id'] ?? null,
            'product_id' => $subject['product_id'] ?? null,
            'amount' => $amount,
            'requested' => $requested,
            'clipped_by' => $clippedBy,
        ];
    }

    public function lines(): array
    {
        return $this->lines;
    }

    public function isEmpty(): bool
    {
        return $this->total->isZero();
    }
}
