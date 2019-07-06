<?php

namespace App\Http\Controllers\helpers;

use App\Location;
use App\Order;
use App\OrderOption;
use App\OrderProduct;
use App\OrderStatus;
use App\PickupDate;
use App\Product;
use App\ProductDescription;
use App\ProductDiscount;
use App\ProductOption;
use App\ProductOptionValue;
use App\User;

class OrderHelper
{
    /**
     * function - make orders group by store
     */
    public function makeOrdersByStore($search_string, $start_date, $end_date, $sales_group_id)
    {
        $orders = Order::where('sales_group_id', $sales_group_id)->where('order_status_id', '!=', 6)->get();

        foreach ($orders as $order) {
            if ($search_string !== "") {
                if (
                    !(strpos($order['lastname'], $search_string) !== false)
                    && !(strpos($order['telephone'], $search_string) !== false)
                    && !(strpos($order['invoice_no'], $search_string) !== false)
                ) {
                    $orders = $orders->filter(function ($item) use ($order) {
                        return $item->order_id !== $order->order_id;
                    })->values();
                }
            }
        }
        foreach ($orders as $order) {
            $order["status_name"] = $order->status()->first()->name;
            $user = User::find($order->customer_id);
            $order["user"] = $user;
            $store = Location::find($order->store_id);
            $order["order_items"] = self::fetchOrderProducts($order->order_id);

            $order["store_name"] = $store->name;
        }

        $result = array();

        $orders = $orders->groupBy("store_id");
        foreach ($orders as $orderGroup) {
            $newRow = array();
            $newRow["store_id"] = $orderGroup[0]->store_id;
            $newRow["store_name"] = $orderGroup[0]->store_name;
            $newRow["order_products"] = self::makeStoreOrderProducts($orderGroup);

            array_push($result, $newRow);
        }

        return $result;
    }

    /**
     * helper function to fetch order product for certain order
     * @param Integer OrderId
     * @return Array(OrderProduct)
     */
    public function fetchOrderProducts($order_id)
    {
        $language_id = 2;
        $orderProducts = OrderProduct::where('order_id', $order_id)->get();
        if (count($orderProducts) < 1) {
            return $orderProducts;
        }
        foreach ($orderProducts as $orderProduct) {
            $options = array();
            $options = OrderOption::where('order_product_id', $orderProduct->order_product_id)->get();

            foreach ($options as $orderOption) {
                $product_option = ProductOption::find($orderOption["product_option_id"]);
                $product_option_value = ProductOptionValue::find($orderOption["product_option_value_id"]);

                $product_option_description = $product_option->optionDescriptions()->where('language_id', $language_id)->first();
                if ($product_option_description === null) {
                    $product_option_description = $product_option->optionDescriptions()->first();
                }
                if ($product_option_value) {
                    $product_option_value_description = $product_option_value->descriptions()->where('language_id', $language_id)->first();
                    if ($product_option_value_description === null) {
                        $product_option_value_description = $product_option_value->descriptions()->first();
                    }
                }
                $orderOption["option_name"] = $product_option_description->name;
                $orderOption["option_value_name"] = isset($product_option_value_description) ? $product_option_value_description->name : "";
                $orderOption["price"] = isset($product_option_value) ? number_format($product_option_value->price, 2) : 0;
            }
            $product_description = ProductDescription::where('product_id', $orderProduct->product_id)->where('language_id', $language_id)->first();
            if ($product_description === null) {
                $product_description = ProductDescription::where('product_id', $orderProduct->product_id)->first();
            }
            $orderProduct['name'] = $product_description->name;
            $orderProduct['options'] = $options;
        }

        return $orderProducts;
    }

    /**
     * helper function to add order products to order
     * @param Order
     * @return Order order with details
     */
    public function makeOrder($order)
    {
        $detailedOrder = array();

        $detailedOrder["invoice_no"] = $order->invoice_no;
        $detailedOrder["order_id"] = $order->order_id;
        $detailedOrder["store_id"] = $order->store_id;
        $store = Location::find($order->store_id);
        $detailedOrder["store_name"] = $store->name;
        $detailedOrder["store_address"] = $store->address;
        $detailedOrder["store_phone"] = $store->telephone;
        $detailedOrder["picked_date"] = $order->fax;
        $detailedOrder["create_date"] = $order->date_added;
        $detailedOrder["payment_method"] = $order->payment_method;
        $detailedOrder["total"] = $order->total;
        $detailedOrder["status_id"] = $order->order_status_id;
        $detailedOrder["status"] = OrderStatus::where('order_status_id', $order->order_status_id)->first()->name;
        $detailedOrder["order_items"] = self::fetchOrderProducts($order->order_id);
        $detailedOrder["comments"] = $order->comment;

        return $detailedOrder;
    }

    # self helper class
    public function makeStoreOrderProducts($orderGroup)
    {
        $order_products = array();
        foreach ($orderGroup as $order) {
            $order = json_decode(json_encode($order));
            $pickupDate = PickupDate::find($order->pickup_date_id);

            foreach ($order->order_items as $order_item) {
                $order_item = json_decode(json_encode($order_item));
                $product_id = $order_item->product_id;
                if (array_key_exists($product_id, $order_products)) {

                    array_push($order_products[$product_id],
                        [
                            "product_name" => $order_item->name,
                            "username" => $order->user->username,
                            "date" => $pickupDate->date,
                            "quantity" => $order_item->quantity,
                        ]);
                } else {
                    $order_products[$product_id] = array(
                        [
                            "product_name" => $order_item->name,
                            "username" => $order->user->username,
                            "date" => $pickupDate->date,
                            "quantity" => $order_item->quantity,
                        ]);
                };
            }
        }

        return collect($order_products)->values();
    }

    /**
     * helper function to fetch all orders with neccessary details from DB
     *
     * @param Request
     * @return void
     */
    public function makeOrders($search_string, $start_date, $end_date, $sales_group_id)
    {
        $orders = Order::where('sales_group_id', $sales_group_id)->where('order_status_id', '!=', 6)
            ->paginate(10);

        foreach ($orders as $order) {
            $user = User::find($order['customer_id']);
            $lastName = strtolower($user->username);
            $telephone = strtolower($user->phone);

            if ($search_string !== "") {
                if (
                    !(strpos($lastName, strtolower($search_string)) !== false)
                    && !(strpos($telephone, strtolower($search_string)) !== false)
                    && !(strpos($order['invoice_no'], $search_string) !== false)
                ) {
                    $orders = $orders->filter(function ($item) use ($order) {
                        return $item->order_id !== $order->order_id;
                    })->values();
                }
            }
        }
        foreach ($orders as $order) {
            $order["status_name"] = $order->status()->first()->name;
            $user = User::find($order->customer_id);
            $order["user"] = $user;
            $store = Location::find($order->store_id);
            $order["order_items"] = self::fetchOrderProducts($order->order_id);

            $order["store_name"] = $store->name;
        }
        return $orders;
    }

    public function makeOrdersByCondition($search_string, $start_date, $end_date, $sales_group_id)
    {
        $orders = Order::where('sales_group_id', $sales_group_id)->where('order_status_id', '!=', 6)
            ->paginate(10);

        foreach ($orders as $order) {
            if ($search_string !== "") {
                $order_products = $order->products()->where('name', 'like', "%$search_string%")->get();
                if (count($order_products) === 0) {
                    $orders = $orders->filter(function ($item) use ($order) {
                        return $item->order_id !== $order->order_id;
                    })->values();
                }
            }
        }
        foreach ($orders as $order) {
            $order["status_name"] = $order->status()->first()->name;
            $user = User::find($order->customer_id);
            $order["user"] = $user;
            $store = Location::find($order->store_id);
            $order["order_items"] = self::fetchOrderProducts($order->order_id);

            $order["store_name"] = $store->name;
        }
        return $orders;

    }

    /**
     * helper function to fetch order product options
     * @param Integer OrderProductId
     * @return Array(OrderOption)
     */
    public function fetchOrderPorductOption($order_product_id)
    {
        $options = OrderOption::where('order_product_id', $order_product_id)->where('order_status_id', '!=', 6)->get();

        return $options;
    }
    /**
     * @param Request $request
     * @param Integer $order_id
     * @return Array array of oc_order_product
     */
    public function createOrderProducts($request, $order_id)
    {

        foreach ($request->order_items as $orderItem) {
            $orderItem = json_decode(json_encode($orderItem));
            $order_product = OrderProduct::create([
                'order_id' => $order_id,
                'product_id' => $orderItem->product_id,
                'name' => $orderItem->name,
                'quantity' => $orderItem->quantity,
                'price' => $orderItem->price,
                'total' => $orderItem->total,
                'product_discount_id' => $orderItem->product_discount_id,
            ]);
        }

        $order_products = OrderProduct::where('order_id', $order_id)->get();

        return $order_products;
    }

    /**
     * function - modify stocks after customer paid the order
     * @param Array $orderId
     */
    public function modifyStockQuantity($orderId)
    {
        # find orderProducts
        $orderProducts = OrderProduct::where('order_id', $orderId)->get();
        foreach ($orderProducts as $orderItem) {
            # decrease product quantity
            $productDiscount = ProductDiscount::find($orderItem->product_discount_id);
            $productDiscount->decrement("quantity", $orderItem->quantity);
            if ($productDiscount !== null) {
                $productDiscount->decrement("quantity", $orderItem->quantity);
            }
        }
    }

    /**
     * @param Array $options
     * @param Integer $order_id
     * @param Integer $order_product_id
     * @return Array array of new oc_order_option
     */
    public function createOrderOptions($options, $order_id, $order_product_id)
    {
        $orderOptions = array();
        foreach ($options as $option) {
            $option = json_decode(json_encode($option));

            $orderOption = OrderOption::create([
                'order_id' => $order_id,
                'order_product_id' => $order_product_id,
                'product_option_id' => $option->product_option_id,
                'product_option_value_id' => $option->product_option_value_id,
            ]);

            array_push($orderOptions, $orderOption);
        }

        return $orderOptions;
    }

    public function makeDisplayOrders($orders)
    {
        $result_array = array();

        foreach ($orders as $order) {
            $display_order = array();
            $order = json_decode(json_encode($order));

            $products = self::fetchOrderProducts($order->order_id);
            $user = User::find($order->customer_id);

            foreach ($products as $product) {
                $display_order["product_name"] = $product->name;
                $display_order["product_quantity"] = $product->quantity;
                $display_order["order_id"] = $order->order_id;
                $display_order["customer_name"] = $user->username;
                $display_order["date"] = $order->date_added;
                $display_order["total"] = $order->total;
                array_push($result_array, $display_order);
            }

        }

        return $result_array;
    }

    public function makeOrderedProductsListByStore($search_string, $start_date, $end_date, $sales_group_id)
    {

        $orders = Order::where('sales_group_id', $sales_group_id)->where('order_status_id', '!=', 6)->get();
        foreach ($orders as $order) {
            if ($search_string !== "") {
                if (
                    !(strpos($order['lastname'], $search_string) !== false)
                    && !(strpos($order['telephone'], $search_string) !== false)
                    && !(strpos($order['invoice_no'], $search_string) !== false)
                ) {
                    $orders = $orders->filter(function ($item) use ($order) {
                        return $item->order_id !== $order->order_id;
                    })->values();
                }
            }
        }

        $array = array();
        $order_ids = $orders->pluck('order_id');

        $order_products = OrderProduct::whereIn('order_id', $order_ids)->get()->groupby("product_id");

        foreach ($order_products as $orderArray) {
            $total = 0;
            $quantity = 0;
            $product_id = $orderArray[0]->product_id;
            $product = Product::find($product_id);
            $product_name = $product->descriptions()->where("language_id", 2)->first()->name;
            foreach ($orderArray as $order) {
                $quantity += $order->quantity;
                $total += $order->total;
            }
            array_push($array, [
                "product" => $product_id,
                "product_name" => $product_name,
                "total" => $total,
                "quantity" => $quantity,
                "location_id" => $product->location,
                "store_name" => $product->location()->first()->name,
            ]);

        }

        $collection = collect($array);
        return $collection->groupBy("location_id")->values();
    }

    public function makeOrdersGroupByLocation($search_string, $start_date, $end_date, $location_id, $sales_group_id)
    {
        $orders = Order::where('sales_group_id', $sales_group_id)->where('order_status_id', '!=', 6)
            ->where("store_id", $location_id)
            ->paginate(6);
        foreach ($orders as $order) {
            if ($search_string !== "") {
                if (
                    !(strpos($order['lastname'], $search_string) !== false)
                    && !(strpos($order['telephone'], $search_string) !== false)
                    && !(strpos($order['invoice_no'], $search_string) !== false)
                ) {
                    $orders = $orders->filter(function ($item) use ($order) {
                        return $item->order_id !== $order->order_id;
                    })->values();
                }
            }
        }
        foreach ($orders as $order) {
            $order["status_name"] = $order->status()->first()->name;
            $user = User::find($order->customer_id);
            $order["user"] = $user;
            $store = Location::find($order->store_id);
            $order["order_items"] = self::fetchOrderProducts($order->order_id);

            $order["store_name"] = $store->name;
        }
        return $orders;

    }

    # check is there any orders expiring.
    public function checkOrderStatus()
    {
        # get datetime for now
        $dt = new \DateTime('now', new \DateTimeZone('Australia/Sydney'));
        
        # create the condition datetime
        // now + 6 mins
       $expired_dt = date('y-m-d H:i:s', strtotime("6 minutes", strtotime($dt->format('y-m-d H:i:s'))));

        # fetch all orders
        $orders = Order::where('date_added', '<=', $expired_dt)->where('order_status_id',2)->get();

        # modify stock

        foreach ($orders as $order) {
            # change order status
            $order->update(['order_status_id'=>4]);

            $this->modifyQuantityInStock($order->order_id);
        }

        return compact('orders');

    }

    # recalculate the stock quantity when order deleted or canceled
    public function modifyQuantityInStock($order_id)
    {
        
        # fetch all order_products according to order_id
        $orderProducts = OrderProduct::where('order_id',$order_id)->get();

        # modify quantity in db_table: product_discounts

        foreach ($orderProducts as $orderProduct) {
            $productDiscount = ProductDiscount::find($orderProduct->product_discount_id);

            $productDiscount->increment('quantity', $orderProduct->quantity);

            if($productDiscount->quantity > $productDiscount->max_quantity){
                $productDiscount->update(['quantity'=>$productDiscount->max_quantity]);
            }


        }

    }
}
