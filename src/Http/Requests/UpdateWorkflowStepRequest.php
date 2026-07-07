<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'approver_type' => ['sometimes', 'required', 'string', 'in:user,role,manager,amount_gate'],
            'approver_config' => ['sometimes', 'array'],
            'min_amount_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_amount_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'mode' => ['sometimes', 'nullable', 'string', 'in:any,all'],
            'required' => ['sometimes', 'boolean'],
        ];
    }
}
