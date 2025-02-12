<?php

namespace App\Services\Gateway\instamojo;


use Facades\App\Services\BasicCurl;
use Facades\App\Services\BasicService;
use Log;
class Payment
{
    public static function prepareData($order, $gateway)
    {
        $basic = (object) config('basic');
        $api_key = trim($gateway->parameters->api_key ?? '');
        $auth_token = trim($gateway->parameters->auth_token ?? '');

        //$url = 'https://instamojo.com/api/1.1/payment-requests/';
        $url = 'https://www.instamojo.com/api/1.1/payment-requests/';
        /*
        $headers = [
            "X-Api-Key:$api_key",
            "X-Auth-Token:$auth_token"
        ];
        $postParam = [
            'purpose' => 'Payment to ' . $basic->site_title ?? 'Photoica',
            'amount' => round($order->final_amount,2),
            'buyer_name' => optional($order->user)->username ?? 'User Name',
            'redirect_url' => route('success'),
            'webhook' => route('ipn', [$gateway->code, $order->transaction]),
            'email' => optional($order->user)->email ?? 'example@example.com',
            'send_email' => true,
            'allow_repeated_payments' => false
        ];

        $response = BasicCurl::curlPostRequestWithHeaders($url, $headers, $postParam);
        $response = json_decode($response);
        
        */
        
            
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.instamojo.com/api/1.1/payment-requests/');
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                "X-Api-Key:$api_key",
                "X-Auth-Token:$auth_token"
            )
        );
        $payload = array(
            'purpose' => 'Payment to ' . $basic->site_title ?? 'Photoica',
            'amount' => round($order->final_amount,2),
            'buyer_name' => optional($order->user)->username ?? 'User Name',
            'redirect_url' => route('success'),
            'webhook' => route('ipn', [$gateway->code, $order->transaction]),
            'email' => optional($order->user)->email ?? 'example@example.com',
            'send_email' => true,
            'allow_repeated_payments' => false
        );

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        $response = curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response);
        

        
        if ($response && $response->success) {
            
        
            $order->btc_wallet = $response->payment_request->id;
            $order->save();
                
            $send['redirect'] = true;
            $send['redirect_url'] = $response->payment_request->longurl;
        } else {
            $send['error'] = true;
            $send['message'] = "Invalid Request";
        }
        return json_encode($send);
    }

    public static function ipn($request, $gateway, $order = null, $trx = null, $type = null)
    {
        $salt = trim($gateway->parameters->salt);
        $imData = $request;
        $macSent = $imData['mac'];
        unset($imData['mac']);
        ksort($imData, SORT_STRING | SORT_FLAG_CASE);
        $mac = hash_hmac("sha1", implode("|", $imData), $salt);

        if ($macSent == $mac && $imData['status'] == "Credit" && $order->status == '0') {
            BasicService::preparePaymentUpgradation($order);
        }
    }
}
