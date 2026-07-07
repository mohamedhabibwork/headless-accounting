<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['sometimes', 'required', 'email:rfc', 'max:191'],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'company' => ['sometimes', 'nullable', 'string', 'max:191'],
            'vat_id' => ['sometimes', 'nullable', 'string', 'max:32'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'default_currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'default_locale' => ['sometimes', 'nullable', 'string', 'size:8'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
