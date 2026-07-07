<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Requests;

use Headless\Accounting\Models\Channel;
use Headless\Accounting\Support\Config;
use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedCurrencies = Config::array('headless-accounting.currency.allowed', []);
        $allowedLocales = Config::array('headless-accounting.locale.allowed', []);
        $channels = Channel::query()->pluck('code')->all();

        return [
            'channel' => ['nullable', 'string', 'in:'.implode(',', $channels)],
            'currency' => ['nullable', 'string', 'size:3', 'in:'.implode(',', $allowedCurrencies)],
            'locale' => ['nullable', 'string', 'size:8', 'in:'.implode(',', $allowedLocales)],

            'billing_address' => ['nullable', 'array'],
            'billing_address.line1' => ['required_with:billing_address', 'string'],
            'billing_address.city' => ['required_with:billing_address', 'string'],
            'billing_address.country_code' => ['required_with:billing_address', 'string', 'size:2'],

            'shipping_address' => ['nullable', 'array'],
            'shipping_address.line1' => ['required_with:shipping_address', 'string'],
            'shipping_address.city' => ['required_with:shipping_address', 'string'],
            'shipping_address.country_code' => ['required_with:shipping_address', 'string', 'size:2'],

            'tax_inclusive' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
