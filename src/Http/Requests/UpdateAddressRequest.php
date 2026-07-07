<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', 'in:billing,shipping'],
            'company' => ['sometimes', 'nullable', 'string', 'max:191'],
            'first_name' => ['sometimes', 'required', 'string', 'max:191'],
            'last_name' => ['sometimes', 'required', 'string', 'max:191'],
            'line1' => ['sometimes', 'required', 'string', 'max:191'],
            'line2' => ['sometimes', 'nullable', 'string', 'max:191'],
            'city' => ['sometimes', 'required', 'string', 'max:191'],
            'region' => ['sometimes', 'nullable', 'string', 'max:191'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'country_code' => ['sometimes', 'required', 'string', 'size:2'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
