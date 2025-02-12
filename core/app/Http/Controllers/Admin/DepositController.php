<?php

namespace App\Http\Controllers\Admin;

use App\Models\Deposit;
use App\Models\Gateway;
use App\Constants\Status;
use Illuminate\Http\Request;
use App\Models\ProductDetail;
use App\Models\WalletHistory;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Controllers\Gateway\PaymentController;

class DepositController extends Controller
{
    public function pending()
    {
        $pageTitle = 'Pending Payments';
        $deposits = $this->depositData('pending');
        return view('admin.deposit.log', compact('pageTitle', 'deposits'));
    }


    public function approved()
    {
        $pageTitle = 'Approved Payments';
        $deposits = $this->depositData('approved');
        return view('admin.deposit.log', compact('pageTitle', 'deposits'));
    }

    public function successful()
    {
        $pageTitle = 'Successful Payments';
        $deposits = $this->depositData('successful');
        return view('admin.deposit.log', compact('pageTitle', 'deposits'));
    }

    public function rejected()
    {
        $pageTitle = 'Rejected Payments';
        $deposits = $this->depositData('rejected');
        return view('admin.deposit.log', compact('pageTitle', 'deposits'));
    }

    public function initiated()
    {
        $pageTitle = 'Initiated Payments';
        $deposits = $this->depositData('initiated');
        return view('admin.deposit.log', compact('pageTitle', 'deposits'));
    }

    public function deposit()
    {
        $pageTitle = 'Payment History';
        $depositData = $this->depositData($scope = null, $summery = true);
        $deposits = $depositData['data'];
        $summery = $depositData['summery'];
        $successful = $summery['successful'];
        $pending = $summery['pending'];
        $rejected = $summery['rejected'];
        $initiated = $summery['initiated'];

        return view('admin.deposit.log', compact('pageTitle', 'deposits','successful','pending','rejected','initiated'));
    }

    // protected function depositData($scope = null,$summery = false)
    // {
    //     if ($scope) {
    //         $deposits = Deposit::$scope()->with(['user', 'gateway']);
    //     }else{
    //         $deposits = Deposit::with(['user', 'gateway']);
    //     }

    //     $deposits = $deposits->searchable(['trx','user:username'])->dateFilter();

    //     $request = request();
    //     //vai method
    //     if ($request->method) {
    //         $method = Gateway::where('alias',$request->method)->firstOrFail();
    //         $deposits = $deposits->where('method_code',$method->code);
    //     }

    //     if (!$summery) {
    //         return $deposits->orderBy('id','desc')->paginate(getPaginate());
    //     }else{
    //         $successful = clone $deposits;
    //         $pending = clone $deposits;
    //         $rejected = clone $deposits;
    //         $initiated = clone $deposits;

    //         $successfulSummery = $successful->where('status',Status::PAYMENT_SUCCESS)->sum('amount');
    //         $pendingSummery = $pending->where('status',Status::PAYMENT_PENDING)->sum('amount');
    //         $rejectedSummery = $rejected->where('status',Status::PAYMENT_REJECT)->sum('amount');
    //         $initiatedSummery = $initiated->where('status',Status::PAYMENT_INITIATE)->sum('amount');

    //         return [
    //             'data'=>$deposits->orderBy('id','desc')->paginate(getPaginate()),
    //             'summery'=>[
    //                 'successful'=>$successfulSummery,
    //                 'pending'=>$pendingSummery,
    //                 'rejected'=>$rejectedSummery,
    //                 'initiated'=>$initiatedSummery,
    //             ]
    //         ];
    //     }
    // }

    protected function depositData($scope = null, $summary = false)
    {
        $depositsQuery = $scope ? Deposit::$scope()->with(['user', 'gateway']) : Deposit::with(['user', 'gateway']);
        $depositsQuery = $depositsQuery->searchable(['trx', 'user:username'])->dateFilter();

        $request = request();

        // Filter by method if requested
        if ($request->method) {
            $method = Gateway::where('alias', $request->method)->firstOrFail();
            $depositsQuery = $depositsQuery->where('method_code', $method->code);
        }

        // Get all deposits
        $deposits = $depositsQuery->orderBy('created_at', 'desc')->get();

        // Query WalletHistory separately
        $walletHistoriesQuery = $scope ? WalletHistory::$scope()->with(['user', 'gateway']) : WalletHistory::with(['user', 'gateway']);
        $walletHistoriesQuery = $walletHistoriesQuery->searchable(['trx', 'wallet.user:username'])->dateFilter();

        // Get all wallet histories
        $walletHistories = $walletHistoriesQuery->where('transaction_type', 1)->orderBy('created_at', 'desc')->get();

        // Combine deposits and wallet histories
        $combined = $deposits->concat($walletHistories);

        // Sort combined collection by created_at date
        $sorted = $combined->sortByDesc('created_at');

        // Manual pagination
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $perPage = getPaginate(); // Replace with your pagination number
        $currentPageItems = $sorted->slice(($currentPage - 1) * $perPage, $perPage)->all();
        $paginatedItems = new LengthAwarePaginator($currentPageItems, $sorted->count(), $perPage, $currentPage, [
            'path' => LengthAwarePaginator::resolveCurrentPath()
        ]);

        // Prepare summary data if requested
        if ($summary) {
            $successfulDeposits = clone $deposits;
            $successfulWalletCredits = clone $walletHistories;
            $pending = clone $deposits;
            $rejected = clone $deposits;
            $initiatedDeposits = clone $deposits;
            $initiatedWalletCredits = clone $walletHistories;

            // Summarize deposits
            $successfulSum = $successfulDeposits->where('status', Status::PAYMENT_SUCCESS)->sum('amount') + $successfulWalletCredits->where('status', Status::PAYMENT_SUCCESS)->sum('amount');
            $pendingSum = $pending->where('status', Status::PAYMENT_PENDING)->sum('amount');
            $rejectedSum = $rejected->where('status', Status::PAYMENT_REJECT)->sum('amount');
            $initiatedSum = $initiatedDeposits->where('status', Status::PAYMENT_INITIATE)->sum('amount') + $initiatedWalletCredits->where('status', Status::PAYMENT_INITIATE)->sum('amount');

            return [
                'data' => $paginatedItems,
                'summery' => [
                    'successful' => $successfulSum,
                    'pending' => $pendingSum,
                    'rejected' => $rejectedSum,
                    'initiated' => $initiatedSum,
                ],
            ];
        }

        return $paginatedItems;
    }

    public function details($id)
{
    $request = request();
    $source = $request->get('source', 'deposit');

    if ($source === 'deposit') {
        $deposit = Deposit::where('id', $id)->with(['user', 'gateway'])->first();

        if (!$deposit) {
            abort(404, 'Deposit not found');
        }

        $pageTitle = $deposit->user->username . ($deposit->status === 0? ' initiated request to deposit ' : ' deposited ') . showAmount($deposit->amount) . ' ' . gs('cur_text');
        $details = ($deposit->detail != null) ? json_encode($deposit->detail) : null;

    } elseif ($source === 'wallet') {
        $walletHistory = WalletHistory::where('id', $id)->with(['wallet.user', 'gateway'])->first();

        if (!$walletHistory) {
            abort(404, 'Wallet History not found');
        }

        $deposit = $walletHistory;
        $pageTitle = $walletHistory->wallet->user->username . ' ' . ($walletHistory->status === 0? 'initiated request to credit' : 'credited') . ' wallet with ' . showAmount($walletHistory->amount) . ' ' . gs('cur_text');
        $details = ($walletHistory->detail != null) ? json_encode($walletHistory->detail) : null;

    } else {
        abort(404, 'Invalid source specified');
    }

    return view('admin.deposit.detail', compact('pageTitle', 'deposit', 'details', 'source'));
}



    public function approve($id) 
    {  
        $deposit = Deposit::where('id',$id)->where('status',Status::PAYMENT_PENDING)->firstOrFail();

        PaymentController::userDataUpdate($deposit,true);

        $notify[] = ['success', 'Payment request approved successfully'];

        return to_route('admin.deposit.pending')->withNotify($notify);
    }

    public function reject(Request $request)
    { 
        $request->validate([
            'id' => 'required|integer',
            'message' => 'required|string|max:255'
        ]);
        $deposit = Deposit::where('id',$request->id)->firstOrFail();

        $deposit->admin_feedback = $request->message;
        $deposit->status = Status::PAYMENT_REJECT;
        $deposit->save();
        
        $order = $deposit->order;
        $items = @$order->orderItems->pluck('product_detail_id')->toArray() ?? [];
        ProductDetail::whereIn('id', $items)->update(['is_sold'=>Status::NO]);

        notify($deposit->user, 'DEPOSIT_REJECT', [
            'method_name' => $deposit->gatewayCurrency()->name,
            'method_currency' => $deposit->method_currency,
            'method_amount' => showAmount($deposit->final_amo),
            'amount' => showAmount($deposit->amount),
            'charge' => showAmount($deposit->charge),
            'rate' => showAmount($deposit->rate),
            'trx' => $deposit->trx,
            'rejection_message' => $request->message
        ]);

        $notify[] = ['success', 'Payment request rejected successfully'];
        return  to_route('admin.deposit.pending')->withNotify($notify);

    }
}
