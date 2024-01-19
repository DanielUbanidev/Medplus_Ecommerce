@extends('layouts.back-end.app')
@section('title', translate('attribute'))
@push('css_or_js')
    <!-- Custom styles for this page -->
    <link href="{{asset('public/assets/back-end')}}/vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link href="{{asset('public/assets/back-end/css/croppie.css')}}" rel="stylesheet">
@endpush

@section('content')
<div class="content container-fluid">
    <!-- Page Title -->
    <div class="mb-3">
        <h2 class="h1 mb-0 d-flex gap-2">
            <img src="{{asset('/public/assets/back-end/img/attribute.png')}}" alt="">
            {{translate('Fulfilment')}}
        </h2>
    </div>
    <!-- End Page Title -->

    <!-- Content Row -->
    <div class="row">
        <div class="col-md-12 mb-3">
            <div class="card">
                <!-- <div class="card-header">
                    {{ translate('add_new_attribute')}}
                </div> -->
            </div>
        </div>

        <div class="col-md-12">
            <div class="card">
                <div class="px-3 py-4">
                    <div class="row align-items-center">
                            <div class="col-sm-4 col-md-6 col-lg-8 mb-2 mb-sm-0">
                                <h5 class="mb-0 d-flex align-items-center gap-2">{{ translate('Fulfilment List')}}

                                </h5>
                            </div>
                            
                        </div>
                </div>
                <div style="text-align: {{Session::get('direction') === "rtl" ? 'right' : 'left'}};">
                    <div class="table-responsive">
                        <table id="datatable"
                               class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table w-100">
                            <thead class="thead-light thead-50 text-capitalize">
                                <tr>
                                    <th>{{translate('SL')}}</th>
                                    <th>{{translate('name of customer')}}</th>
                                    <th>{{translate('phone number')}}</th>
                                    <th>{{translate('name of product')}}</th>
                                    <th>{{translate('quantity')}}</th>
                                    <!-- <th class="text-center">{{translate('order_Status')}} </th> -->
                                    <!-- <th class="text-center">{{translate('action')}}</th> -->
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($fulfil as $key=>$row)
                                <td>{{ $loop->index + 1 }}</td>
                                <td>{{$row->name_of_customer}}</td>
                                <td>{{$row->phone_no}}</td>
                                <td>{{$row->name_of_product}}</td>
                                <td>{{$row->quantity}}</td>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                
            </div>
        </div>
    </div>
</div>
@endsection

