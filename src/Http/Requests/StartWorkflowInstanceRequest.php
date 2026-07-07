<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartWorkflowInstanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject_type' => ['required', 'string', 'max:191'],
            'subject_id' => ['required'],
            'scope' => ['required', 'string', 'max:64'],
            'amount_context' => ['nullable', 'array'],
        ];
    }
}
