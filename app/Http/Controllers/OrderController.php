<?php

namespace App\Http\Controllers;

use App\Http\Controllers\helpers\OrderHelper;
use App\Http\Controllers\helpers\ProductHelper;
use App\Option;
use App\Order;
use App\OrderOption;
use App\OrderProduct;
use App\Product;
use App\ProductDiscount;
use App\User;
use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * constructor funtion
     * @return Void create an instance of helper class - ReportsControllerHelper
     */
    public function __construct()
    {
        $this->helper = new OrderHelper();
        $this->productHelper = new ProductHelper();
    }
    /**
     * show single order details
     *
     * @param Request $request
     * @return void
     */
    public function show($order_id)
    {
        $dbOrder = Order::find($order_id);
        if ($dbOrder === null) {
            return response()->json(['errors' => "can not found order"], 400);
        }
        $order = $this->helper->makeOrder($dbOrder);

        return response()->json(compact('order'), 200);
    }

    /**
     * update order details
     * @param Request
     * @param Integer $order_id
     * @return ResponseJson with orders and order
     */
    public function update(Request $request, $order_id)
    {
        $dt = new \DateTime("now", new \DateTimeZone('Australia/Sydney'));
        $today = $dt->format('Y-m-d');

        $end_date = isset($request->end_date) ? $request->end_date : $today;
        $start_date = isset($request->start_date) ? $request->start_date : $today;

        $orders = $this->helper->makeOrders($request, $start_date, $end_date);
        $dbOrder = Order::find($order_id);
        if ($dbOrder === null) {
            return response()->json(['errors' => "can not found order"], 400);
        }
        $order = $this->helper->makeOrder($dbOrder);

        return response()->json(compact("orders", "order"), 200);
    }

    /**
     * fetch all orders for cPanel use
     * @param Request $request
     * @return void
     */
    public function getAll(Request $request)
    {
        $method = isset($request->method) ? $request->method : "all";
        $search_string = isset($request->search_string) ? $request->search_string : "";

        $dt = new \DateTime("now", new \DateTimeZone('Australia/Sydney'));
        $today = $dt->format('Y-m-d');

        $end_date = isset($request->end_date) ? $request->end_date : $today;
        $start_date = isset($request->start_date) ? $request->start_date : $today;

        switch ($method) {
            case 'all':
                if (isset($request->location_id)) {
                    $orders = $this->helper->makeOrdersGroupByLocation($search_string, $start_date, $end_date, $request->location_id);
                } else {

                    $orders = $this->helper->makeOrders($search_string, $start_date, $end_date);
                }
                break;
            case 'byStore':
                $orders = $this->helper->makeOrdersByStore($search_string, $start_date, $end_date);
                break;
            case 'adv':
                $orders = $this->helper->makeOrdersByCondition($search_string, $start_date, $end_date);
                break;
            case 'products':
                $orders = $this->helper->makeOrderedProductsListByStore($search_string, $start_date, $end_date);
                break;
            default:
                $orders = $this->helper->makeOrders($search_string, $start_date, $end_date);
                break;
        }

        return response()->json(compact("orders"), 200);
    }

    /**
     * show all orders for current user
     * @param void
     * @return Response
     */
    public function index(Request $request)
    {
        // get logged in user
        $user = $request->user();
        // response order with details container
        $responseOrders = array();
        // Todo:: paginate
        $orders = Order::where('customer_id', $user->user_id)->orderByDesc("date_added")->get();
        // add details to each order
        foreach ($orders as $order) {
            $detailedOrder = $this->helper->makeOrder($order);
            array_push($responseOrders, $detailedOrder);
        }

        return response()->json(['orders' => $responseOrders], 200);
    }

    /**
     * create new order in DB
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request)
    {
        //1. validation
        //2. create
        $dt = new DateTime("now", new DateTimeZone('Australia/Sydney'));
        $today = $dt->format('y-m-d');

        $user = $request->user();

        if (isset($request->order_id)) {
            $order = Order::find($request->order_id);
            if ($order !== null) {
                $order->delete();

                $order_products = $order->products()->get();
                foreach ($order_products as $order_product) {

                    $target_product = Product::find($order_product->product_id)->increment('quantity', $order_product->quantity);
                    $target_productDiscount = ProductDiscount::where("product_id", $order_product->product_id)->first();
                    if ($target_productDiscount !== null) {
                        $target_productDiscount->increment("quantity", $order_product->quantity);
                    }
                    $order_product->delete();
                }
            }
        }

        $input = [
            'invoice_no' => $request->invoice_no,
            'store_id' => isset($request->store_id) ? $request->store_id : "",
            'customer_id' => $user->user_id,
            'fax' => isset($request->fax) ? $request->fax : "",
            'payment_method' => isset($request->payment_method) ? $request->payment_method : "",
            'total' => isset($request->total) ? $request->total : "",
            'date_added' => $today,
            'date_modified' => $today,
            'order_status_id' => $request->order_status_id,
        ];

        $order = Order::create($input);
        if (isset($request->customerComments)) {
            $order->comment = $request->customerComments;
            $order->save();
        }

        $order_products = $this->helper->createOrderProducts($request, $order->order_id);

        // make reponse body

        //

        // Todo:: paginate
        $language_id = isset($request->language_id) ? $request->language_id : config('app.default_language_id');
        $products = $this->productHelper->getProductsList($language_id, 0, "", 2);

        return response()->json(compact("products"));
    }

    /**
     * covert order_items into shopping cart list for front end use
     *
     * @param Request $request
     * @return Response $ShoppingCartList as json object
     */
    public function convertOrderToShoppingCartList(Request $request)
    {
        $language_id = $request->input("language_id");
        $orderItems = $request->items;
        $order_id = $request->order_id;
        // response result container
        $shoppingCartList = array();

        // maping value
        foreach ($orderItems as $orderItem) {
            $orderItem = json_decode(json_encode($orderItem));
            $newOrderItem = array();
            // fetch product by product_id
            $product_id = $orderItem->product_id;
            $product = Product::find($product_id);
            // fetch product name
            $productDescription = $product->descriptions()->where('language_id', $language_id)->first();
            if ($productDescription === null) {
                $productDescription = $product->descriptions()->first();
            }

            $product['name'] = $productDescription->name;

            // fetch product options
            $options = array();
            $product_options = $product->options()->get();
            foreach ($product_options as $product_option) {
                $newOption = array();
                $productOptionDescription = $product_option->optionDescriptions()->where('language_id', $language_id)->first();
                if ($productOptionDescription === null) {
                    $productOptionDescription = $product_option->optionDescriptions()->first();

                }
                $newOption['option_name'] = $productOptionDescription->name;
                $newOption['product_option_id'] = $product_option->product_option_id;
                $newOption['required'] = $product_option->required;
                $newOption['type'] = $product_option->option->type;

                $newValues = array();

                $productOptionValues = $product_option->optionValues()->get();
                foreach ($productOptionValues as $productOptionValue) {
                    $newValue = array();

                    $productOptionValueDescription = $productOptionValue->descriptions()->where('language_id', $language_id)->first();
                    if ($productOptionValueDescription === null) {
                        $productOptionValueDescription = $productOptionValue->descriptions()->first();
                    }

                    $newValue['name'] = $productOptionValueDescription->name;
                    $newValue['price'] = number_format($productOptionValue->price, 2);
                    $newValue['product_option_value_id'] = $productOptionValue->product_option_value_id;
                    array_push($newValues, $newValue);
                }
                $newOption['values'] = $newValues;
                array_push($options, $newOption);

            }
            $product['options'] = $options;

            // fetch order item choices
            $choices = array();
            foreach ($orderItem->options as $orderOption) {
                $orderOption = json_decode(json_encode($orderOption));

                // get value details
                $orderItemOptionValueName = "";
                $orderItemOptionValuePrice = "";
                foreach ($options as $option) {
                    foreach ($option["values"] as $optionValue) {
                        if ($optionValue["product_option_value_id"] == $orderOption->product_option_value_id) {
                            $orderItemOptionValueName = $optionValue["name"];
                            $orderItemOptionValuePrice = $optionValue["price"];
                        }
                    }

                }

                // chech duplicate product option exist in $choice or not
                $flag = false;
                $index = 0;
                for ($i = 0; $i < count($choices); $i++) {
                    $choice = $choices[$i];
                    if ($choice["productOption"] == $orderOption->product_option_id) {
                        $flag = true;
                        $index = $i;
                    }

                }

                // if no duplicate record in $choices

                if (!$flag) {

                    $choice = array();
                    $choice["productOption"] = $orderOption->product_option_id;
                    $choice["productOptionValue"] = [
                        "name" => $orderItemOptionValueName,
                        "price" => $orderItemOptionValuePrice,
                        "product_option_value_id" => $orderOption->product_option_value_id,
                    ];

                    array_push($choices, $choice);
                } else {

                    // if duplicate record find in $choices
                    array_push($choices[$index]["productOptionValue"], [
                        "name" => $orderItemOptionValueName,
                        "price" => $orderItemOptionValuePrice,
                        "product_option_value_id" => $orderOption->product_option_value_id,
                    ]);
                }

            }

            $product["choices"] = $choices;

            $newOrderItem["item"] = $product;
            $newOrderItem["quantity"] = $orderItem->quantity;

            array_push($shoppingCartList, $newOrderItem);
        }

        return response()->json(compact("shoppingCartList", "order_id"), 200);
    }

    public function remove(Request $request, $order_id)
    {
        Order::destroy($order_id);
        OrderProduct::where('order_id', $order_id)->delete();
        OrderOption::where('order_id', $order_id)->delete();

// make reponse body
        // get logged in user
        $user = $request->user();
// response order with details container
        $responseOrders = array();
// Todo:: paginate
        $orders = Order::where('customer_id', $user->user_id)->get();
// add details to each order
        foreach ($orders as $order) {
            $detailedOrder = $this->helper->makeOrder($order);
            array_push($responseOrders, $detailedOrder);
        }

// return response
        return response()->json($responseOrders, 200);

    }

    /**
     * delete order
     * @param Integer $oc_order_id
     * @return Void
     */
    public function deleteOrder($order_id)
    {
        Order::destroy($order_id);
        OrderProduct::where('order_id', $order_id)->delete();
        OrderOption::where('order_id', $order_id)->delete();
    }
    /**
     * update order status
     * @param Request
     * @return ResponseJson
     */
    public function updateStatus(Request $request, $order_id)
    {
        $newOrder = Order::find($order_id);

        $newOrder->order_status_id = $request->order_status_id;

        $newOrder->save();

        $search_string = isset($request->search_string) ? $request->search_string : "";

        $dt = new \DateTime("now", new \DateTimeZone('Australia/Sydney'));
        $today = $dt->format('Y-m-d');

        $end_date = isset($request->end_date) ? $request->end_date : $today;
        $start_date = isset($request->start_date) ? $request->start_date : $today;
        $orders = $this->helper->makeOrders($search_string, $start_date, $end_date);
        $dbOrder = Order::find($order_id);
        if ($dbOrder === null) {
            return response()->json(['errors' => "can not found order"], 400);
        }
        $order = $this->helper->makeOrder($dbOrder);

        return response()->json(compact("order", "orders"), 200);
    }

}
