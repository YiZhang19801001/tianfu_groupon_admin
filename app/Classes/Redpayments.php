<?php
namespace App\Classes;

use App\Order;

class Redpayments
{
    private $key = "";
    private $version = "";

    public function __construct($method)
    {
        if ($method === "WECHAT") {

            $this->key = config("redpayments.wechat_key");
        } else {
            $this->key = config("redpayments.ali_key");
        }
        $this->version = config("redpayments.version");
    }

    public function create($request)
    {
        // read input
        $requestBody = [
            "amount" => $request->total,
            "channel" => $request->channel,
            "currency" => $request->input("currency", "AUD"),
            "item" => $request->input("item", config("redpayments.items")),
            "mchNo" => config("redpayments.mchNo"),
            "mchOrderNo" => $request->invoice_no,
            "notifyUrl" => config("app.paymentNotifycationUrl", "/$request->channel"),
            "params" => $request->input("params", config("redpayments.params")),
            "payWay" => $request->input("payway", config("redpayments.payWay")),
            "quantity" => $request->quantity,
            "returnUrl" => config("app.returnUrl") . "/$request->channel",
            "storeNo" => config("redpayments.storeNo"),
            "timestamp" => $request->timestamps,
            "version" => $this->version,
        ];
        // add sign
        $requestBody["sign"] = self::getSign($requestBody);

        // call web api
        $data_string = json_encode($requestBody);

        $url = config("redpayments.createUrl");

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json; Charset=UTF-8', 'Content-Length: ' . strlen($data_string)));

        $curl_response = curl_exec($curl);

// make reposnse object
        $responseBody = json_decode($curl_response);
// return reponse
        return $responseBody;
    }

    public function query($paymentId)
    {

        $order = Order::where('payment_code', $paymentId)->first();
        $date = new \DateTime("now", new \DateTimeZone("Australia/Sydney"));
        $requestBody = [
            "mchNo" => config("redpayments.mchNo"),
            // "mchOrderNo" => isset($order) ? $order->invoice_no : "",
            "orderNo" => $paymentId,
            "timestamp" => $date->getTimestamp(),
            "version" => $this->version,
        ];
        // add sign
        $requestBody["sign"] = self::getSign($requestBody);

        // call web api
        $data_string = json_encode($requestBody);

        $url = config("redpayments.queryUrl");

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json; Chartset: utf8', 'Content-Length: ' . strlen($data_string)));

        $curl_response = curl_exec($curl);

        // make reposnse object
        $responseBody = json_decode($curl_response);

        return $responseBody;

        // Todo:: finish the logic

    }

    public function handleNotify($request)
    {
        $decode = $request->all();
        $message = json_encode($request);
        $status = $decode->status;
        if ($status == 'SUCCEEDED') {
            $order = Order::where("payment_code", $response->orderNo)->first();
            if ($order !== null) {
                $order->order_status_id = 2;
                $order->save();
            }
        }
        return compact("message", "status");
    }

    public function getSign($params)
    {
        $str = '';
        ksort($params);
        foreach ($params as $k => $v) {
            $str .= "$k=$v&";
        }
        $str .= "key=$this->key";
        return md5($str);
    }

}
