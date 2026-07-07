<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Accept either a numeric id, the discount code, or the literal word
            // 'coupon' so callers can pass the code in either field.
            'discount_id' => ['nullable', 'integer', 'min:1'],
            'code' => ['nullable', 'string', 'max:64'],
            'coupon' => ['nullable', 'string', 'max:64'],
        ];
    }
}
