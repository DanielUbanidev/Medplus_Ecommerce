@extends('layouts.front-end.app')

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/front-end/css/bootstrap-select.min.css') }}">

    <style>
        .btn-outline {
            border-color: {{$web_config['primary_color']}} ;
        }

        .btn-outline {
            border-color: {{$web_config['primary_color']}}    !important;
        }

        .btn-outline:hover {
            background: {{$web_config['primary_color']}};

        }

        .btn-outline:focus {
            border-color: {{$web_config['primary_color']}}    !important;
        }

        /*#location_map_canvas {*/
        /*    height: 100%;*/
        /*}*/

        .filter-option {
            display: block;
            width: 100%;
            height: calc(1.5em + 1.25rem + 2px);
            padding: 0.625rem 1rem;
            font-size: .9375rem;
            font-weight: 400;
            line-height: 1.5;
            color: #4b566b;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #dae1e7;
            border-radius: 0.3125rem;
            box-shadow: 0 0 0 0 transparent;
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }

        .btn-light + .dropdown-menu{
            transform: none !important;
            top: 41px !important;
        }
    </style>
@endpush

@section('content')
@php($billing_input_by_customer=\App\CPU\Helpers::get_business_settings('billing_input_by_customer'))
    <div class="container py-4 rtl __inline-56 px-0 px-md-3" style="text-align: {{Session::get('direction') === "rtl" ? 'right' : 'left'}};">
        <div class="row mx-max-md-0">
            <div class="col-md-12 mb-3">
                <h3 class="font-weight-bold text-center text-lg-left">{{translate('checkout')}}</h3>
            </div>
            <section class="col-lg-8 px-max-md-0">
                <div class="checkout_details">
                <!-- Steps-->
                <div class="px-3 px-md-3">
                    @include('web-views.partials._checkout-steps',['step'=>2])
                </div>
                    <!-- Shipping methods table-->

                        @php($shipping_addresses=\App\Model\ShippingAddress::where(['customer_id'=>auth('customer')->id(), 'is_billing'=>0, 'is_guest'=>0])->get())
                        <form method="post" class="card __card" id="address-form">
                            <div class="card-body p-0">
                                @if(session('error'))
                                    <p class="text-center" style="color: #ff6264; font-weight: normal; font-size: 17px;">{{ session('error') }}</p>
                                @elseif(session('success'))
                                    <p class="text-center" style="color: #00a716; font-weight: normal; font-size: 17px;">{{ session('success') }}</p>
                                @endif
                                <ul class="list-group">
                                    <li class="list-group-item" onclick="anotherAddress()">
                                        <div id="accordion">
                                            <div class="">
                                                <div class="mt-3">
                                                    <div class="row">
                                                        <div class="col-sm-6">
                                                            <div class="form-group">
                                                                <label>{{ translate('name')}}
                                                                    <span
                                                                        class="text-danger">*</span></label>
                                                                <input type="text" class="form-control" name="name"  id="name">
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group">
                                                                <label>{{ translate('phone number')}}
                                                                    <span
                                                                        class="text-danger">*</span></label>
                                                                <input type="text" class="form-control" name="phone_no"  id="phone">
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group">
                                                                <label>{{ translate('Name of product')}}
                                                                    <span class="text-danger">*</span></label>
                                                                <input type="text" class="form-control" name="product" id="product">
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group">
                                                                <label>{{ translate('quantity')}}
                                                                    <span
                                                                        class="text-danger">*</span></label>
                                                                <input type="text" class="form-control" name="quantity"  id="quantity">
                                                            </div>
                                                        </div>
                                                        <button
                                                            class="btn btn--primary element-center btn-gap-{{Session::get('direction') === "rtl" ? 'left' : 'right'}}" style="width: 50%; margin: 0 auto;"
                                                            type="submit">
                                                            <span class="string-limit">{{translate('Submit Request')}}</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </form>
                </div>
            </section>
            @include('web-views.partials._order-summary')
        </div>
    </div>
@endsection





