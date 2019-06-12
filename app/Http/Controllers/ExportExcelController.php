<?php

namespace App\Http\Controllers;

use App\Exports\WorkSheetsExport;
use App\Http\Controllers\helpers\ProductHelper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportExcelController extends Controller
{

    public function __construct()
    {
        $this->productHelper = new ProductHelper();
    }

    public function index(Request $request)
    {
        # read inputs
        $language_id = $request->input('language_id', 1);
        $status = $request->input('status', 0);
        $search_string = $request->input('search_string', '');
        $user_group_id = $request->input('user_group_id', 3);

        $products = $this->productHelper->getProductsList($language_id, $status, $search_string, $user_group_id);
        $orders = [
            array('order_id' => 1, 'invoice_no' => 1),
        ];

        $headingsTop = ['name'];
        $headingsBottom = ['quantity'];
        $customer_array = [];

        # make headings
        foreach ($products[0]["products"] as $product) {

            $product = json_decode(json_encode($product));

            array_push($headingsTop, $product->name);
            array_push($headingsBottom, $product->price);
        }

        # mapping order_products quantity to each product

        foreach ($orders as $order) {

            $newArray = [];
            # 1. get order products quantity
            $orderProducts = $order->orderProducts()->get();
            # 2. mapping values to each products
            foreach ($products as $product) {
                // if product id matched return quantity, otherwise return 0
                $matchedOrderProduct = $orderProducts->where($product->product_id)->first();

                if ($matchedOrderProduct === null) {
                    $newArray[] = 0;
                } else {
                    $newArray[] = $matchedOrderProduct->quantity;
                }
            }

            $customer_array[] = $newArray;
        }

        $headings = [$headingsTop, $headingsBottom];
        $export = new WorkSheetsExport($customer_array, 'new sheets title', $headings);

        // Excel::create('Customer Data', function ($excel) use ($customer_array) {
        //     $excel->setTitle('Customer Data');
        //     $excel->sheet('Customer Data', function ($sheet) use ($customer_array) {
        //         $sheet->fromArray($customer_array, null, 'A1', false, false);
        //     });
        // })->download('xlsx');

        // return Excel::download($customer_array, 'customers.xlsx');
        return Excel::download($export, 'users.xlsx');

        // return response()->json($customer_array);
    }
}
