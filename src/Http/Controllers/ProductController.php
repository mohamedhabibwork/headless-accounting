<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Http\Resources\ProductResource;
use Headless\Accounting\Models\Product;
use Illuminate\Routing\Controller;

class ProductController extends Controller
{
    public function index()
    {
        $items = Product::query()->where('active', true)->paginate();

        return ProductResource::collection($items);
    }

    public function show(int $id)
    {
        $product = Product::findOrFail($id)->load('variants', 'categories');

        return new ProductResource($product);
    }
}
