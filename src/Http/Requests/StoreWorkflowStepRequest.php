<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:191'],
            'approver_type' => ['required', 'string', 'in:user,role,manager,amount_gate'],
            'approver_config' => ['nullable', 'array'],
            'min_amount_minor' => ['nullable', 'integer', 'min:0'],
            'max_amount_minor' => ['nullable', 'integer', 'min:0'],
            'mode' => ['nullable', 'string', 'in:any,all'],
            'required' => ['nullable', 'boolean'],
        ];
    }
}
