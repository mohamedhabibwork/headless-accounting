<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer'],
            'scope' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:191'],
            'config' => ['nullable', 'array'],
            'active' => ['nullable', 'boolean'],

            'steps' => ['nullable', 'array'],
            'steps.*.order' => ['required_with:steps', 'integer', 'min:1'],
            'steps.*.name' => ['required_with:steps', 'string', 'max:191'],
            'steps.*.approver_type' => ['required_with:steps', 'string', 'in:user,role,manager,amount_gate'],
            'steps.*.approver_config' => ['nullable', 'array'],
            'steps.*.min_amount_minor' => ['nullable', 'integer', 'min:0'],
            'steps.*.max_amount_minor' => ['nullable', 'integer', 'min:0'],
            'steps.*.mode' => ['nullable', 'string', 'in:any,all'],
            'steps.*.required' => ['nullable', 'boolean'],
        ];
    }
}
