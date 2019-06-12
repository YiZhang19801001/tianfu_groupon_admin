<?php
namespace App\Classes;

use App\Order;

class Poli
{

    public function create($request)
    {

        $url = config("poli.createUrl");
        $reference = config("poli.reference");
        $auth = base64_encode(config("poli.auth"));
        $homepage = config("app.homepage");
        $payment_returnUrl = config("app.returnUrl");

        $requestBody = [
            "Amount" => $request->total,
            "CurrencyCode" => isset($request->currency) ? $request->currency : "AUD",
            "MerchantReference" => $reference,
            "MerchantHomepageURL" => $homepage,
            "SuccessURL" => "$payment_returnUrl/poli",
            "FailureURL" => $homepage,
            "CancellationURL" => config('app.paymentCancelUrl') . "/poli",
            "NotificationURL" => config('app.paymentNotifycationUrl') . "/poli",
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', "Authorization: Basic " . $auth));

        $curl_response = curl_exec($ch);
        // Check the return value of curl_exec(), too
        if ($curl_response === false) {
            throw new \Exception(curl_error($ch), curl_errno($ch));
        }
        $responseBody = json_decode($curl_response);

        return $responseBody;
    }

    public function query($token)
    {
        $auth = base64_encode(config("poli.auth"));
        $header = array();
        $header[] = 'Authorization: Basic ' . $auth;
        $ch = curl_init(config("poli.queryUrl") . urlencode($token));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', "Authorization: Basic " . $auth));
        $response = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($response, true);

        return $json;

    }

    public function handleNotify($request)
    {
        $decode = $request->all();
        $response = self::query($decode['Token']);
        $message = json_encode($response);
        $response = json_decode($message);
        $status = $response->TransactionStatus;
        if ($status === 'Completed') {
            $order = Order::where("payment_code", $response->TransactionRefNo)->first();
            if ($order !== null) {
                $order->order_status_id = 2;
                $order->save();
            }
        }

        return compact("message", "status");
    }
}
/* example of query response

CountryName: "Australia",
FinancialInstitutionCountryCode: "iBankAU01",
TransactionID: "47e0b02c-c603-4108-96c8-93792166e6de",
MerchantEstablishedDateTime: "2019-03-21T19:50:40",
PayerAccountNumber: "98742364",
PayerAccountSortCode: "123456",
MerchantAccountSortCode: "062128",
MerchantAccountName: "AUREUS CORP PTY LT",
MerchantData: "",
CurrencyName: "Australian Dollar",
TransactionStatus: "Completed",
IsExpired: false,
MerchantEntityID: "b68661c4-1221-463c-b4e2-a44b19d5674d",
UserIPAddress: "58.84.147.115",
POLiVersionCode: "4 ",
MerchantName: "AUREUS CORP PTY LTD",
TransactionRefNo: "996156835707",
CurrencyCode: "AUD",
CountryCode: "AU",
PaymentAmount: 30.8,
AmountPaid: 30.8,
EstablishedDateTime: "2019-03-21T19:50:40.01",
StartDateTime: "2019-03-21T19:50:40.01",
EndDateTime: "2019-03-21T19:51:02.11",
BankReceipt: "35825250-384452",
BankReceiptDateTime: "21 March 2019 19:51:02",
TransactionStatusCode: "Completed",
ErrorCode: null,
ErrorMessage: "",
FinancialInstitutionCode: "iBankAU01",
FinancialInstitutionName: "iBank AU 01",
MerchantReference: "AUREUS CORP PTY LTD",
MerchantAccountSuffix: null,
MerchantAccountNumber: "11006814",
PayerFirstName: "Mr",
PayerFamilyName: "DemoShopper",
PayerAccountSuffix: ""
 */
