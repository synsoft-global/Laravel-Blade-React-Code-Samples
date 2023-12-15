@extends('layouts.app')
<style type="text/css">
.main-container {
    margin:0 !important;
    width: 100% !important;
    padding: 10px;
    margin-bottom:10px !important;
}
</style>
@section('content')

<div class="page-content page-loading" id="page_loading" style="display: none;">
	<!--start header row -->
	<div class="product-grp-container">
		<div class="row top-subnav navbar-fixed-top"> 
			<div class="col-sm-12 col-md-4 subnav-left "> 
				<ul class="nav nav-tabs subnav__nav" role="tablist">
					<li class="nav-item subnav__nav-item">
						<a class="nav-link subnav__nav-link active" href="{{url('products')}}"><i class="fa fa-chevron-circle-left mr-2" aria-hidden="true"></i>{{ __('messages.product_detail.All_Product') }}</a>
					</li> 
				</ul>
			</div>
			<div class="col-sm-12 col-md-12 col-lg-8 float-left subnav-right subnav-flex">
	    		{!!view("products.ajax.topright",array('order_detail'=>true))->render()!!}
	  		</div>
		</div>
	</div>	
	<!--end header row -->

	<!--start left top product information grid-->
	<div class="container-fluid ">
	  <div class="row">
	      <div class="col-sm-12 col-md-4 col-lg-4">
	        <div class="main-container">
	            <div class="product-information">
	              <div class="row">
	                <div class="col-8 pull-left">
	                  <h3 class="">
	                {{isset($product->name)?$product->name:''}}
	                <!----></h3>

	                <h4><!----> <span>{{isset($product->sku)?$product->sku:''}} <span class="pdt-tag-amount">@money($product->price)</span></span></h4>

	                 <a href="{{url('product/edit/'.$product->id)}}" target="_blank" class="btn btn-outline-primary btn-sm mb-2 mr-3"><i class="fa fa-pencil-square-o mr-2" aria-hidden="true"></i>{{ __('messages.product_detail.Edit') }}</a>
	                  <!-- <a href="{{$product->permalink}}" target="_blank" class="btn btn-outline-primary btn-sm mb-2 mr-3"><i class="fa fa-eye mr-2" aria-hidden="true"></i>{{ __('messages.product_detail.View') }}</a> -->
	                </div>
	                <div class="col-4 pull-left">
	                  <img src="{{isset($product->images)?$product->images:''}}" onerror="this.src='{{asset('theme/img/defproduct.png')}}'" class="image">
	                </div>
	              </div>
	            </div>
	           <!-- end product-information-->
	          </div>
	            <!--start main container1-->
	           <div class="main-container product-stock">
	            <i class="fa fa-hashtag mr-3" aria-hidden="true"></i>
	            @if(!empty($product->stock_status) && $product->stock_status == "instock")
	            <span class="color-success">In Stock</span>
	            @else
	            <span class="stock-status">Out Of Stock</span>
	            @endif
	            <span class="x">x</span> 
	            <span class="stock-count">{{isset($product->stock_quantity)?$product->stock_quantity:0}}</span>
	           </div>
	            <!--start main container2-->
	           	<div class="main-container ">
					<div class="product-categories">
						<i class="fa fa-archive mr-3" aria-hidden="true"></i> 
							@foreach($category as $key => $category_val)
								<a href="{{url('categories')}}/{{isset($category_val->id)?$category_val->id:''}}" class="">{{$category_val->name}}</a>
								@if($key < count($category)-1)
								, 	
								@endif
							@endforeach
					</div>
				</div>
				<!--<span class="meta-actions"><button type="button" class="btn btn-outline-secondary btn-sm mb-2 mr-3"><span>Force update #15599</span></button></span>-->
	        </div>

	        <div class="col-sm-12 col-md-8 col-lg-8">
	        		<div class="main-container " id="product-chart">
						{!!view("products.ajax.ajaxchart",$chart)->render()!!}
					</div>
					<div  class="main-container pdt-container">
						<div class="product-stats-boxes">
						<div class="{{$number_of_orders > 0 ? 'row tight-inner-gutter_dummy' : 'row tight-inner-gutter_dummy hide_c' }}" id="net_data">
							{!!view("products.ajax.ajaxnetdata",$net_data)->render()!!}
						</div>
						<div class="row tight-inner-gutter_dummy" id="net_data">
							<div class="col-md-12 col-sm-12 col-xs-12 psfwg">
								<div id="net_avg" class="{{$number_of_orders > 1 ? 'stats-box' : 'stats-box hide_c' }} mb-0">
									{!!view("products.ajax.ajaxordernumber",$net_data['net_data']['net_data'][0])->render()!!}
								</div>
							</div>

							<div class="container-fluid pt-0">
								<div class="row">
									<div class="col-12">
										<hr class="psfwg-divider">
									</div>
								</div>
							</div>

							<div class="col-md-12 col-sm-12 col-xs-12 psfwg"> 
								<div id="filter_product_detail" class="test45">
									{!!view("products.ajax.ajaxfilter",$order_placed_data)->render()!!}
								</div>
							</div>

						<div class="{{$number_of_orders > 1 ? 'container-fluid' : 'hide_c'}} pt-0">
							<div class="row">
								<div class="col-12">
									<hr class="psfwg-divider">
								</div>
							</div>
						</div>

						<div class="{{$number_of_orders > 0 ? 'col-md-12 col-sm-12 col-xs-12 psfwg' : 'col-md-12 col-sm-12 col-xs-12 psfwg hide_c' }}" id="product_order">
							<div class="main-container">
								<div class="container-fh__content dataset">
									<div class="dataset__header">
										<div class="dataset__header-side max-width-400" id="number_of_orders">
										<h3>	{!!view("products.ajax.ajaxnumberoforders",$net_data['net_data']['net_data'][0])->render()!!}   </h3>
										</div>
										<div class="dataset__header-controls"> 
										<a class="color-info view-order" href="javascript:void(0)">
										View All
										</a>
										</div>
									</div>
								</div>
								<div class="dataset__body dataset__body--panel order-custom-container" id="product_order_details">
								  {!!view("products.ajax.ajaxorder",$order_details)->render()!!}
								</div>
							</div>
						</div>
					</div>
					<!--end row tight-inner-gutter-->
					</div>
					<!--end product-stats-boxes-->
					</div>
				</div>
	  </div>
	</div>
	<!--end left top product information grid-->
@endsection