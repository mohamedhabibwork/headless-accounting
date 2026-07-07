<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaxRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'zone_id' => ['required', 'integer', 'exists:ha_tax_zones,id'],
            'tax_class_id' => ['nullable', 'integer', 'exists:ha_tax_classes,id'],
            'name' => ['required', 'string', 'max:191'],
            'percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'compound' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
