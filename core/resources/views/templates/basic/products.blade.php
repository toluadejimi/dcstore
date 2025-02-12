@extends($activeTemplate . 'layouts.frontend')

@section('content')
<section class="catalog-section section-bg py-{{ @$categories->count() ? 120 : 60 }}">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xxl-10 col-xl-11">
                @forelse($categories as $category)
                    @php
                        $products = $category->products;
                    @endphp
                    <div class="catalog-item-wrapper">
                        <div class="catalog-item-wrapper__header d-flex align-items-center justify-content-between">
                            <h5 class="title mb-0">{{ __($category->name) }}</h5>
                            <a href="{{ route('category.products', ['search'=>request()->search, 'slug'=>slug($category->name), 'id'=>$category->id]) }}"
                                class="btn btn--base btn-outline--base btn--sm">
                                @lang('View All')
                            </a>
                        </div>
                        @foreach($products->take(5) as $product)
                            @include($activeTemplate.'partials/products')
                        @endforeach
                    </div>
                @empty
                <div class="empty-data text-center">
                    <div class="thumb">
                        <img src="{{ asset($activeTemplateTrue . 'images/not-found.png') }}">
                    </div>
                    <h4 class="title">@lang('No result found for "'.request()->search.'"')</h4>
                </div>
                @endforelse
                {{ paginateLinks($categories) }}
            </div>
        </div>
    </div>
</section>

@if ($sections->secs != null)
    @foreach (json_decode($sections->secs) as $sec)
        @include($activeTemplate . 'sections.' . $sec)
    @endforeach
@endif

<x-purchase-modal :wallet="$userWallet" />
@endsection



