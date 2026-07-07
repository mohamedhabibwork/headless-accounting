<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Http\Resources\ResolvedPriceResource;
use Headless\Accounting\Models\Channel;
use Headless\Accounting\Models\Customer;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Pricing\PricingResolver;
use Headless\Accounting\Support\Config;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PricingController extends Controller
{
    public function __construct(private readonly PricingResolver $resolver) {}

    public function resolve(Request $request, int $variantId)
    {
        $variant = ProductVariant::findOrFail($variantId);
        $price = $this->resolver->resolve(
            variant: $variant,
            currency: $request->input('currency', $request->header('X-Currency', Config::string('headless-accounting.currency.default'))),
            channel: $request->filled('channel') ? Channel::find($request->channel) : null,
            customer: $request->filled('customer') ? Customer::find($request->customer) : null,
            quantity: (int) $request->input('quantity', 1),
            locale: $request->header('Accept-Language', Config::string('headless-accounting.locale.default')),
        );

        return new ResolvedPriceResource($price);
    }
}
