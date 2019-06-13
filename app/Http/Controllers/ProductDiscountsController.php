<?php

namespace App\Http\Controllers;

use App\Http\Controllers\helpers\ProductHelper;
use App\ProductDiscount;
use Illuminate\Http\Request;

class ProductDiscountsController extends Controller
{
    public function __construct()
    {
        $this->productHelper = new ProductHelper();
    }

    public function store(Request $request)
    {
        #read data from $request
        $quantity = $request->input('quantity');
        $max_quantity = $request->input('max_quantity');
        $product_id = $request->input('product_id');
        $price = $request->input('price');
        $sales_group_id = $request->input('sales_group_id');

        #create new discount in DB
        ProductDiscount::create(["product_id" => $product_id, "price" => $price, 'quantity' => $quantity, "max_quantity" => $max_quantity]);

        #prepare response object
        $product = $this->productHelper->getSingleProduct(2, $product_id);
        return response()->json(compact('product'), 200);
    }

    public function update(Request $request)
    {

        #read data from $request
        $quantity = $request->input('quantity');
        $max_quantity = $request->input('max_quantity');
        $product_id = $request->input('product_id');
        $price = $request->input('price');
        $sales_group_id = $request->input('sales_group_id');
        $product_discount_id = $request->input('product_discount_id');

        #update product_discount
        $productDiscount = ProductDiscount::find($product_discount_id);

        $productDiscount->quantity = $quantity;
        $productDiscount->max_quantity = $max_quantity;
        $productDiscount->price = $price;
        $productDiscount->sales_group_id = $sales_group_id;

        $productDiscount->save();

        #prepare response object
        $product = $this->productHelper->getSingleProduct(2, $product_id);
        return response()->json(compact('product'), 200);

    }

}