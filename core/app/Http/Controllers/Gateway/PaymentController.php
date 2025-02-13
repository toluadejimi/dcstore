<?php

namespace App\Http\Controllers\Gateway;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\FormProcessor;
use App\Models\AdminNotification;
use App\Models\Deposit;
use App\Models\GatewayCurrency;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\User;
use App\Models\WalletHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class PaymentController extends Controller
{
    public function renderPurchaseModal(Request $request){
        $user = auth()->user();
        $userWallet = null;

        if ($user) {
            // Access the user's wallet if logged in
            $userWallet = $user->wallet;
        }

        $gatewayCurrency = GatewayCurrency::whereHas('method', function ($gate) {
            $gate->where('status', Status::DISABLE);
        })->with('method')->orderby('method_code')->get();
        $productDetailsArray = $request->get('product_details_id');
        $first_product_details = ProductDetail::findOrFail($productDetailsArray[0]);
        $product = Product::findOrFail($first_product_details->product_id);
        $product_price = $product->price;
        $total_price = $product_price * count($productDetailsArray);

        $result = '<div class="small">
        <p class="text mb-3"></p>
        <ul class="list-group list-group-flush preview-details">
        <input type="hidden" name="product_details_ids" value="' . implode(",",$productDetailsArray) . '">
        <input type="hidden" name="amount" value="' . $total_price . '">
        <input type="hidden" name="id" value="' . $product->id . '">';

        foreach($productDetailsArray as $product_detail){
            $result .= '<li class="list-group-item d-flex justify-content-between">
                <span>Account</span>
                <span>
                    <span class="price fw-bold">' . number_format($product_price, 2) . '</span>
                    NGN
                </span>
            </li>';
        }

        $result .= '<li class="list-group-item d-flex justify-content-between">
            <span>Charge</span>
            <span>
                <span class="charge fw-bold">0</span>
                NGN
            </span>
        </li>
        <li class="list-group-item d-flex justify-content-between">
            <span>Total amount (Payable)</span>
            <span>
                <span class="payable fw-bold">' . number_format($total_price, 2) . '</span>
                NGN
            </span>
        </li>
        </ul></div>';

        $result .= '<div class="row mt-4">
        <div class="form-group col">
            <label class="form--label">Select Gateway</label>
            <select class="form--control form-select" name="gateway" required>
            <option value="">Select One</option>
            <option value="wallet" data-gateway="{\'currency\': \'NGN\'}">Pay with wallet (Balance: NGN' . number_format(Auth::user()->balance, 2) . ')</option>';

        foreach($gatewayCurrency as $data){
            $result .= '<option value="' . $data->method_code . '" data-gateway="' . $data . '">'. $data->name . '</option>';
        }

        $result .= '</select></div></div>';

        return $result;
    }

    public function newDepositInsert(Request $request)
    {
        $request->validate([
            'gateway' => 'required',
            'currency' => 'required',
            'id' => 'required',
            'product_details_ids' => 'required',
            'amount' => 'required',
        ]);

        $product_details_ids = explode(",", $request->product_details_ids);
        $amount = $request->amount;
        $isWallet = null;
        $single_price = $amount / count($product_details_ids);

        if($request->gateway === "wallet"){
            $isWallet = true;
        }

        foreach($product_details_ids as $product_detail_id){
            $product_detail = ProductDetail::find($product_detail_id);

            if(!$product_detail){
                $notify[] = ['error', "One or more of the selected products has been sold."];
                return back()->withNotify($notify);
            }
        }

        $user = auth()->user();

        // dd($user->wallet->balance < $amount, $isWallet);

        if($isWallet){
            if($user->wallet->balance < $amount){
                $notify[] = ['error', "Insufficient balance"];
                return back()->withNotify($notify);
            }
        }

        if(!$isWallet){
            $gate = GatewayCurrency::whereHas('method', function ($gate) {
                $gate->where('status', Status::DISABLE);
            })->where('method_code', $request->gateway)->where('currency', $request->currency)->first();
            if (!$gate) {
                $notify[] = ['error', 'Invalid gateway'];
                return back()->withNotify($notify);
            }

            if ($gate->min_amount > $amount || $gate->max_amount < $amount) {
                $notify[] = ['error', 'Please follow deposit limit'];
                return back()->withNotify($notify);
            }

            $charge = $gate->fixed_charge + ($amount * $gate->percent_charge / 100);
            $payable = $amount + $charge;
            $final_amo = $payable * $gate->rate;
        }else{
            // Is wallet
            $final_amo = $amount;
        }

        $order = new Order();
        $order->user_id = $user->id;
        if($isWallet) $order->status = '1';
        $order->total_amount = $amount;
        $order->save();

        if(!$isWallet){
            $data = new Deposit();
            $data->user_id = $user->id;
            $data->order_id = $order->id;
            $data->method_code = $gate->method_code;
            $data->method_currency = strtoupper($gate->currency);
            $data->amount = $amount;
            $data->charge = $charge;
            $data->rate = $gate->rate;
            $data->final_amo = $final_amo;
            $data->btc_amo = 0;
            $data->btc_wallet = "";
            $data->trx = getTrx();
            $data->save();
        }else{
            $data = new WalletHistory();
            $data->wallet_id = $user->wallet->id;
            $data->order_id = $order->id;
            $data->transaction_type = '2';
            $data->final_amo = $final_amo;
            $data->amount = $amount;
            $data->status = '1';
            $data->method_code = '';
            $data->method_currency = 'NGN';
            $data->save();
        }


        foreach($product_details_ids as $product_detail_id){
            $item = new OrderItem();
            $item->order_id = $order->id;
            $item->product_id = $request->id;
            $item->product_detail_id = $product_detail_id;
            $item->price = $single_price;
            $item->save();
        }

        if(!$isWallet){
            session()->put('Track', $data->trx);
            return to_route('user.deposit.confirm');
        }else{
            $order->status = Status::ORDER_PAID;
            $order->save();

            $items = @$order->orderItems->pluck('product_detail_id')->toArray() ?? [];
            ProductDetail::whereIn('id', $items)->update(['is_sold'=>Status::YES]);

            $wallet = $user->wallet;
            $wallet->balance -= $amount;
            $wallet->save();

            $notify[] = ['success', 'Your order has been placed successfully!'];
            return to_route('user.orders')->withNotify($notify);
        }

    }

    public function fundWallet(){
        $pageTitle = 'Fund your Wallet';
        $user = auth()->user();

        return view($this->activeTemplate . 'user.fund_wallet', compact('pageTitle', 'user'));
    }

    public function depositWalletInsert(Request $request)
    {
        $request->validate([
            'amount' => 'required',
            'gateway' => 'required',
        ]);

        $amount = $request->amount;

        $user = auth()->user();
        $gate = GatewayCurrency::whereHas('method', function ($gate) {
            $gate->where('status', Status::DISABLE);
        })->where('method_code', $request->gateway)->where('currency', 'NGN')->first();
        if (!$gate) {
            $notify[] = ['error', 'Invalid gateway'];
            return back()->withNotify($notify);
        }

        if ($gate->min_amount > $amount || $gate->max_amount < $amount) {
            $notify[] = ['error', 'Please follow deposit limit (min amount: NGN ' . ceil($gate->min_amount) . ')'];
            return back()->withNotify($notify);
        }

        $trx_id = getTrx();

        $dep = new Deposit();
        $dep->user_id = Auth::id();
        $dep->order_id = "ORD".getTrx();
        $dep->method_code = "250";
        $dep->amount = $request->amount;
        $dep->method_currency = "NGN";
        $dep->charge = 0;
        $dep->rate = 0;
        $dep->final_amo = $request->amount;
        $dep->trx = $trx_id;
        $dep->status = 0;
        $dep->save();


        $key = env('WEBKEY');
        $email = Auth::user()->email;
        $amount = round($request->amount, 2);
        $url = "https://web.sprintpay.online/pay?amount=$amount&key=$key&ref=$trx_id&email=$email";

        $mssage = $email."| wants to fund | ".$amount. "| ref - $trx_id";
        send_notification($mssage);


        $pageTitle = 'Payment Confirm';
        return view($this->activeTemplate.'user.payment.SprintPay', compact('url', 'pageTitle', 'amount'));

    }

    public function depositWalletConfirm()
    {

        $track = session()->get('Track');

        dd($track);
        $deposit = Deposit::where('trx', $track)->where('status',Status::PAYMENT_INITIATE)->orderBy('id', 'DESC')->with('gateway')->firstOrFail();

        if ($deposit->method_code >= 1000) {
            return to_route('user.deposit.manual.confirm');
        }


        $dirName = $deposit->gateway->alias;
        $new = __NAMESPACE__ . '\\' . $dirName . '\\ProcessController';

        $data = $new::process($deposit);
        $data = json_decode($data);


        if (isset($data->error)) {
            $notify[] = ['error', $data->message];
            return to_route(gatewayWalletRedirectUrl())->withNotify($notify);
        }
        if (isset($data->redirect)) {
            return redirect($data->redirect_url);
        }

        // for Stripe V3
        if(@$data->session){
            $deposit->btc_wallet = $data->session->id;
            $deposit->save();
        }


    }

    public static function userWalletDataUpdate($deposit,$isManual = null)
    {
        if ($deposit->status == Status::PAYMENT_INITIATE || $deposit->status == Status::PAYMENT_PENDING) {
            $deposit->status = Status::PAYMENT_SUCCESS;
            $deposit->save();

            $user = $deposit->wallet->user;
            $wallet = $deposit->wallet;

            // Credit wallet here
            $wallet->balance += $deposit->amount;
            $wallet->save();

            if (!$isManual) {
                $adminNotification = new AdminNotification();
                $adminNotification->user_id = $user->id;
                $adminNotification->title = 'Wallet Payment successful via '.$deposit->gatewayCurrency()->name;
                $adminNotification->click_url = '#';
                $adminNotification->save();
            }

            notify($user, $isManual ? 'DEPOSIT_APPROVE' : 'DEPOSIT_COMPLETE', [
                'method_name' => $deposit->gatewayCurrency()->name,
                'method_currency' => $deposit->method_currency,
                'method_amount' => showAmount($deposit->final_amo),
                'amount' => showAmount($deposit->amount),
                // 'charge' => showAmount($deposit->charge),
                // 'rate' => showAmount($deposit->rate),
                'trx' => $deposit->trx,
                'details_link'=> route('user.wallet.history')
            ]);


        }
    }


    public function depositInsert(Request $request)
    {
        $request->validate([
            'gateway' => 'required',
            'currency' => 'required',
            'id' => 'required',
            'qty' => 'required|integer|gt:0',
        ]);

        $qty = $request->qty;
        $isWallet = null;

        if($request->gateway === "wallet"){
            $isWallet = true;
        }

        $product = Product::active()->whereHas('category', function($category){
            return $category->active();
        })->findOrFail($request->id);

        if($product->in_stock < $qty){
            $notify[] = ['error', "Not enough stock available. Only {$product->in_stock} quantity left"];
            return back()->withNotify($notify);
        }

        $amount = ($product->price * $qty);

        $user = auth()->user();

        if($isWallet){
            if(Auth::user()->balance < $amount){
                $notify[] = ['error', "Insufficient balance"];
                return back()->withNotify($notify);
            }
        }

        if(!$isWallet){
            $gate = GatewayCurrency::whereHas('method', function ($gate) {
                $gate->where('status', Status::DISABLE);
            })->where('method_code', $request->gateway)->where('currency', $request->currency)->first();
            if (!$gate) {
                $notify[] = ['error', 'Invalid gateway'];
                return back()->withNotify($notify);
            }

            if ($gate->min_amount > $amount || $gate->max_amount < $amount) {
                $notify[] = ['error', 'Please follow deposit limit'];
                return back()->withNotify($notify);
            }

            $charge = $gate->fixed_charge + ($amount * $gate->percent_charge / 100);
            $payable = $amount + $charge;
            $final_amo = $payable * $gate->rate;
        }else{
            // Is wallet
            $final_amo = $amount;
        }

        $order = new Order();
        $order->user_id = $user->id;
        if($isWallet) $order->status = '1';
        $order->total_amount = $amount;
        $order->save();

//        if(!$isWallet){
//            $data = new Deposit();
//            $data->user_id = $user->id;
//            $data->order_id = $order->id;
//            $data->method_code = $gate->method_code;
//            $data->method_currency = strtoupper($gate->currency);
//            $data->amount = $amount;
//            $data->charge = $charge;
//            $data->rate = $gate->rate;
//            $data->final_amo = $final_amo;
//            $data->btc_amo = 0;
//            $data->btc_wallet = "";
//            $data->trx = getTrx();
//            $data->save();
//        }else{
//            $data = new WalletHistory();
//            $data->wallet_id = $user->wallet->id;
//            $data->order_id = $order->id;
//            $data->transaction_type = '2';
//            $data->final_amo = $final_amo;
//            $data->amount = $amount;
//            $data->status = '1';
//            $data->method_code = '';
//            $data->method_currency = 'NGN';
//            $data->save();
//        }


        $unsoldProductDetails = $product->unsoldProductDetails;

        for($i = 0; $i < $qty; $i++){
            if(@!$unsoldProductDetails[$i]){
                continue;
            }
            $item = new OrderItem();
            $item->order_id = $order->id;
            $item->product_id = $product->id;
            $item->product_detail_id = $unsoldProductDetails[$i]->id;
            $item->price = $product->price;
            $item->save();
        }

        if(!$isWallet){
            session()->put('Track', $data->trx);
            return to_route('user.deposit.confirm');
        }else{
            $order->status = Status::ORDER_PAID;
            $order->save();

            $items = @$order->orderItems->pluck('product_detail_id')->toArray() ?? [];
            ProductDetail::whereIn('id', $items)->update(['is_sold'=>Status::YES]);

            User::where('id', Auth::id())->decrement('balance', $amount);

            $amt = number_format($amount, 2);

            $message = Auth::user()->email." Just bought item with | ID-".$order->id."| amount - $amt";
            send_notification($message);

            $notify[] = ['success', 'Your order has been placed successfully!'];
            return to_route('user.orders')->withNotify($notify);
        }

    }

    public function depositConfirm(request $request)
    {
        $track = session()->get('Track');
        $deposit = Deposit::where('trx', $track)->where('status',Status::PAYMENT_INITIATE)->orderBy('id', 'DESC')->with('gateway')->firstOrFail();

        if ($deposit->method_code >= 1000) {
            return to_route('user.deposit.manual.confirm');
        }


        $dirName = $deposit->gateway->alias;
        $new = __NAMESPACE__ . '\\' . $dirName . '\\ProcessController';

        $data = $new::process($deposit);
        $data = json_decode($data);


        if (isset($data->error)) {
            $notify[] = ['error', $data->message];
            return to_route(gatewayRedirectUrl())->withNotify($notify);
        }
        if (isset($data->redirect)) {
            return redirect($data->redirect_url);
        }

        // for Stripe V3
        if(@$data->session){
            $deposit->btc_wallet = $data->session->id;
            $deposit->save();
        }

        $pageTitle = 'Payment Confirm';
        return view($this->activeTemplate . $data->view, compact('data', 'pageTitle', 'deposit'));
    }


    public static function userDataUpdate($deposit,$isManual = null)
    {
        if ($deposit->status == Status::PAYMENT_INITIATE || $deposit->status == Status::PAYMENT_PENDING) {
            $deposit->status = Status::PAYMENT_SUCCESS;
            $deposit->save();

            $user = User::find($deposit->user_id);

            $order = $deposit->order;
            $order->status = Status::ORDER_PAID;
            $order->save();

            $items = @$order->orderItems->pluck('product_detail_id')->toArray() ?? [];
            ProductDetail::whereIn('id', $items)->update(['is_sold'=>Status::YES]);

            if (!$isManual) {
                $adminNotification = new AdminNotification();
                $adminNotification->user_id = $user->id;
                $adminNotification->title = 'Payment successful via '.$deposit->gatewayCurrency()->name;
                $adminNotification->click_url = urlPath('admin.deposit.successful');
                $adminNotification->save();
            }

            notify($user, $isManual ? 'DEPOSIT_APPROVE' : 'DEPOSIT_COMPLETE', [
                'method_name' => $deposit->gatewayCurrency()->name,
                'method_currency' => $deposit->method_currency,
                'method_amount' => showAmount($deposit->final_amo),
                'amount' => showAmount($deposit->amount),
                'charge' => showAmount($deposit->charge),
                'rate' => showAmount($deposit->rate),
                'trx' => $deposit->trx,
                'details_link'=> route('user.order.details', $order->id)
            ]);


        }
    }

    public function manualDepositConfirm()
    {
        $track = session()->get('Track');
        $data = Deposit::with('gateway')->where('status', Status::PAYMENT_INITIATE)->where('trx', $track)->first();
        if (!$data) {
            return to_route(gatewayRedirectUrl());
        }
        if ($data->method_code > 999) {

            $pageTitle = 'Payment Confirm';
            $method = $data->gatewayCurrency();
            $gateway = $method->method;
            return view($this->activeTemplate . 'user.payment.manual', compact('data', 'pageTitle', 'method','gateway'));
        }
        abort(404);
    }

    public function manualDepositUpdate(Request $request)
    {
        $track = session()->get('Track');
        $data = Deposit::with('gateway')->where('status', Status::PAYMENT_INITIATE)->where('trx', $track)->first();
        if (!$data) {
            return to_route(gatewayRedirectUrl());
        }
        $gatewayCurrency = $data->gatewayCurrency();
        $gateway = $gatewayCurrency->method;
        $formData = $gateway->form->form_data;

        $formProcessor = new FormProcessor();
        $validationRule = $formProcessor->valueValidation($formData);
        $request->validate($validationRule);
        $userData = $formProcessor->processFormData($request, $formData);


        $data->detail = $userData;
        $data->status = Status::PAYMENT_PENDING;
        $data->save();

        $order = $data->order;
        $items = @$order->orderItems->pluck('product_detail_id')->toArray() ?? [];
        ProductDetail::whereIn('id', $items)->update(['is_sold'=>Status::YES]);

        $adminNotification = new AdminNotification();
        $adminNotification->user_id = $data->user->id;
        $adminNotification->title = 'Payment request from '.$data->user->username;
        $adminNotification->click_url = urlPath('admin.deposit.details',$data->id);
        $adminNotification->save();

        notify($data->user, 'DEPOSIT_REQUEST', [
            'method_name' => $data->gatewayCurrency()->name,
            'method_currency' => $data->method_currency,
            'method_amount' => showAmount($data->final_amo),
            'amount' => showAmount($data->amount),
            'charge' => showAmount($data->charge),
            'rate' => showAmount($data->rate),
            'trx' => $data->trx
        ]);

        $notify[] = ['success', 'You have payment request has been taken'];
        return to_route('user.deposit.history')->withNotify($notify);
    }

    public function sprintInitialize($amount)
    {
        // Session::put('amount', $amount);

        // dd(session('amount'));
        // $amount = $amount;
        $reference = Str::uuid();
        $key = "008844747463745";
        $email = auth()->user()->email;
        $url = "https://web.sprintpay.online/pay?amount={$amount}&key={$key}&ref={$reference}&email={$email}";


        return redirect()->away($url);

        // print_r($response);
    }

    public function verifywoven(Request $request)
    {
        dd($request);

        // print_r($response);
    }
}
