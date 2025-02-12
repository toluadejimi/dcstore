<!doctype html>
<html lang="{{ config('app.locale') }}" itemscope itemtype="http://schema.org/WebPage">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title> {{ $general->siteName(__($pageTitle)) }}</title>

    @include('partials.seo')
    <link href="{{ asset('assets/global/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/global/css/all.min.css') }}" rel="stylesheet"> 

    <link rel="stylesheet" href="{{ asset('assets/global/css/line-awesome.min.css') }}" />
    <link rel="stylesheet" href="{{ asset($activeTemplateTrue . 'css/owl.theme.default.min.css') }}">
    <link rel="stylesheet" href="{{ asset($activeTemplateTrue . 'css/owl.carousel.min.css') }}">
    <link rel="stylesheet" href="{{ asset($activeTemplateTrue . 'css/odometer.css') }}">
 
    @stack('style-lib')

    <link rel="stylesheet" href="{{ asset($activeTemplateTrue . 'css/main.css') }}">
    <link rel="stylesheet" href="{{ asset($activeTemplateTrue . 'css/custom.css') }}">

    @stack('style')

    <link rel="stylesheet" href="{{ asset($activeTemplateTrue . 'css/color.php') }}?color={{ $general->base_color }}">
</head>

<body>
    @stack('fbComment')

    <div class="preloader">
        <div class="loader-p"></div>
    </div>

    <div class="body-overlay"></div>
    <div class="sidebar-overlay"></div>
    <a class="scroll-top"><i class="las la-long-arrow-alt-up"></i></a>

    @yield('app')
    
    <!--whatsapp button--> 
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css"> 
    <a href="https://chat.whatsapp.com/JCsDgfaBmLPBqh9CjClF82" class="float float1" target="_blank"> 
        <i class="fa fa-whatsapp"></i> Join Group
    </a> 
    <a href="http://Wa.me/+2347044703024" class="float float2" target="_blank"> 
        <i class="fa fa-whatsapp"></i> Message Support
    </a> 
    
    <style>
        .float {
          background-color: #FFFFFF;
          border: 0;
          border-radius: .5rem;
          box-sizing: border-box;
          color: #111827;
          font-size: .875rem;
          font-weight: 600;
          line-height: 1.25rem;
          padding: .75rem 1rem;
          text-align: center;
          text-decoration: none #D1D5DB solid;
          text-decoration-thickness: auto;
          box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
          cursor: pointer;
          user-select: none;
          -webkit-user-select: none;
          touch-action: manipulation;
        }
        
        .float i{
            color: #25d366;
        }
        
        .float1{ 
        position:fixed; 
            bottom: 70px;
            left: 30px; 
            z-index:1000; 
        } 
        .float2{ 
        position:fixed; 
            bottom: 20px;
            left: 30px; 
            z-index:1000; 
        } 
    </style> 
    <!--whatsapp button end-->
    
    @php
        @$cookie = App\Models\Frontend::where('data_keys', 'cookie.data')->first();
    @endphp

    @if (@$cookie->data_values->status == Status::ENABLE && !\Cookie::get('gdpr_cookie'))
        <div class="cookies-card text-center hide">
            <div class="cookies-card__icon bg--base">
                <i class="las la-cookie-bite"></i>
            </div>
            <p class="mt-4 cookies-card__content">
                {{ $cookie->data_values->short_desc }} <a href="{{ route('cookie.policy') }}" target="_blank">@lang('learn more')</a>
            </p>
            <div class="cookies-card__btn mt-4">
                <a href="javascript:void(0)" class="btn btn--base w-100 policy">@lang('Allow')</a>
            </div>
        </div>
    @endif

    <script src="{{ asset('assets/global/js/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('assets/global/js/bootstrap.bundle.min.js') }}"></script>

    <script src="{{ asset($activeTemplateTrue . 'js/owl.carousel.min.js') }}"></script>
    <script src="{{ asset($activeTemplateTrue . 'js/owl.carousel.filter.js') }}"></script>
    <script src="{{ asset($activeTemplateTrue . 'js/odometer.min.js') }}"></script>
    <script src="{{ asset($activeTemplateTrue . 'js/viewport.jquery.js') }}"></script>
    <script src="{{ asset($activeTemplateTrue . 'js/main.js') }}"></script>

    @if (request()->routeIs('product.details'))
        <script>
            let productDetailsIdArray = [];

            $('#purchaseModal').on('hidden.bs.modal', function () {
                location.reload();
            });

            $('.purchaseBtn').on('click', function () {
        
                if (productDetailsIdArray.length === 0) {
                    alert('Kindly select a product to purchase')
        
                    return;
                }
        
                var formData = { 
                    'product_details_id': productDetailsIdArray,
                    '_token': "{{ csrf_token() }}",
                }

                console.log(productDetailsIdArray);
        
                $.ajax({
                        method: 'POST',
                        url: "{{ route('user.deposit.purchase.modal') }}",
                        data: formData,
                        beforeSend: function (){
                            $('.purchaseBtn').attr('disabled', true)
                            $('.purchaseBtn').
                            html('<span class="spinner-border spinner-border-sm text-light" role="status"  aria-hidden="true"></span>Loading...')
                        },
                        success: function(response) {
        
                        // console.log(response);
        
                        $('.insert_modal_content').html(response);
        
                        var modal = $('#purchaseModal');
                        modal.modal('show');
        
                        },
                        error: function(xhr, status, error) {
                        let errorMessage = xhr.responseJSON.message;
                            console.log(errorMessage);
                        },
                        complete: function() {
                            $('.purchaseBtn-text').html('Purchase')
                            $('.purchaseBtn').attr('disabled', false)
                        }
                    });
            });
        
        
            $('.selectBtn').on('click', function() {
                let selectBtn = $(this);
                let isSelected = selectBtn.hasClass('selected');
        
                let price = parseFloat($('#price').val());
        
                // Find the corresponding '.product_details_id' input relative to the clicked button
                let productDetailsId = selectBtn.closest('.catalog-item__content').find('.product_details_id').val();
        
                if (isSelected) {
                    // Subtract from total price and count
                    let currentTotalPrice = parseFloat($('#totalPrice').text().replace(/[^\d.-]/g, ''));
                    let newTotalPrice = currentTotalPrice - price;
        
                    let currentTotalCount = parseInt($('#totalProductCount').text());
                    let newTotalCount = currentTotalCount - 1;
        
                    $('#totalPrice').text(newTotalPrice.toFixed(2) + ' NGN');
                    $('#totalProductCount').text(newTotalCount);
        
                    // Remove 'selected' class from button
                    selectBtn.removeClass('selected');
                    selectBtn.html('<i class="las la-shopping-cart"></i> <span>Select</span>');
        
                    // Find index of productDetailsId in the array
                    let index = productDetailsIdArray.indexOf(productDetailsId);
                    if (index !== -1) {
                        // Remove productDetailsId from the array
                        productDetailsIdArray.splice(index, 1);
                    }
        
                    // console.log(productDetailsIdArray);
        
                } else {
                    // Check if the product details id is not already in the array
                    if (!productDetailsIdArray.includes(productDetailsId)) {
                        productDetailsIdArray.push(productDetailsId);
                    }
        
                    // console.log(productDetailsIdArray);
        
                    // Add to total price and count
                    console.log($('#totalPrice').text().replace(/[^\d.-]/g, ''));
                    let currentTotalPrice = parseFloat($('#totalPrice').text().replace(/[^\d.-]/g, ''));
                    let newTotalPrice = currentTotalPrice + price;
        
                    let currentTotalCount = parseInt($('#totalProductCount').text());
                    let newTotalCount = currentTotalCount + 1;
        
                    $('#totalPrice').text(newTotalPrice.toFixed(2) + ' NGN');
                    $('#totalProductCount').text(newTotalCount);
        
                    // Add 'selected' class to button
                    selectBtn.addClass('selected');
                    selectBtn.html('<i class="las la-shopping-cart"></i> <span>Selected</span>');
                }
            });
            
        </script>
    @endif

    @stack('script-lib')
    
    @stack('script')

    @include('partials.plugins')
    
    @include('partials.notify')

    <script>
        (function($) {
            "use strict";
            $(".langSel").on("change", function() {
                window.location.href = "{{ route('home') }}/change/" + $(this).val();
            });

            $('.policy').on('click', function() {
                $.get('{{ route('cookie.accept') }}', function(response) {
                    $('.cookies-card').addClass('d-none');
                });
            });

            setTimeout(function() {
                $('.cookies-card').removeClass('hide')
            }, 2000);

            var inputElements = $('[type=text],select,textarea');
            $.each(inputElements, function(index, element) {
                element = $(element);

                if(element.hasClass('exclude')){
                    return false;
                }

                element.closest('.form-group').find('label').attr('for', element.attr('name'));
                element.attr('id', element.attr('name'))
            });

            $.each($('input, select, textarea'), function(i, element) {
                var elementType = $(element);
                if (elementType.attr('type') != 'checkbox') {
                    if (element.hasAttribute('required')) {
                        $(element).closest('.form-group').find('label').addClass('required');
                    }
                }
            });

        })(jQuery);
    </script>
</body>

</html>
