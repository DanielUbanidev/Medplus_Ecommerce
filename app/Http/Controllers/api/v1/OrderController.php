<?php

namespace App\Http\Controllers\api\v1;

use App\User;
use Carbon\Carbon;
use App\Model\Cart;
use App\CPU\Convert;
use App\CPU\Helpers;
use App\Model\Admin;
use App\Model\Order;
use App\Model\Seller;
use App\Model\Setting;
use App\CPU\SMS_module;
use App\CPU\CartManager;
use App\CPU\ImageManager;
use App\CPU\OrderManager;
use App\Model\DeliveryMan;
use App\Model\OrderDetail;
use App\Traits\SmsGateway;
use Carbon\CarbonInterval;
use App\Traits\CommonTrait;
use App\Model\BusinessSetting;
use App\Model\Currency;
use App\Traits\Payment;
use App\Library\Payer;
use App\Library\Payment as PaymentInfo;
use App\Library\Receiver;
use App\CPU\CustomerManager;
use App\Model\RefundRequest;
use Illuminate\Http\Request;
use App\Model\ShippingAddress;
use function App\CPU\translate;
use App\Model\OfflinePaymentMethod;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Model\DigitalProductOtpVerification;
use Illuminate\Support\Facades\File;

class OrderController extends Controller
{
    use CommonTrait;

    public function make_payment(Request $request)
    {
        $user = Helpers::get_customer($request);
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required',
            'payment_platform' => 'required',
        ]);

        $validator->sometimes('customer_id', 'required', function ($input) {
            return in_array($input->payment_request_from, ['app', 'react']);
        });
        $validator->sometimes('is_guest', 'required', function ($input) {
            return in_array($input->payment_request_from, ['app', 'react']);
        });

        if ($validator->fails()) { //api
            $errors = Helpers::error_processor($validator);
            if(in_array($request->payment_request_from, ['app', 'react'])){
                return response()->json(['errors' => Helpers::error_processor($validator)], 403);
            }else{
                foreach ($errors as $value) {
                    Toastr::error(translate($value['message']));
                }
                return back();
            }
        }

        $cart_group_ids = CartManager::get_cart_group_ids();
        $carts = Cart::whereIn('cart_group_id', $cart_group_ids)->get();
        $product_stock = CartManager::product_stock_check($carts);
        if(!$product_stock && in_array($request->payment_request_from, ['app', 'react'])){
            return response()->json(['errors' => ['code' => 'product-stock', 'message' => 'The following items in your cart are currently out of stock']], 403);
        }elseif(!$product_stock){
            Toastr::error(translate('the_following_items_in_your_cart_are_currently_out_of_stock'));
            return redirect()->route('shop-cart');
        }
        if(in_array($request->payment_request_from, ['app', 'react'])) {
            $shippingMethod = Helpers::get_business_settings('shipping_method');
            $physical_product = false;
            foreach ($carts as $cart) {
                if ($cart->product_type == 'physical') {
                    $physical_product = true;
                }

                if ($shippingMethod == 'inhouse_shipping') {
                    $admin_shipping = ShippingType::where('seller_id', 0)->first();
                    $shipping_type = isset($admin_shipping) == true ? $admin_shipping->shipping_type : 'order_wise';
                } else {
                    if ($cart->seller_is == 'admin') {
                        $admin_shipping = ShippingType::where('seller_id', 0)->first();
                        $shipping_type = isset($admin_shipping) == true ? $admin_shipping->shipping_type : 'order_wise';
                    } else {
                        $seller_shipping = ShippingType::where('seller_id', $cart->seller_id)->first();
                        $shipping_type = isset($seller_shipping) == true ? $seller_shipping->shipping_type : 'order_wise';
                    }
                }

                if ($shipping_type == 'order_wise') {
                    $cart_shipping = CartShipping::where('cart_group_id', $cart->cart_group_id)->first();
                    if (!isset($cart_shipping) && $physical_product) {
                        return response()->json(['errors' => ['code' => 'shipping-method', 'message' => 'Data not found']], 403);
                    }
                }
            }
        }

        $redirect_link = $this->customer_payment_request($request);

        if(in_array($request->payment_request_from, ['app', 'react'])) {
            return response()->json(['redirect_link'=>$redirect_link], 200);
        }else{
            return redirect($redirect_link);
        }
    }
    
    public function customer_payment_request(Request $request)
    {
        $additional_data = [
            'business_name' => BusinessSetting::where(['type' => 'company_name'])->first()->value,
            'business_logo' => asset('storage/app/public/company') . '/' . Helpers::get_business_settings('company_web_logo'),
            'payment_mode' => $request->has('payment_platform') ? $request->payment_platform : 'web',
        ];

        $user = Helpers::get_customer($request);
        if(in_array($request->payment_request_from, ['app', 'react'])){
            $additional_data['customer_id'] = $request->customer_id;
            $additional_data['is_guest'] = $request->is_guest;
            $additional_data['order_note'] = $request['order_note'];
            $additional_data['address_id'] = $request['address_id'];
            $additional_data['billing_address_id'] = $request['billing_address_id'];
            $additional_data['coupon_code'] = $request['coupon_code'];
            $additional_data['coupon_discount'] = $request['coupon_discount'];
            $additional_data['payment_request_from'] = $request->payment_request_from;
        }

        $currency_model = Helpers::get_business_settings('currency_model');
        if ($currency_model == 'multi_currency') {
            $currency_code = 'USD';
        } else {
            $default = BusinessSetting::where(['type' => 'system_default_currency'])->first()->value;
            $currency_code = Currency::find($default)->code;
        }

        if(in_array($request->payment_request_from, ['app', 'react'])) {
            $cart_group_ids = CartManager::get_cart_group_ids($request);
            $cart_amount = 0;
            $shipping_cost_saved = 0;
            foreach ($cart_group_ids as $group_id) {
                $cart_amount += CartManager::api_cart_grand_total($request, $group_id);
                $shipping_cost_saved += CartManager::get_shipping_cost_saved_for_free_delivery($group_id);
            }
            $payment_amount = $cart_amount - $request['coupon_discount'] - $shipping_cost_saved;
        }else{
            $discount = session()->has('coupon_discount') ? session('coupon_discount') : 0;
            $order_wise_shipping_discount = CartManager::order_wise_shipping_discount();
            $shipping_cost_saved = CartManager::get_shipping_cost_saved_for_free_delivery();
            $payment_amount = CartManager::cart_grand_total() - $discount - $order_wise_shipping_discount - $shipping_cost_saved;
        }

        $customer = Helpers::get_customer($request);

        if($customer == 'offline'){
            $address = ShippingAddress::where(['customer_id'=>$request->customer_id, 'is_guest'=>1])->latest()->first();
            if($address){
                $payer = new Payer(
                    $address->contact_person_name,
                    $address->email,
                    $address->phone,
                    ''
                );
            }else {
                $payer = new Payer(
                    'Contact person name',
                    '',
                    '',
                    ''
                );
            }
        }else{
            $payer = new Payer(
                $customer->f_name . ' ' . $customer->l_name ,
                $customer->email,
                $customer->phone,
                ''
            );
        }

        $payment_info = new PaymentInfo(
            success_hook: 'digital_payment_success',
            failure_hook: 'digital_payment_fail',
            currency_code: $currency_code,
            payment_method: $request->payment_method,
            payment_platform: $request->payment_platform,
            payer_id: $customer=='offline' ? $request->customer_id : $customer->id,
            receiver_id: '100',
            additional_data: $additional_data,
            payment_amount: $payment_amount,
            external_redirect_link: $request->payment_platform == 'web' ? $request->external_redirect_link : null,
            attribute: 'order',
            attribute_id: '10001'
        );

        $receiver_info = new Receiver('receiver_name','example.png');

        $redirect_link = Payment::generate_link($payer, $payment_info, $receiver_info);

        return $redirect_link;
    }
    
    public function track_by_order_id(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        return response()->json(OrderManager::track_order($request['order_id']), 200);
    }
    public function order_cancel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $order = Order::where(['id' => $request->order_id])->first();

        if ($order['payment_method'] == 'cash_on_delivery' && $order['order_status'] == 'pending') {
            OrderManager::stock_update_on_order_status_change($order, 'canceled');
            Order::where(['id' => $request->order_id])->update([
                'order_status' => 'canceled'
            ]);

            return response()->json(translate('order_canceled_successfully'), 200);
        }

        return response()->json(translate('status_not_changable_now'), 302);
    }
    public function place_order(Request $request)
    {
        $user = Helpers::get_customer($request);

        $cart_group_ids = CartManager::get_cart_group_ids($request);
        $carts = Cart::whereIn('cart_group_id', $cart_group_ids)->get();

        $product_stock = CartManager::product_stock_check($carts);
        if(!$product_stock){
            return response()->json(['message' => 'The following items in your cart are currently out of stock'], 403);
        }

        $physical_product = false;
        foreach($carts as $cart){
            if($cart->product_type == 'physical'){
                $physical_product = true;
            }
        }

        if($physical_product) {
            $zip_restrict_status = Helpers::get_business_settings('delivery_zip_code_area_restriction');
            $country_restrict_status = Helpers::get_business_settings('delivery_country_restriction');

            if ($request->has('billing_address_id') && $request->billing_address_id) {
                if ($user == 'offline') {
                    $shipping_address = ShippingAddress::where(['customer_id' => $request->guest_id,'is_guest'=>1, 'id' => $request->input('billing_address_id')])->first();
                }else{
                    $shipping_address = ShippingAddress::where(['customer_id' => $user->id,'is_guest'=>'0', 'id' => $request->input('billing_address_id')])->first();
                }

                if (!$shipping_address) {
                    return response()->json(['message' => translate('address_not_found')], 403);
                }
                elseif ($country_restrict_status && !self::delivery_country_exist_check($shipping_address->country)) {
                    return response()->json(['message' => translate('Delivery_unavailable_for_this_country')], 403);

                } elseif ($zip_restrict_status && !self::delivery_zipcode_exist_check($shipping_address->zip)) {
                    return response()->json(['message' => translate('Delivery_unavailable_for_this_zip_code_area')], 403);
                }
            }
        }

        $unique_id = OrderManager::gen_unique_id();

        $order_ids = [];
        foreach ($cart_group_ids as $group_id) {
            $data = [
                'payment_method' => 'cash_on_delivery',
                'order_status' => 'pending',
                'payment_status' => 'unpaid',
                'transaction_ref' => '',
                'order_group_id' => $unique_id,
                'cart_group_id' => $group_id,
                'request' => $request,
            ];
            $order_id = OrderManager::generate_order($data);

            $order = Order::find($order_id);
            $order->billing_address = ($request['billing_address_id'] != null) ? $request['billing_address_id'] : $order['billing_address'];
            $order->billing_address_data = ($request['billing_address_id'] != null) ?  ShippingAddress::find($request['billing_address_id']) : $order['billing_address_data'];
            $order->order_note = ($request['order_note'] != null) ? $request['order_note'] : $order['order_note'];
            $order->save();

            array_push($order_ids, $order_id);
        }

        CartManager::cart_clean($request);

        return response()->json(['order_ids'=>$order_ids], 200);
    }

    public function place_order_by_offline_payment(Request $request)
    {
        $cart_group_ids = CartManager::get_cart_group_ids($request);
        $carts = Cart::whereIn('cart_group_id', $cart_group_ids)->get();

        $product_stock = CartManager::product_stock_check($carts);
        if(!$product_stock){
            return response()->json(['message' => 'The following items in your cart are currently out of stock'], 403);
        }

        $physical_product = false;
        foreach($carts as $cart){
            if($cart->product_type == 'physical'){
                $physical_product = true;
            }
        }

        $user = Helpers::get_customer($request);

        if($physical_product) {
            $zip_restrict_status = Helpers::get_business_settings('delivery_zip_code_area_restriction');
            $country_restrict_status = Helpers::get_business_settings('delivery_country_restriction');

            if ($request->has('billing_address_id') && $request->billing_address_id) {
                if ($user == 'offline') {
                    $shipping_address = ShippingAddress::where(['customer_id' => $request->guest_id,'is_guest'=>1, 'id' => $request->input('billing_address_id')])->first();
                }else{
                    $shipping_address = ShippingAddress::where(['customer_id' => $user->id,'is_guest'=>'0', 'id' => $request->input('billing_address_id')])->first();
                }

                if (!$shipping_address) {
                    return response()->json(['message' => translate('address_not_found')], 200);
                }
                elseif ($country_restrict_status && !self::delivery_country_exist_check($shipping_address->country)) {
                    return response()->json(['message' => translate('Delivery_unavailable_for_this_country')], 403);

                } elseif ($zip_restrict_status && !self::delivery_zipcode_exist_check($shipping_address->zip)) {
                    return response()->json(['message' => translate('Delivery_unavailable_for_this_zip_code_area')], 403);
                }
            }
        }

        $offline_payment_info = [];
        $method = OfflinePaymentMethod::where(['id'=>$request->method_id,'status'=>1])->first();

        if(isset($method))
        {
            $fields = array_column($method->method_informations, 'customer_input');
            $values = (array) json_decode(base64_decode($request->method_informations));

            $offline_payment_info['method_id'] = $request->method_id;
            $offline_payment_info['method_name'] = $method->method_name;
            foreach ($fields as $field) {
                if(key_exists($field, $values)) {
                    $offline_payment_info[$field] = $values[$field];
                }
            }
        }

        $unique_id = OrderManager::gen_unique_id();
        $order_ids = [];
        foreach ($cart_group_ids as $group_id) {
            $data = [
                'payment_method' => 'offline_payment',
                'order_status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_note' => $request->payment_note,
                'order_group_id' => $unique_id,
                'cart_group_id' => $group_id,
                'request' => $request,
                'offline_payment_info' => $offline_payment_info,
            ];
            $order_id = OrderManager::generate_order($data);

            $order = Order::find($order_id);
            $order->billing_address = ($request['billing_address_id'] != null) ? $request['billing_address_id'] : $order['billing_address'];
            $order->billing_address_data = ($request['billing_address_id'] != null) ?  ShippingAddress::find($request['billing_address_id']) : $order['billing_address_data'];
            $order->order_note = ($request['order_note'] != null) ? $request['order_note'] : $order['order_note'];
            $order->save();

            array_push($order_ids, $order_id);
        }

        CartManager::cart_clean($request);

        return response()->json(translate('order_placed_successfully'), 200);
    }

    public function place_order_by_wallet(Request $request)
    {
        $cart_group_ids = CartManager::get_cart_group_ids($request);
        $carts = Cart::whereIn('cart_group_id', $cart_group_ids)->get();

        $product_stock = CartManager::product_stock_check($carts);
        if(!$product_stock){
            return response()->json(['message' => 'The following items in your cart are currently out of stock'], 403);
        }

        $cartTotal = 0;
        foreach($cart_group_ids as $cart_group_id){
            $cartTotal += CartManager::cart_grand_total($cart_group_id);
        }
        $user = Helpers::get_customer($request);
        if( $cartTotal > $user->wallet_balance)
        {
            return response()->json('inefficient balance in your wallet to pay for this order', 403);
        }else{
            $physical_product = false;
            foreach($carts as $cart){
                if($cart->product_type == 'physical'){
                    $physical_product = true;
                }
            }

            if($physical_product) {
                $zip_restrict_status = Helpers::get_business_settings('delivery_zip_code_area_restriction');
                $country_restrict_status = Helpers::get_business_settings('delivery_country_restriction');

                if ($request->has('billing_address_id')) {
                    $shipping_address = ShippingAddress::where(['customer_id' => $request->user()->id, 'id' => $request->input('billing_address_id')])->first();

                    if (!$shipping_address) {
                        return response()->json(['message' => translate('address_not_found')], 200);
                    }
                    elseif ($country_restrict_status && !self::delivery_country_exist_check($shipping_address->country)) {
                        return response()->json(['message' => translate('Delivery_unavailable_for_this_country')], 403);

                    } elseif ($zip_restrict_status && !self::delivery_zipcode_exist_check($shipping_address->zip)) {
                        return response()->json(['message' => translate('Delivery_unavailable_for_this_zip_code_area')], 403);
                    }
                }
            }


            $unique_id = $request->user()->id . '-' . rand(000001, 999999) . '-' . time();
            $order_ids = [];
            foreach ($cart_group_ids as $group_id) {
                $data = [
                    'payment_method' => 'pay_by_wallet',
                    'order_status' => 'confirmed',
                    'payment_status' => 'paid',
                    'transaction_ref' => '',
                    'order_group_id' => $unique_id,
                    'cart_group_id' => $group_id,
                    'request' => $request,
                ];
                $order_id = OrderManager::generate_order($data);

                $order = Order::find($order_id);
                $order->billing_address = ($request['billing_address_id'] != null) ? $request['billing_address_id'] : $order['billing_address'];
                $order->billing_address_data = ($request['billing_address_id'] != null) ?  ShippingAddress::find($request['billing_address_id']) : $order['billing_address_data'];
                $order->order_note = ($request['order_note'] != null) ? $request['order_note'] : $order['order_note'];
                $order->save();

                array_push($order_ids, $order_id);
            }

            CustomerManager::create_wallet_transaction($user->id, Convert::default($cartTotal), 'order_place','order payment');

            CartManager::cart_clean($request);

            return response()->json(translate('order_placed_successfully'), 200);
        }
    }

    public function refund_request(Request $request)
    {
        $order_details = OrderDetail::find($request->order_details_id);

        $user = $request->user();


        $loyalty_point_status = Helpers::get_business_settings('loyalty_point_status');
        if($loyalty_point_status == 1)
        {
            $loyalty_point = CustomerManager::count_loyalty_point_for_amount($request->order_details_id);

            if($user->loyalty_point < $loyalty_point)
            {
                return response()->json(['message'=>translate('you have not sufficient loyalty point to refund this order!!')], 202);
            }
        }

        if($order_details->delivery_status == 'delivered')
        {
            $order = Order::find($order_details->order_id);
            $total_product_price = 0;
            $refund_amount = 0;
            $data = [];
            foreach ($order->details as $key => $or_d) {
                $total_product_price += ($or_d->qty*$or_d->price) + $or_d->tax - $or_d->discount;
            }

            $subtotal = ($order_details->price * $order_details->qty) - $order_details->discount + $order_details->tax;

            $coupon_discount = ($order->discount_amount*$subtotal)/$total_product_price;

            $refund_amount = $subtotal - $coupon_discount;

            $data['product_price'] = $order_details->price;
            $data['quntity'] = $order_details->qty;
            $data['product_total_discount'] = $order_details->discount;
            $data['product_total_tax'] = $order_details->tax;
            $data['subtotal'] = $subtotal;
            $data['coupon_discount'] = $coupon_discount;
            $data['refund_amount'] = $refund_amount;

            $refund_day_limit = Helpers::get_business_settings('refund_day_limit');
            $order_details_date = $order_details->created_at;
            $current = \Carbon\Carbon::now();
            $length = $order_details_date->diffInDays($current);
            $expired = false;
            $already_requested = false;
            if($order_details->refund_request != 0)
            {
                $already_requested = true;
            }
            if($length > $refund_day_limit )
            {
                $expired = true;
            }
            return response()->json(['already_requested'=>$already_requested,'expired'=>$expired,'refund'=>$data], 200);
        }else{
            return response()->json(['message'=>translate('You_can_request_for_refund_after_order_delivered')], 200);
        }

    }
    public function store_refund(Request $request)
    {

        $order_details = OrderDetail::find($request->order_details_id);

        $user = $request->user();


        $loyalty_point_status = Helpers::get_business_settings('loyalty_point_status');
        if($loyalty_point_status == 1)
        {
            $loyalty_point = CustomerManager::count_loyalty_point_for_amount($request->order_details_id);

            if($user->loyalty_point < $loyalty_point)
            {
                return response()->json(translate('you have not sufficient loyalty point to refund this order!!'), 200);
            }
        }

        if($order_details->refund_request == 0){

            $validator = Validator::make($request->all(), [
                'order_details_id' => 'required',
                'amount' => 'required',
                'refund_reason' => 'required'

            ]);
            if ($validator->fails()) {
                return response()->json(['errors' => Helpers::error_processor($validator)], 403);
            }
            $refund_request = new RefundRequest;
            $refund_request->order_details_id = $request->order_details_id;
            $refund_request->customer_id = $request->user()->id;
            $refund_request->status = 'pending';
            $refund_request->amount = $request->amount;
            $refund_request->product_id = $order_details->product_id;
            $refund_request->order_id = $order_details->order_id;
            $refund_request->refund_reason = $request->refund_reason;

            if ($request->file('images')) {
                foreach ($request->file('images') as $img) {
                    $product_images[] = ImageManager::upload('refund/', 'webp', $img);
                }
                $refund_request->images = json_encode($product_images);
            }
            $refund_request->save();

            $order_details->refund_request = 1;
            $order_details->save();

            return response()->json(translate('refunded_request_updated_successfully!!'), 200);
        }else{
            return response()->json(translate('already_applied_for_refund_request!!'), 302);
        }

    }
    public function refund_details(Request $request)
    {
        $order_details = OrderDetail::find($request->id);
        $refund = RefundRequest::where('customer_id',$request->user()->id)
                                ->where('order_details_id',$order_details->id )->get();
        $refund = $refund->map(function($query){
            $query['images'] = json_decode($query['images']);
            return $query;
        });

        $order = Order::find($order_details->order_id);

        $total_product_price = 0;
        $refund_amount = 0;
        $data = [];
        foreach ($order->details as $key => $or_d) {
            $total_product_price += ($or_d->qty*$or_d->price) + $or_d->tax - $or_d->discount;
        }

        $subtotal = ($order_details->price * $order_details->qty) - $order_details->discount + $order_details->tax;

        $coupon_discount = ($order->discount_amount*$subtotal)/$total_product_price;

        $refund_amount = $subtotal - $coupon_discount;

        $data['product_price'] = $order_details->price;
        $data['quntity'] = $order_details->qty;
        $data['product_total_discount'] = $order_details->discount;
        $data['product_total_tax'] = $order_details->tax;
        $data['subtotal'] = $subtotal;
        $data['coupon_discount'] = $coupon_discount;
        $data['refund_amount'] = $refund_amount;
        $data['refund_request']=$refund;
        $data['order_place_date']=$order->created_at;

        return response()->json($data, 200);
    }

    public function digital_product_download($id, Request $request)
    {
        $user = Helpers::get_customer($request);
        $order_details_data = OrderDetail::with('order.customer')->find($id);

        if($order_details_data) {
            if($order_details_data->order->payment_status !== "paid") {
                return response()->json([
                    'status' => 0,
                    'message' => translate('Payment_must_be_confirmed_first').' !!',
                ]);
            };

            if($order_details_data->order->is_guest) {
                $customer_email = $order_details_data->order->shipping_address_data ? json_decode($order_details_data->order->shipping_address_data)->email : ($order_details_data->order->billing_address_data ? json_decode($order_details_data->order->billing_address_data)->email : '');

                $customer_phone = $order_details_data->order->shipping_address_data ? json_decode($order_details_data->order->shipping_address_data)->phone : ($order_details_data->order->billing_address_data ? json_decode($order_details_data->order->billing_address_data)->phone : '');

                $customer_data = ['email' =>$customer_email, 'phone' =>$customer_phone];
                return self::digital_product_download_process($order_details_data, $customer_data);
            }else {
                if($user != 'offline' && $user->id == $order_details_data->order->customer->id) {
                    $file_name = '';
                    if( $order_details_data->product->digital_product_type == 'ready_product' && $order_details_data->product->digital_file_ready) {
                        $file_path = asset('storage/app/public/product/digital-product/' .$order_details_data->product->digital_file_ready);
                        $file_name = $order_details_data->product->digital_file_ready;
                    }else{
                        $file_path = asset('storage/app/public/product/digital-product/' . $order_details_data->digital_file_after_sell);
                        $file_name = $order_details_data->digital_file_after_sell;
                    }

                    if(File::exists(base_path('storage/app/public/product/digital-product/'. $file_name))) {
                        return \response()->download($file_path);
                    }else {
                        return response()->json([
                            'status' => 0,
                            'message' => translate('file_not_found'),
                        ]);
                    }
                }else {
                    $customer_data = ['email' =>$order_details_data->order->customer->email ?? '', 'phone' =>$order_details_data->order->customer->phone ?? ''];
                    return self::digital_product_download_process($order_details_data, $customer_data);
                }
            }
        }else{
            return response()->json(['message'=>translate('order_Not_Found')], 403);
        }
    }

    public function digital_product_download_process($order_details_data, $customer)
    {
        $status = 2;
        $emailServices_smtp = Helpers::get_business_settings('mail_config');
        if ($emailServices_smtp['status'] == 0) {
            $emailServices_smtp = Helpers::get_business_settings('mail_config_sendgrid');
        }

        $payment_published_status = config('get_payment_publish_status');
        $published_status = isset($payment_published_status[0]['is_published']) ? $payment_published_status[0]['is_published'] : 0;

        if($published_status == 1){
            $sms_config_status = Setting::where(['settings_type'=>'sms_config', 'is_active'=>1])->count() > 0 ? 1:0;
        }else{
            $sms_config_status = Setting::where(['settings_type'=>'sms_config', 'is_active'=>1])->whereIn('key_name', Helpers::default_sms_gateways())->count() > 0 ? 1:0;
        }

        if($emailServices_smtp['status'] || $sms_config_status) {
            $token = rand(1000, 9999);
            if($customer['email'] == '' && $customer['phone'] == ''){
                return response()->json([
                    'status' => $status,
                    'file_path' => '',
                    'view'=> view(VIEW_FILE_NAMES['digital_product_order_otp_verify_failed'])->render(),
                ]);
            }

            $verification_data = DigitalProductOtpVerification::where('identity', $customer['email'])->orWhere('identity', $customer['phone'])->where('order_details_id', $order_details_data->id)->latest()->first();
            $otp_interval_time = Helpers::get_business_settings('otp_resend_time') ?? 1; //second

            if(isset($verification_data) &&  Carbon::parse($verification_data->created_at)->diffInSeconds() < $otp_interval_time){
                $time_count_in_second = $otp_interval_time - Carbon::parse($verification_data->created_at)->diffInSeconds();
                return response()->json([
                    'status' => 0,
                    'email_config_status' => $emailServices_smtp['status'],
                    'sms_config_status' => $sms_config_status,
                    'time_count_in_second'=> $time_count_in_second,
                ]);
            }else {
                $verify_data = [
                    'order_details_id' => $order_details_data->id,
                    'token' => $token,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                DigitalProductOtpVerification::updateOrInsert(['identity' => $customer['email'], 'order_details_id' => $order_details_data->id], $verify_data);
                DigitalProductOtpVerification::updateOrInsert(['identity' => $customer['phone'], 'order_details_id' => $order_details_data->id], $verify_data);

                $reset_data = DigitalProductOtpVerification::where('identity', $customer['email'])->orWhere('identity', $customer['phone'])->where('order_details_id', $order_details_data->id)->latest()->first();
                $otp_resend_time = Helpers::get_business_settings('otp_resend_time') > 0 ? Helpers::get_business_settings('otp_resend_time') : 0;
                $token_time = Carbon::parse($reset_data->created_at);
                $convert_time = $token_time->addSeconds($otp_resend_time);
                $time_count_in_second = $convert_time > Carbon::now() ? Carbon::now()->diffInSeconds($convert_time) : 0;
                $mail_status = 0;

                if ($emailServices_smtp['status'] == 1) {
                    try{
                        Mail::to($customer['email'])->send(new \App\Mail\DigitalProductOtpVerificationMail($token));
                        $mail_status = 1;
                    } catch (\Exception $exception) {
                    }
                }

                $response = '';
                if($sms_config_status && $published_status == 1){
                    $response = SmsGateway::send($customer['phone'], $token);
                }else if($sms_config_status && $published_status == 0){
                    $response = SMS_module::send($customer['phone'], $token);
                }

                $sms_status = ($response == "not_found" || $sms_config_status == 0) ? 0 : 1;
                if($mail_status || $sms_status){
                    return response()->json([
                        'status' => 1,
                        'email_config_status' => $emailServices_smtp['status'],
                        'sms_config_status' => $sms_config_status,
                        'email_sent_status' => $mail_status,
                        'sms_sent_status' => $sms_status,
                        'time_count_in_second'=> $time_count_in_second,
                    ], 200);
                }else{
                    return response()->json([
                        'status' => 0,
                        'email_config_status' => $emailServices_smtp['status'],
                        'sms_config_status' => $sms_config_status,
                        'email_sent_status' => $mail_status,
                        'sms_sent_status' => $sms_status,
                        'time_count_in_second'=> $time_count_in_second,
                    ], 403);
                }
            }
        }else{
            return response()->json([
                'status' => 0,
                'email_config_status' => $emailServices_smtp['status'],
                'sms_config_status' => $sms_config_status,
                'email_config_status' => $emailServices_smtp['status'],
                'sms_config_status' => $sms_config_status,
            ], 403);
        }

    }

    public function digital_product_download_otp_verify(Request $request)
    {
        $verification = DigitalProductOtpVerification::where(['token' => $request->otp, 'order_details_id' => $request->order_details_id])->first();
        $order_details_data = OrderDetail::with('order.customer')->find($request->order_details_id);

        if($verification) {
            if($order_details_data){
                if( $order_details_data->product->digital_product_type == 'ready_product' && $order_details_data->product->digital_file_ready) {
                    $file_path = storage_path('app/public/product/digital-product/' .$order_details_data->product->digital_file_ready);
                    $file_name = $order_details_data->product->digital_file_ready;
                }else if ($order_details_data->digital_file_after_sell){
                    $file_path = storage_path('app/public/product/digital-product/' . $order_details_data->digital_file_after_sell);
                    $file_name = $order_details_data->digital_file_after_sell;
                }
            }

            if($request->has('action') && $request->action == "download") {
                DigitalProductOtpVerification::where(['token' => $request->otp, 'order_details_id' => $request->order_details_id])->delete();
            }

            if(isset($file_name) && File::exists(base_path('storage/app/public/product/digital-product/'. $file_name))) {
                return \response()->download($file_path);
            }else {
                return response()->json([
                    'status' => 0,
                    'message' => translate('file_not_found'),
                ]);
            }

        }else{
            return response()->json([
                'message' => translate('The_OTP_is_incorrect'),
            ], 403);
        }
    }

    public function digital_product_download_otp_resend(Request $request)
    {
        $token_info = DigitalProductOtpVerification::where(['order_details_id'=> $request->order_details_id])->first();
        $otp_interval_time = Helpers::get_business_settings('otp_resend_time') ?? 1; //minute
        if(isset($token_info) &&  Carbon::parse($token_info->created_at)->diffInSeconds() < $otp_interval_time){
            $time_count_in_second = $otp_interval_time - Carbon::parse($token_info->created_at)->diffInSeconds();

            return response()->json([
                'status'=>0,
                'time_count_in_second'=> $time_count_in_second,
                'message'=> 'Please try again after '. CarbonInterval::seconds($time_count_in_second)->cascade()->forHumans()
            ]);
        }else {
            $guest_email = '';
            $guest_phone = '';
            $token = rand(1000, 9999);

            $order_details_data = OrderDetail::with('order.customer')->find($request->order_details_id);

            try {
                if($order_details_data->order->shipping_address_data){
                    $guest_email = $order_details_data->order->shipping_address_data ? json_decode($order_details_data->order->shipping_address_data)->email : null;
                    $guest_phone = $order_details_data->order->shipping_address_data ? json_decode($order_details_data->order->shipping_address_data)->phone : null;
                }else{
                    $guest_email = $order_details_data->order->billing_address_data ? json_decode($order_details_data->order->billing_address_data)->email : null;
                    $guest_phone = $order_details_data->order->billing_address_data ? json_decode($order_details_data->order->billing_address_data)->phone : null;
                }
            } catch (\Throwable $th) {

            }

            $emailServices_smtp = Helpers::get_business_settings('mail_config');
            if ($emailServices_smtp['status'] == 0) {
                $emailServices_smtp = Helpers::get_business_settings('mail_config_sendgrid');
            }
            if ($emailServices_smtp['status'] == 1) {
                try{
                    Mail::to($guest_email)->send(new \App\Mail\DigitalProductOtpVerificationMail($token));
                    $mail_status = 1;
                } catch (\Exception $exception) {
                    $mail_status = 0;
                }
            } else {
                $mail_status = 0;
            }

            $published_status = 0;
            $payment_published_status = config('get_payment_publish_status');
            if (isset($payment_published_status[0]['is_published'])) {
                $published_status = $payment_published_status[0]['is_published'];
            }

            $response = '';
            if($published_status == 1){
                $response = SmsGateway::send($guest_phone, $token);
            }else{
                $response = SMS_module::send($guest_phone, $token);
            }

            $sms_status = $response == "not_found" ? 0 : 1;

            if($mail_status || $sms_status)
            {
                $verify_data = [
                    'order_details_id' => $order_details_data->id,
                    'token' => $token,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                DigitalProductOtpVerification::updateOrInsert(['identity' => $guest_email, 'order_details_id' => $order_details_data->id], $verify_data);
                DigitalProductOtpVerification::updateOrInsert(['identity' => $guest_phone, 'order_details_id' => $order_details_data->id], $verify_data);
            }

            return response()->json([
                'mail_status'=> $mail_status,
                'sms_status'=> $sms_status,
                'status' => ($mail_status || $sms_status) ? 1 : 0,
                'new_time' => $otp_interval_time,
                'message'=>'OTP sent successfully',
            ]);

        }
    }

    public function order_again(Request $request){
        $data = OrderManager::order_again($request);
        $order_product_count = $data['order_product_count'];
        $add_to_cart_count = $data['add_to_cart_count'];

        if($order_product_count == $add_to_cart_count){
            return response()->json(['message' => 'Added to cart successfully'], 200);
        }elseif($add_to_cart_count>0){
            return response()->json(['message' => $add_to_cart_count.' item added to cart successfully!'], 200);

        }{
            return response()->json(['message' => 'All items were not added to cart as they are currently unavailable for purchase'], 403);
        }
    }

    public function offline_payment_method_list(Request $request)
    {
        $data = OfflinePaymentMethod::where('status', 1)->get();
        return response()->json(['offline_methods'=>$data], 200);
    }

    public function track_order(Request $request)
    {

        $user = Helpers::get_customer($request);

        if ($user != 'offline') {
            $order = Order::where(['id'=> $request['order_id'], 'order_type'=>'default_type'])->first();
            if($order && $order->is_guest){
                $orderDetails = Order::where(['id'=> $request['order_id'], 'order_type'=>'default_type'])->whereHas('shippingAddress', function ($query) use ($request) {
                    $query->where('phone', $request->phone_number);
                })->first();

                if(!$orderDetails){
                    $orderDetails = Order::where(['id'=> $request['order_id'], 'order_type'=>'default_type'])->whereHas('billingAddress', function ($query) use ($request) {
                            $query->where('phone', $request->phone_number);
                        })->first();
                }
            }elseif ($user->phone == $request->phone_number) {
                $orderDetails = Order::where(['id'=> $request['order_id'], 'order_type'=>'default_type', 'customer_id'=> auth('customer')->id()])
                    ->whereHas('details', function ($query) {
                       return $query;
                    })->first();
            }

            if ($request->from_order_details == 1) {
                $orderDetails = Order::where(['id'=> $request['order_id'], 'order_type'=>'default_type'])->whereHas('details', function ($query) {
                    $query->where('customer_id', auth('customer')->id());
                })->first();
            }

        } else {
            $user_id = User::where('phone', $request->phone_number)->first();
            $order = Order::where('id', $request['order_id'])->first();

            if($order && $order->is_guest){
                $orderDetails = Order::where(['id'=> $request['order_id'], 'order_type'=>'default_type'])->whereHas('shippingAddress', function ($query) use ($request) {
                    $query->where('phone', $request->phone_number);
                })->first();

                if(!$orderDetails){
                    $orderDetails = Order::where(['id'=> $request['order_id'], 'order_type'=>'default_type'])->whereHas('billingAddress', function ($query) use ($request) {
                            $query->where('phone', $request->phone_number);
                        })->first();
                }
            }elseif($user_id){
                $orderDetails = Order::where(['customer_id'=> $user_id->id, 'id'=> $request['order_id'], 'order_type'=>'default_type'])->whereHas('details', function ($query) {
                    return $query;
                })->first();
            }else{
                return response()->json(['message' => 'Invalid Phone Number'], 403);
            }
        }

        if (isset($orderDetails)) {
            $details = OrderDetail::with(['order.delivery_man','verification_images','seller.shop'])
                ->where(['order_id' => $orderDetails['id']])
                ->get();
            $details->map(function ($query) {
                $query['variation'] = json_decode($query['variation'], true);
                $query['product_details'] = Helpers::product_data_formatting(json_decode($query['product_details'], true));
                return $query;
            });

            return response()->json($details, 200);
        }

        return response()->json(['message' => 'Invalid Order Id or Phone Number'], 403);
    }
}
