@extends($activeTemplate.'layouts.master')
@section('content') 
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card custom--card">
            <div class="card-header">
                <h5 class="card-title text-center">@lang('SprintPay')</h5>
            </div>
            <div class="card-body p-5">
                <ul class="list-group list-group-flush text-center">
                    <li class="list-group-item d-flex flex-wrap justify-content-between px-0">
                        @lang('You have to pay')
                        <strong>{{showAmount($deposit->final_amo)}} {{__($deposit->method_currency)}}</strong>
                    </li>
                </ul>
                <a href="{{ route('user.deposit.sprint', $deposit->final_amo) }}" class="btn btn--base w-100 mt-3" id="btn-confirm">@lang('Pay Now')</a>
            </div>
        </div>
    </div>
</div>
@endsection
@push('script')

@endpush
