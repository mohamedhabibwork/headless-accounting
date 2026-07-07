<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Requests;

use Headless\Accounting\Discounts\Drivers\BuyXGetYDiscount;
use Headless\Accounting\Discounts\Drivers\FixedAmountDiscount;
use Headless\Accounting\Discounts\Drivers\PercentageDiscount;
use Headless\Accounting\Support\Config;
use Illuminate\Foundation\Http\FormRequest;

class StoreDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $drivers = Config::array('headless-accounting.discounts.drivers', []);

        $driverClasses = array_values($drivers);
        $allowedTypes = array_unique(array_filter([
            PercentageDiscount::class,
            FixedAmountDiscount::class,
            BuyXGetYDiscount::class,
            ...$driverClasses,
        ]));

        return [
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:64'],
            'type' => ['required', 'string', 'in:'.implode(',', $allowedTypes)],
            'active' => ['nullable', 'boolean'],
            'stackable' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'config' => ['nullable', 'array'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'channel_code' => ['nullable', 'string', 'max:64'],
            'targets' => ['nullable', 'array'],
            'targets.*.type' => ['required_with:targets', 'string'],
            'targets.*.id' => ['required_with:targets', 'integer'],
            'conditions' => ['nullable', 'array'],
            'conditions.*.type' => ['required_with:conditions', 'string'],
            'conditions.*.config' => ['nullable', 'array'],
            'limitations' => ['nullable', 'array'],
            'limitations.*.type' => ['required_with:limitations', 'string'],
            'limitations.*.config' => ['nullable', 'array'],
        ];
    }
}
