<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'in:billing,shipping'],
            'company' => ['nullable', 'string', 'max:191'],
            'first_name' => ['required', 'string', 'max:191'],
            'last_name' => ['required', 'string', 'max:191'],
            'line1' => ['required', 'string', 'max:191'],
            'line2' => ['nullable', 'string', 'max:191'],
            'city' => ['required', 'string', 'max:191'],
            'region' => ['nullable', 'string', 'max:191'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'country_code' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
