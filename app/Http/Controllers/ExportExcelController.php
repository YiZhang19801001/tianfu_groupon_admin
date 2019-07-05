<?php

namespace App\Http\Controllers;

use App\Exports\WorkSheetsExport;
use App\Http\Controllers\helpers\ProductHelper;
use App\Order;
use App\OrderProduct;
use App\PickupDate;
use App\ProductDescription;
use App\ProductDiscount;
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
        $sales_group_id = $request->input('sales_group_id');

        # fetch products from DB
        $productDiscounts = ProductDiscount::where('sales_group_id', $sales_group_id)->get();
        # build excel file headers
        $headingsTop = ['name'];
        $headingsBottom = ['quantity'];
        $customer_array = [];

        foreach ($productDiscounts as $productDiscount) {
            $description = ProductDescription::where('language_id', 2)->where('product_id', $productDiscount->product_id)->first();
            $name = $description->name;
            array_push($headingsTop, $name);

            $price = '$' . number_format($productDiscount->price, 2);

            array_push($headingsBottom, $price);
        }

        array_push($headingsTop, 'Amount');
        array_push($headingsTop, 'pickup date & location');

        # fetch orders
        $orders = Order::where('sales_group_id', $sales_group_id)->where('order_status_id', 2)->get();
        foreach ($orders as $order) {
            $pickupDate = PickupDate::find($order->pickup_date_id);
            $order['pickup_date'] = $pickupDate->date;
        }
        $newTotalArray = [];
        foreach ($orders as $order) {
            $orderProducts = OrderProduct::where('order_id', $order->order_id)->get();
            $newArray = [$order->order_id];
            foreach ($productDiscounts as $productDiscount) {
                $matchedOrderProduct = $orderProducts->where('product_discount_id', $productDiscount->product_discount_id)->first();
                if ($matchedOrderProduct === null) {
                    array_push($newArray, 0);
                } else {
                    array_push($newArray, $matchedOrderProduct->quantity);
                }
            }
            array_push($newArray, $order->total);

            array_push($newArray, $order->store_name . ' ' . $order->pickup_date);

            array_push($newTotalArray, $newArray);
        }
        array_push($customer_array, ['data' => $newTotalArray, 'title' => '总数']);

        $orderGroups = $orders->groupBy('store_id');

        foreach ($orderGroups as $orderGroup) {
            $newStoreArray = [];
            foreach ($orderGroup as $order) {
                $orderProducts = OrderProduct::where('order_id', $order->order_id)->get();
                $newArray = [$order->order_id];
                foreach ($productDiscounts as $productDiscount) {
                    $matchedOrderProduct = $orderProducts->where('product_discount_id', $productDiscount->product_discount_id)->first();
                    if ($matchedOrderProduct === null) {
                        array_push($newArray, 0);
                    } else {
                        array_push($newArray, $matchedOrderProduct->quantity);
                    }
                }
                array_push($newArray, $order->total);
                array_push($newArray, $order->store_name . ' ' . $order->pickup_date);

                array_push($newStoreArray, $newArray);
            }
            array_push($customer_array, ['data' => $newStoreArray, 'title' => $orderGroup[0]['store_name']]);
        }

        $orderGroups = $orders->groupBy('pickup_date');

        foreach ($orderGroups as $orderGroup) {
            $newDateArray = [];
            foreach ($orderGroup as $order) {
                $orderProducts = OrderProduct::where('order_id', $order->order_id)->get();
                $newArray = [$order->order_id];
                foreach ($productDiscounts as $productDiscount) {
                    $matchedOrderProduct = $orderProducts->where('product_discount_id', $productDiscount->product_discount_id)->first();
                    if ($matchedOrderProduct === null) {
                        array_push($newArray, 0);
                    } else {
                        array_push($newArray, $matchedOrderProduct->quantity);
                    }
                }
                array_push($newArray, $order->total);
                array_push($newArray, $order->store_name . ' ' . $order->pickup_date);

                array_push($newDateArray, $newArray);
            }
            array_push($customer_array, ['data' => $newDateArray, 'title' => $orderGroup[0]['pickup_date'] . ' 总数']);
        }

        $headings = [$headingsTop, $headingsBottom];
        $export = new WorkSheetsExport($customer_array, $headings);

        return Excel::download($export, 'worksheets.xlsx');
    }
}
