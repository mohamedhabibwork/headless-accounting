<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'variant_id' => ['required', 'integer', 'exists:ha_product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'unit_price_minor' => ['nullable', 'integer', 'min:0'],     // optional override
        ];
    }
}
