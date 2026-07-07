<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Requests;

use Headless\Accounting\Support\Config;
use Illuminate\Foundation\Http\FormRequest;

class CapturePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $drivers = array_keys(Config::array('headless-accounting.payments.drivers', []));

        return [
            'driver' => ['required', 'string', 'in:'.implode(',', $drivers)],
            'method' => ['nullable', 'string', 'max:32'],
            'token' => ['nullable', 'string', 'max:191'],
            'amount_minor' => ['nullable', 'integer', 'min:0'],
            'return_url' => ['nullable', 'url'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
