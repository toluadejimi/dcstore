<?php

namespace App\Http\Controllers\Gateway\SprintPay;

use App\Constants\Status;
use App\Models\Deposit;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gateway\PaymentController;
use App\Models\WalletHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Session;

class ProcessController extends Controller
{
    public static function process($deposit)
    {
//        $sprintPay = json_decode($deposit->gatewayCurrency()->gateway_parameter);
//
//        $isWalletCredit = isset($deposit->transaction_type);
//        // dd($isWalletCredit);
//
//        $send['API_publicKey'] = $sprintPay->key;
//        $send['customer_email'] = auth()->user()->email;
//        $send['amount'] = round($deposit->final_amo,2);
//        $send['customer_phone'] = auth()->user()->mobile;
//        $send['currency'] = $deposit->method_currency;
//        $send['txref'] = $deposit->trx;
//        $send['notify_url'] = $isWalletCredit? url('wallet/flutterwave') : url('ipn/flutterwave');
//
//        // Session::put('amount', $send['amount']);
//
//        $alias = $deposit->gateway->alias;
//        $send['view'] =  $isWalletCredit? 'user.payment.wallet.'.$alias : 'user.payment.'.$alias;

        $enkpayAcc = json_decode($deposit->gatewayCurrency()->gateway_parameter);
        $key = env('WEBKEY');
        $email = Auth::user()->email;
        $amount = round($deposit->final_amo, 2);
        $url = "https://web.sprintpay.online/pay?amount=$amount&key=$key&ref=$deposit->trx&email=$email";
        $send['url'] =  $url;

        $alias = $deposit->gateway->alias;

        $send['view'] = 'user.payment.'.$alias;


        return json_encode($send);

        // dd($send);

    }
}
