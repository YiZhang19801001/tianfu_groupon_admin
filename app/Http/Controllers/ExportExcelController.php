<?php

namespace App\Http\Controllers;

use App\Http\Controllers\helpers\ProductHelper;
use Excel;
use Illuminate\Http\Request;

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

        $customer_array[] = array('product_id', 'product_name', 'image', 'price', 'store_name');
        foreach ($products[0]["products"] as $product) {

            $product = json_decode(json_encode($product));
            $customer_array[] = array(
                'product_id' => $product->product_id,
                'product_name' => $product->name,
                'image' => $product->image,
                'price' => $product->price,
                'store_name' => $product->store_name,
            );
        }

        \Excel::create('Customer Data', function ($excel) use ($customer_array) {
            $excel->setTitle('Customer Data');
            $excel->sheet('Customer Data', function ($sheet) use ($customer_array) {
                $sheet->fromArray($customer_array, null, 'A1', false, false);
            });
        })->download('xlsx');
    }
}
