<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolvePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency' => ['nullable', 'string', 'size:3'],
            'channel' => ['nullable', 'string', 'max:32'],
            'customer' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ];
    }
}
