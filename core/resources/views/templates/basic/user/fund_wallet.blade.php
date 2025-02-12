@extends($activeTemplate.'layouts.master')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card custom--card">
            <div class="card-header">
                <h5 class="card-title text-center">Fund Your Wallet</h5>
            </div>
            <div class="card-body">
                <form class="register" action="{{ route('user.wallet.insert') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="form-group col-sm-12">
                            <label class="form--label">Amount to credit</label>
                            <input type="text" class="form--control" name="amount" required>
                        </div>
                        <div class="form-group col-sm-12">
                            <label class="form--label">Select Method</label>
                            <select name="gateway" class="form--control form--select" required>
                                <option value>
                                    Select Payment Method
                                </option>
                                <option value="109">
                                    Flutterwave
                                </option>
                                <option value="201">
                                    SprintPay
                                </option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn--base w-100">@lang('Submit')</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
