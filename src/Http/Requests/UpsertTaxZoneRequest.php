<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertTaxZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:191'],
            'active' => ['nullable', 'boolean'],
            'members' => ['nullable', 'array'],
            'members.*.country_code' => ['required_with:members', 'string', 'size:2'],
            'members.*.region' => ['nullable', 'string', 'max:191'],
            'members.*.postal_code_pattern' => ['nullable', 'string', 'max:32'],
            'members.*.operator' => ['nullable', 'string', 'in:or,and'],
        ];
    }
}
