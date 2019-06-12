<?php

namespace App\Http\Controllers\helpers;

use App\Category;
use App\Location;
use App\OrderProduct;
use App\Product;
use App\User;

class ReportsHelper
{
    /**
     * function - make report summary
     *
     * @param Collections $orders
     * @param Integer $language_id
     * @return Array
     */
    public function makeSummary($orders, $language_id)
    {
        $summary = array();

// 1. caculate sales
        $sum = 0;
        foreach ($orders as $order) {
            $order = json_decode(json_encode($order));
            $sum += $order->total;
        }

        $summary["sales"] = $sum;
        $summary["sales_by_store"] = self::makeSalesByStore($orders);
        $summary["sales_by_date"] = self::makeSalesByDate($orders);
        $summary["sales_by_payment"] = self::makeSalesByPayment($orders);
        $summary["sales_by_product"] = self::makeSalesByProduct($orders, $language_id);
        $summary["sales_by_customer"] = self::makeSalesByCustomer($orders);
        $summary["sales_by_category"] = self::categoryReport($orders, $language_id);

        return $summary;
    }
    /**
     * function - make reports for sales by products
     *
     * @param Collection $orders
     * @param Integer $language_id
     * @return Array $salesByProduct
     */
    public function makeSalesByProduct($orders, $language_id)
    {

        $array = array();
        $order_ids = $orders->pluck('order_id');

        $order_products = OrderProduct::whereIn('order_id', $order_ids)->get()->groupby("product_id");

        foreach ($order_products as $orderArray) {
            $total = 0;
            $product_id = $orderArray[0]->product_id;

            $product_name = Product::find($product_id)->descriptions()->where("language_id", $language_id)->first()->name;

            foreach ($orderArray as $order) {
                $total += $order->total;
            }

            array_push($array, [
                "product" => $product_id,
                "product_name" => $product_name,
                "total" => $total,
            ]);

        }

        return $array;
    }

    /**
     * function - make reports for sales by stores
     *
     * @param Collections $orders
     * @return Array
     */
    public function makeSalesByStore($orders)
    {
        $sum_by_store = array();
        $orders_by_store = $orders->groupby("store_id");

        foreach ($orders_by_store as $orderArray) {
            $total = 0;
            $store_id = $orderArray[0]->store_id;
            $name = Location::find($store_id)->name;
            foreach ($orderArray as $order) {
                $total += $order->total;
            }
            array_push($sum_by_store, ["store_id" => $store_id, "store_name" => $name, "total" => $total, "quantity" => count($orderArray)]);
        }
        return $sum_by_store;
    }

    /**
     * funtion - make reports for sales by date
     *
     * @param Collections $orders
     * @return Array
     */
    public function makeSalesByDate($orders)
    {
        $sum_by_date = array();
        $orders_by_date = $orders->groupby("fax");
        foreach ($orders_by_date as $orderArray) {
            $total = 0;
            foreach ($orderArray as $order) {
                $total += $order->total;
            }
            array_push($sum_by_date, ["date" => $orderArray[0]->fax, "total" => $total, "quantity" => count($orderArray)]);
        }
        return $sum_by_date;
    }

    /**
     * funtion - make reports for sales by payment method
     *
     * @param Collections $orders
     * @return Array
     */
    public function makeSalesByPayment($orders)
    {
        $sum_by_payment = array();
        $orders_by_payment = $orders->groupby("payment_method");
        foreach ($orders_by_payment as $key => $orderArray) {
            $result = array();
            $orders_by_date_added = $orderArray->groupby("date_added");
            foreach ($orders_by_date_added as $orderArray2) {
                $total = 0;
                foreach ($orderArray2 as $order) {
                    $total += $order->total;
                }
                array_push($result, ["date" => $orderArray2[0]->date_added, "total" => $total]);
            }
            array_push($sum_by_payment, ["payment_method" => $key, "data" => $result]);
        }
        return $sum_by_payment;
    }

    /**
     * function - make reports for sales by customers
     *
     * @param Collections $orders
     * @return Array
     */
    public function makeSalesByCustomer($orders)
    {
        $sum_by_customer = array();
        $orders_by_customer = $orders->groupby("customer_id");
        foreach ($orders_by_customer as $orderArray) {
            $total = 0;
            $username = User::find($orderArray[0]->customer_id)->username;
            foreach ($orderArray as $order) {
                $total += $order->total;
            }
            array_push($sum_by_customer, ["customer_id" => $orderArray[0]->customer_id, "username" => $username, "total" => $total, "quantity" => count($orderArray)]);
        }
        return $sum_by_customer;
    }

    public function categoryReport($orders, $language_id)
    {
        $array = array();
        $order_ids = $orders->pluck('order_id');

        $orderProducts = OrderProduct::whereIn('order_id', $order_ids)->get();

        foreach ($orderProducts as $orderProduct) {
            $orderProduct["category_id"] = $orderProduct->category()->first()->category_id;
        }

        $orderProductsByCategory = $orderProducts->groupby("category_id")->values();
        foreach ($orderProductsByCategory as $orderProductByCategory) {
            $total = 0;
            $quantity = 0;
            $category_id = $orderProductByCategory[0]->category_id;
            $category_description = Category::find($category_id)->descriptions()->where("language_id", $language_id)->first();
            if ($category_description === null) {
                $category_description = Category::find($category_id)->descriptions()->first();
            }
            $category_name = $category_description->name;
            foreach ($orderProductByCategory as $order) {
                $quantity += $order->quantity;
                $total += $order->total;
            }
            array_push($array, ["category_id" => $category_id, "category_name" => $category_name, "total" => $total, "quantity" => $quantity]);

        }

        return $array;

    }

    public function productReport($orders, $language_id)
    {

        $array = array();
        $order_ids = $orders->pluck('order_id');

        $order_products = OrderProduct::whereIn('order_id', $order_ids)->get()->groupby("product_id");

        foreach ($order_products as $orderArray) {
            $total = 0;
            $quantity = 0;
            $product_id = $orderArray[0]->product_id;
            $product = Product::find($product_id);
            $product_name = $product->descriptions()->where("language_id", $language_id)->first()->name;
            foreach ($orderArray as $order) {
                $quantity += $order->quantity;
                $total += $order->total;
            }
            array_push($array, [
                "product" => $product_id,
                "product_name" => $product_name,
                "total" => $total,
                "quantity" => $quantity,
                "price" => $product->price,
            ]);

        }

        return $array;

    }

}
