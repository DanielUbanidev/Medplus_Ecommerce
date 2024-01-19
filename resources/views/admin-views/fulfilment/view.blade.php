@extends('layouts.back-end.app')
@section('title', translate('attribute'))
@push('css_or_js')
<!-- Custom styles for this page -->
<link href="{{asset('public/assets/back-end')}}/vendor/datatables/dataTables.bootstrap4.css" rel="stylesheet">

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
	
	<!-- The Modal -->
	<div class="modal" style="background-color: rgb(35 16 16 / 80%) !important;"  id="viewCustomRequest">
		<div class="modal-dialog modal-xl" >
			<div class="modal-content" style="border-colour: black">
				
				<!-- Modal Header -->
				<div class="modal-header">
					<h4 class="modal-title">Request Details</h4>
					<button type="button" class="close" data-dismiss="modal">&times;</button>
				</div>
				
				<!-- Modal body -->
				<div class="modal-body">
					<div class="row">
						<div id="pickerNameDiv" class="col-sm-6">
							<div class="form-group">
								<label style="padding-left: 1em;" class="form-label input-label">Requester Name<span class="input-label-secondary" style="color: red"> (Required)</span></label>
								<span  id="cr_name_of_customer" class="form-control"  style="padding-left: 1em; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); position: relative; display: inline-block;"></span>
								
							</div>
						</div>
						
						<div id="pickerMobileDiv" class="col-sm-6">
							<div class="form-group">
								<label style="padding-left: 1em;" class="form-label input-label">Phone<span class="input-label-secondary" style="color: red"> </span></label>
								<span id="cr_phone_no"  class="form-control"   style="padding-left: 1em; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); position: relative; display: inline-block;"></span>
							</div>
						</div>
					</div>
					
					<div class="row">
						<div id="pickerNameDiv" class="col-sm-6">
							<div class="form-group">
								<label style="padding-left: 1em;" class="form-label input-label">Product Name<span class="input-label-secondary" style="color: red"> </span></label>
								<span  id="cr_name_of_product" class="form-control"  style="padding-left: 1em; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); position: relative; display: inline-block;"></span>
								
							</div>
						</div>
						
						<div id="pickerMobileDiv" class="col-sm-6">
							<div class="form-group">
								<label style="padding-left: 1em;" class="form-label input-label">Quantity<span class="input-label-secondary" style="color: red"> </span></label>
								<span id="cr_quantity"  class="form-control"   style="padding-left: 1em; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); position: relative; display: inline-block;"></span>
							</div>
						</div>
					</div>
					
					<div class="row">
						<div id="pickerNameDiv" class="col-sm-6">
							<div class="form-group">
								<label style="padding-left: 1em;" class="form-label input-label">Status<span class="input-label-secondary" style="color: red"> </span></label>
								<span  id="cr_status" class="form-control" type="text" style="padding-left: 1em; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); position: relative; display: inline-block;"></span>
								
							</div>
						</div>
						
						<div id="pickerMobileDiv" class="col-sm-6">
							<div class="form-group">
								<label style="padding-left: 1em;" class="form-label input-label">Date Created<span class="input-label-secondary" style="color: red"></span></label>
								<span id="cr_created_at"  class="form-control" type="number"  style="padding-left: 1em; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); position: relative; display: inline-block;"></span>
							</div>
						</div>
					</div>
					
					<div class="row">
						<div id="pickerNameDiv" class="col-sm-6">
							<div class="form-group">
								<label style="padding-left: 1em;" class="form-label input-label">Change Status<span class="input-label-secondary" style="color: red"> </span></label>
								<select  id="cr_statusChange" class="form-control" style="padding-left: 1em; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); position: relative; display: inline-block;">
									<option value="Pending">Pending</option>
									<option value="Processing">Processing</option>
									<option value="Unavailable">Unavailable</option>
									<option value="Completed">Completed</option>
								</select>
								
							</div>
						</div>
						
						<div id="pickerMobileDiv" class="col-sm-6">
							<div class="form-group">
								<label style="padding-left: 1em;" class="form-label input-label">Product Image<span class="input-label-secondary" style="color: red"></span></label>
								<img id="productImage" class="form-control" style="height: 600px; padding-left: 1em; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); position: relative; display: inline-block;"></img> 
							</div>
						</div>
					</div>
					<input id="cr_id" type="hidden"></input>
					
				</div>
				
				<!-- Modal footer -->
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
					<button type="button" id="saveCustomStatus" class="btn btn-primary" >Save Status</button>
					<h6 id="processTxt" style="color: red">Processing... Please wait</h6>
				</div>
				
			</div>
		</div>
	</div>
	
	
	
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
							<h3 class="mb-0 d-flex align-items-center gap-2">Special Request Manager for Fufillment Department
								
							</h>
						</div>
						
					</div>
				</div>
                <div>
                    
					<br></br>
					<div class="card-body">   
						<!-- Start Tx Table -->
						<div class="table-responsive">
							<div class="table-responsive" style="border-style: solid; border-color: lightgrey; border-width: thin; border-radius: 1em">
								<table id="loan_tx_dashboard" class="display table table-condensed responsive display table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table w-100" style="width:100%; border-color: azure; font-size: 15px; padding:0.3em;"></table>
							</div>
						</div>
						<!-- End Tx Table -->
					</div>
				</div>
				
				
			</div>
			
		</div>
	</div>
</div>
</div>
@endsection

@push('script')


<script src="{{asset('public/assets/back-end')}}/vendor/datatables/dataTables.bootstrap4.min.js" rel="stylesheet"></script>

<script>
	
	
	
	
	function buildtxTable(){
        
        $('.loader-wrap').css("display", "block");
        
		//   var userId = window.localStorage.getItem('authId');
        
		
		const settings = {
			"async": true,
			"crossDomain": true,
			"url": "https://medplus.collaboratoor.com/ecommercexyz/service/mother.php",
			"method": "POST",
			"headers": {
				"Content-Type": "application/x-www-form-urlencoded",
				"User-Agent": "insomnia/2023.5.8"
			},
			"data": {
				"condition": "getAllCustomRequest"
			}
		};
		
		$.ajax(settings).done(function (response) {
            
			// $('.loader-wrap').css("display", "block");
            
            response = JSON.parse(response);
            payload = response.sort(function(a, b) {
                return parseFloat(b.id) - parseFloat(a.id);
			});
            
            window.localStorage.setItem('authloanData', JSON.stringify(payload));
            
            
            if ($.fn.dataTable.isDataTable('#loan_tx_dashboard')) {
                table1 = $('#loan_tx_dashboard').DataTable();
                table1.clear();
                table1.rows.add(payload).draw();
                table1.bAutoWidth = "false";
                table1.autoWidth = "false";
                responsive = "true",
                bPaginate = "true",
                pageLength = "10"
                }else{
                table1 = $('#loan_tx_dashboard').DataTable({
                    
                    bAutoWidth: false,
                    order: [[ 0, 'dsc' ]],
                    autoWidth: false,
                    data: payload,
                    responsive: true,       
                    bPaginate: true,
                    bLengthChange: false,
                    bFilter: true,
                    bInfo: false,
                    pageLength : 50,
                    columns: [
                    { data: "id", render: function (data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
					}},
                    { data: "name_of_customer", title: "Customer Name"},
                    { data: "phone_no",  title: "Mobile"},
                    { data: "name_of_product", title: "Product Name"},
                    { data: "quantity", title: "Quantity"},
                    { data: "created_at", title: "Date Created"},
                    { data: "status", title: "Status"},
					{
						data: null,
						className: "center", 
						defaultContent: '<a data-toggle="modal" onclick="openBox()"  class="btn btn-primary editor_view">Update Record</a>'
						
					}
                    ],
                    "createdRow": function( row, data, dataIndex){
                        <!-- if( data['status'] ==  `Initiated`){ -->
                        <!-- $(row).css("background-color", "aliceblue"); -->
                        <!-- } -->
                        if( data['status'] ==  `Processing`){
                            $(row).css("background-color", "orangered");
						}
                        if( data['status'] ==  `Completed`){
                            $(row).css("background-color", "lightpink");
						}
                        if( data['status'] ==  `Unavailable`){
                            $(row).css("background-color", "lightgreen");
						}
					},
					
				})
                
			}
			
			
			}).fail(function(errorData){
			
			//  $('.loader-wrap').css("display", "block");
			// showAlert("error", "Cannot retrieve data", "Network error. Check your network connection");
			// $('.loader-wrap').css("display", "none");
			
		})
		
	} buildtxTable();
	
	
	function openBox(){
		var table = $('#loan_tx_dashboard').DataTable();
		$('#loan_tx_dashboard tbody').on('click', 'tr', function () {
			console.log(table.row(this).data());
			
			var data = table.row(this).data();
			
			$('#viewCustomRequest').modal('show');
			
			$('#cr_name_of_customer').html(data['name_of_customer']);
			$('#cr_phone_no').html(data['phone_no']);
			$('#cr_name_of_product').html(data['name_of_product']);
			$('#cr_quantity').html(data['quantity']);
			$('#cr_status').html(data['status']);
			$('#cr_created_at').html(data['created_at']);
			$('#cr_id').val(data['id']);
			
			$('#productImage').attr('src', data['images']);
		});
	}
	
	$('#saveCustomStatus').click(function(){
		$('#saveCustomStatus').hide();
		$('#processTxt').show();
		const settings = {
			"async": true,
			"crossDomain": true,
			"url": "https://medplus.collaboratoor.com/ecommercexyz/service/mother.php",
			"method": "POST",
			"headers": {
				"Content-Type": "application/x-www-form-urlencoded",
				"User-Agent": "insomnia/2023.5.8"
			},
			"data": {
				"condition": "updateCustomStatus",
				"newStatus": $('#cr_statusChange').val(),
				"customId": $('#cr_id').val()
			}
		};
		
		$.ajax(settings).done(function (response) {
			console.log(response);
			
			toastr.success("Record updated successfully", {
				CloseButton: true,
				ProgressBar: true,
				onHidden: function () {
					$('#saveCustomStatus').show();
					$('#processTxt').hide();
					$('#viewCustomRequest').modal('hide');
					
					location.reload();
				}
			})
			
			
			
			}).fail(function(jqXHR, textStatus, errorThrown) {
			
			console.error(JSON.stringify(jqXHR + " | " + textStatus + " | " + errorThrown));
			
			
			
			toastr.error("Sent failed! try again " + JSON.stringify(jqXHR + " | " + textStatus + " | " + errorThrown), {
				CloseButton: true,
				ProgressBar: true,
				onHidden: function () {
				
					$('#viewCustomRequest').modal('hide');
					$('#saveCustomStatus').show();
					$('#processTxt').hide();
				}
			});
			
			
			
			
		});
		
	});
	
	$('#processTxt').hide();
	
	toastr.options.timeOut = 1000;
	toastr.options.fadeOut = 1000;
	toastr.options.onHidden = function(){
		// this will be executed after fadeout, i.e. 2secs after notification has been show
		 window.location.reload();
	};
	
</script>
@endpush
