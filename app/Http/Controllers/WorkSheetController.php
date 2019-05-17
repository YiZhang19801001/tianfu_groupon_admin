<?php

namespace App\Http\Controllers;

use App\Order;
use App\Product;
use Illuminate\Http\Request;

class WorkSheetController extends Controller
{
    public function index(Request $request)
    {
        $defaultStartDate = new \DateTime("2019-05-01", new \DateTimeZone("Australia/Sydney"));
        $defaultEndDate = new \DateTime("2019-05-30", new \DateTimeZone("Australia/Sydney"));
        $startDate = $request->input('startDate', $defaultStartDate);
        $endDate = $request->input('endDate', $defaultEndDate);
        $orders = Order::with("products")->whereBetween('pickup_date', [$startDate, $endDate])->get();
        $products = Product::where('status', 0)->get();
        $tableHeaders = array();
        foreach ($products as $product) {
            $productId = $product->product_id;
            $productDescription = $product->descriptions()->where('language_id', 2)->first();
            $productName = $productDescription->name;
            $price = $product->price;
            $newHeader = compact('productId', 'productName', 'price');
            array_push($tableHeaders, $newHeader);
        }

        return response()->json(compact('startDate', 'endDate', 'orders', 'tableHeaders'), 200);
    }
}
