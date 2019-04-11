<?php

namespace App\Http\Controllers;

use App\Classes\Poli;
use App\Classes\Redpayments;
use App\Http\Controllers\helpers\OrderHelper;
use App\Order;
use App\PaymentNotify;
use App\Product;
use App\ProductDiscount;
use App\User;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    private $OrderHelper;

    public function __construct()
    {
        $this->OrderHelper = new OrderHelper();
    }

    public function store(Request $request)
    {

        //Todo: validation

        //1. create order

        $dt = new \DateTime("now", new \DateTimeZone('Australia/Sydney'));
        $today = $dt->format('y-m-d');

        $token = $request->bearerToken();
        $user = User::where("api_token", $token)->first();

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
            'order_status_id' => 6,
        ];
        $order = Order::create($input);
        if (isset($request->customerComments)) {
            $order->comment = $request->customerComments;
            $order->save();
        }

        $order_products = $this->OrderHelper->createOrderProducts($request, $order->order_id);

        //2. create payment
        $approvel_url = "";
        $payment_id = "";
        $order_status = "";
        $testing_message = "";

        # Paypal
        // if ($request->channel === "Paypal") {
        //     $paypal = new Paypal();
        //     $response = $paypayl->create($request);

        //     // return errors when fail
        //     if (!isset($response->state)) {
        //         return response()->json(["status" => "error"]);
        //     }
        //     foreach ($response->links as $link) {
        //         if ($link->rel === "approval_url") {
        //             $approvel_url = $link->href;
        //         }
        //     }
        //     $order_status = $response->state;
        //     $payment_id = $response->id;
        // }

        if ($request->channel === "POLI") {
            $poli = new Poli();
            $response = $poli->create($request);

            $order_status = $response->Success ? "success" : "fail";
            $approvel_url = $response->NavigateURL;
            $payment_id = $response->TransactionRefNo;
        }

        if ($request->channel === "WECHAT" || $request->channel === "ALIPAY") {
            $redpayments = new Redpayments($request->channel);
            $response = $redpayments->create($request);
            // return response()->json(compact("response"), 200);
            $order_status = $response->code == 0 ? "success" : "fail";
            if ($response->code == 0) {

                $data = json_decode(json_encode($response->data));
                $approvel_url = $data->qrCode;
                $payment_id = $request->channel === "ALIPAY" ? $data->mchOrderNo : $request->invoice_no;
            }

            $testing_message = json_encode($response);
        }

        $order->payment_code = $payment_id;
        $order->save();

        return response()->json([
            "status" => $order_status,
            "approvel_url" => $approvel_url,
            "payment_id" => $payment_id,
            "message" => $testing_message,
        ], 200);
    }

    //* receive notify from payment api, if success paid, then change order status in database.
    public function notify(Request $request, $pay_way)
    {

        $dt = new \DateTime("now", new \DateTimeZone('Australia/Sydney'));
        $date_received = $dt->format('y-m-d h:m:s');
        $status = "";
        $message = "can not find $pay_way";
        $result_array = array();
        if ($pay_way === 'poli') {
            $poli = new Poli();
            $result_array = $poli->handleNotify($request);
        }
        if ($pay_way === 'WECHAT' || $pay_way === 'ALIPAY') {
            $redpayments = new Redpayments();
            $result_array = $redpayments->handleNotify($request);
        }

        $message = $result_array["message"];
        $status = $result_array["status"];

        PaymentNotify::create(compact("date_received", "message", "pay_way", 'status'));

    }

    public function query(Request $request)
    {
        $channel = $request->channel;
        $payment_id = $request->payment_id;
        $payment_information = array();
        if ($channel === 'poli') {
            $poli = new Poli();
            $response = $poli->query($payment_id);
            $response = json_decode(json_encode($response));
            $payment_information = array(
                'error_code' => $response->ErrorCode,
                'date_time' => $response->MerchantEstablishedDateTime,
                'status' => $response->TransactionStatus,
                'bill_amount' => $response->PaymentAmount,
                'paid_amount' => $response->AmountPaid,
                'transaction_id' => $response->TransactionRefNo,
            );
        }

        if ($channel === 'WECHAT' || $channel === 'ALIPAY') {
            $redpayments = new Redpayments($channel);
            $response = $redpayments->query($payment_id);

            $response = json_decode(json_encode($response));

            $payment_information = array(
                'error_code' => $response->code,
                'date_time' => $response->data->paidTime,
                'status' => $response->data->resultCode,
                'bill_amount' => $response->data->orderAmount,
                'paid_amount' => $response->data->orderAmount,
                'transaction_id' => $response->data->orderNo,
            );
        }

        if ($channel === 'paypal') {

        }

        return response()->json(compact("payment_information"), 200);

    }

    public function fetchCanceledOrder(Request $request)
    {
        $channel = $request->channel;
        $payment_id = $request->payment_id;

        if ($channel === 'poli') {
            $poli = new Poli();
            $response = $poli->query($payment_id);
            $response = json_decode(json_encode($response));
            $payment_code = $response->TransactionRefNo;
            $dbOrder = Order::where('payment_code', $payment_code)->first();
            $order = $this->OrderHelper->makeOrder($dbOrder);
        }
        return response()->json(compact("order"), 200);
    }
}
