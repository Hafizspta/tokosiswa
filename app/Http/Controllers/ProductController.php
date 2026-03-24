<?php

namespace App\Http\Controllers;

use App\Data\ProductData;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show(Product $product)
    {
        $product = ProductData::fromModel($product, true);

        return view('product.show', compact('product'));
    }
}
