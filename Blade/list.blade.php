@extends('layouts.app')
<style type="text/css">
  
.main-container {
    margin:0 !important;
    width: 100% !important;
    padding: 10px;
}

</style>
@section('content')



<div class="page-content product-grp-container page-loading reports-revenue-grid widgets-products-page" id="page_loading" style="display: none;">

<!--start header row -->

  <div class="row top-subnav navbar-fixed-top"> 
  <div class="col-sm-12 col-md-12 col-lg-4 subnav-left"> 
     <ul class="nav nav-tabs subnav__nav" role="tablist">
      <li class="nav-item subnav__nav-item">
        <a class="nav-link subnav__nav-link {{(Request::segment(1)=='products')?'active':''}}" href="{{url('products')}}">{{ __('messages.products.products') }}</a>
      </li>
      <li class="nav-item subnav__nav-item">
        <a class="nav-link subnav__nav-link {{(Request::segment(1)=='segments_products')?'active':''}}" href="{{url('segments_products')}}">{{ __('messages.customers.segments') }}</a>
      </li>   
    </ul>
  </div>
   <!-- <div class="col-sm-12 col-md-12 col-lg-8 float-left subnav-right subnav-flex"> -->
   <div class="col-sm-12 col-md-12 col-lg-8 float-left subnav-right subnav-flex">
      {!!view("products.ajax.topright")->render()!!}
    </div>
  </div>



<!--end header row -->


<!--START WIDGETS SECTION-->

<div class="container-fluid stats-widgets-sections">
  <div class="product-result"> 
       <div class="d-flex justify-content-center my-3 width100 pull-left">
        <div class="spinner-border" role="status">
          <span class="sr-only">Loading...</span>
        </div>
      </div>  
  </div>
</div>

<!--end WIDGETS SECTION-->





<!-- start hr tag diversion--->

<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <div class="main-container box-shadow-rounded">

        <div class="search-overlay" style="display: none">
            <div class="search-notice" style="display: none">
                  You can't <strong>segment</strong> and <strong>search</strong> at the same time.
              <button type="button" class="btn btn-primary btn-sm" id="clear_search_segment"><span>Clear search</span></button>
            </div>
          </div>

        <div class="row customers-filters-area-1">
           <div class="diversion-centre row p-0 m-0">
          <div class="col-sm-7 col-md-7">
             <div class="floatnone767">
              <!-- start select boxc--> 
              <div class="customer-filter-initial-dummy">   
              <select data-resource="products" data-initialfilter="getCustomerInitialFilterHtml" data-placeholder="Segment products by anything..." id="segment_filter" placeholder="Segment products by anything..." tabindex="-1" class="selectbox form-control hide_c segment_filter customer_segment_select customer-filter-initial" data-filter="0" style="visibility: hidden;height: 0px !important;width: 0px;padding: 0;">
                  <option></option>              
                    @foreach($filter_options['segment_filter'] as $key => $val)
                    <optgroup label="{{$val['display']}}">
                      @foreach($val['value'] as $key => $option)
                      <option data-column="{{$option['column']}}" data-condition="{{$option['condition']}}" data-method="{{$option['method']}}" value="{{$option['key']}}">{{$option['value']}}</option>
                     @endforeach
                     </optgroup>
                    @endforeach                         
                </select>                 
                  <select data-resource="products" data-initialfilter="getCustomerInitialFilterHtml" data-placeholder="Segment products by anything..." id="segment_filter_1" placeholder="Segment products by anything..." tabindex="-1" class="selectbox form-control segment_filter_1 customer_segment_select_1 adv-select-autofilter customer-filter-initial-1 selectize_select" data-filter="0">
                <option></option>              
                  @foreach($filter_options['segment_filter'] as $key => $val)
                  <optgroup label="{{$val['display']}}">
                    @foreach($val['value'] as $key => $option)
                    <option data-column="{{$option['column']}}" data-condition="{{$option['condition']}}" data-method="{{$option['method']}}" value="{{$option['key']}}">{{$option['value']}}</option>
                   @endforeach
                   </optgroup>
                  @endforeach                         
              </select>   
              </div>         
               <!-- end select boxc--> 

               <div class="filters-container customers-filters-area" style="display:none;">                  
                <div class="header">
                  <div class="title">
                    <h5 class="pdt-match">{{ __('messages.products.PRODUCTS_MATCH') }} </h5> 
                    <div class="filterselectbox">
                      
                         <select class="form-control" id="filter_type" name="filter_type">                           
                             <option  {{(isset($segment->filter_type) && $segment->filter_type == "all")?'selected':''}} value="all">{{ __('messages.customers.All') }}</option>
                             <option  {{(isset($segment->filter_type) && $segment->filter_type == "any")?'selected':''}} value="any">{{ __('messages.customers.Any') }}</option>                   
                          </select>
                    </div> 
                    <h5 class="pdt-follow">{{ __('messages.customers.OF_THE_FOLLOWING') }}:</h5>
                  </div>
                </div>           
            </div>  
             </div>

          </div>   
             <div class="col-sm-5 col-md-5">
              <div class="pull-right choose-seg-centre-grid">                
                <div class="dropdown dropdown-lg adv-filter-search-bar">
                  <button class="btn btn-outline-info dropdown-toggle customer-segment-list-btn" type="button" data-toggle="dropdown">{{ __('messages.customers.choose_a_segment') }}</button>

                  <div class="dropdown-menu dropdown-menu-right navbar-dropdown-menu navbar-dropdown-notifications navbar-dropdown-messages" role="menu">
                            <form class="form-horizontal segment-filter-opt" role="form">
                              <div class="search-filters ">  
                                   <!-- loader grid -->
                          <div class="col-sm-12 col-md-12 segment_loading" style="display: none;">   
                           <div class="d-flex justify-content-center my-3 width100 pull-left">
                              <div class="spinner-border" role="status">
                                <span class="sr-only">Loading...</span>
                              </div>
                            </div>
                          </div>
                      <!-- loader grid -->

                      <div class="checkbox-group my-3 mx-3">
                        <div class="col-sm-8 col-md-8">  
                          <label class="checkbox-group__item width100">
                            <input type="text" placeholder="Search segments..." class="form-control search search-filters-customer-segment"> 
                          </label>
                        </div>
                         <div class="col-sm-4 col-md-4 adv-serch-btn">
                          <label class="checkbox-group__item width100">
                            <input type="checkbox" id="mine_segmenting" name="mine_segmenting" class="checkbox-group__input">
                            <span  class="checkbox-group__text ">Mine</span>
                          </label>  
                          </div> 

                        </div>

                    </div>
                     <div class="save-filter-content" id="segments-list">
                       @if(isset($segments))

                       @foreach($segments as $key => $val)                       
                                   
                          <a class="dropdown-item customer-segment-list" href="{{url('products?saved-segment='.$val->id)}}">     
                            <ul class="list-group list-group-flush">
                              <li class="list-group-item">{{isset($val->segment_name)?$val->segment_name:''}}
                                <?php 
                                 if(!empty($val->description)){
                                        echo '&nbsp;<span class="suggested_icon"><i class="fa fa-bolt" aria-hidden="true"></i> Suggested</span>';
                                    }
                                ?>
                              </li>
                              <li class="list-group-item segment-popover-desc">{{isset($val->description)? $val->description:''}}</li>
                              <li class="list-group-item segment-popover-desc">{{$val->getName()}}</li>
                              
                            </ul>  
                          </a>
                      
                        <!-- <div class="dropdown-divider"></div> -->
                       @endforeach
                       @endif   
                    </div>

                     </form>
                       <div class="segments-link">
                        <a class="dropdown-item" href="{{url('segments_products')}}"> 
                        {{ __('messages.common.view_segments_page')}} <i class="fa fa-chevron-circle-right" aria-hidden="true"></i></a>
                      </div>
                                
                     
                    </div>
                </div>

              </div>
               

             </div>   
              </div>   
            <!-- end diversion-centre row  -->    
        </div>
        <!--end row-->
         <div class="customers-filters-area" style="display:none;">  
            <hr>
            <div class="customer-body-area">
                
            </div>
            <hr>

           <!-- start toggle button section -->

            <div class="instant-segment-group">
                  <div class="btn-group btn-collection segment-section">
                        <button class="btn btn-outline-info in-segmenting" type="button">Instantly segmenting</button>      

                        <label class="checkbox-group__item ">
                          <input  id="instantly_segmenting" name="instantly_segmenting" type="checkbox" name="checkbox-group" class="checkbox-group__input" checked="checked">
                          <span id="instantly_segmenting_popover" data-placement="bottom" data-toggle="popover" data-trigger="hover" data-content="Disable instant segmenting" class="checkbox-group__text"><i class="fa fa-bolt" aria-hidden="true"></i></span>
                        </label>                         
                       
                  </div>
            </div>

            <div class="filters3buttons btn-group btn-collection {{($edit_segment) ? '':'hide_c' }}" id="update_more_section">
              <button class="btn btn-secondary btn-disabled" type="button"><strong><span id="view_segment_name">{{isset($segment->segment_name) ? $segment->segment_name : ''}}</span> </strong>
                                 &nbsp; <span class="segment-toggle-typo">segment selected</span></button>

              <button class="btn btn-secondary make_a_copy_segment" type="button" data-placement="top" data-toggle="popover" data-trigger="hover" data-content="Make a copy of this segment(you can change the name, filters , etc. before saving)" data-tippy-placement="top"><i class="fa fa-files-o" aria-hidden="true"></i></button>

              <button data-placement="top" data-toggle="popover" data-trigger="hover" data-content="De-select Segment" data-tippy-placement="top" class="btn btn-secondary deselect_segment" type="button"> <i class="fa fa-minus-circle" aria-hidden="true"></i></button>

               <button data-placement="top" data-toggle="popover" data-trigger="hover" data-content="Delete this segement" data-tippy-placement="top" data-toggle="modal" data-target="#modal-delete-segment" id="delete_segment_btn" class="btn btn-secondary delete_segment_btn" type="button"><i class="fa fa-trash" aria-hidden="true"></i></button>

            </div>


          <div class="segment-actions-group">
            <button class="btn btn-success mr-2 customer-save-button" type="button"><i class="fa fa-floppy-o mr-1" aria-hidden="true"></i> {{$segment_btn_text}}</button>
            <button class="btn btn-secondary reset-customer-filters" type="button"><i class="fa fa-eraser mr-1" aria-hidden="true"></i>Reset Filters</button>
             <div class="segment-actions customer-after-save-buttons segment-actions-save" style="display:none;">
              <div class="row">
                <div class="col-sm-12 col-md-4">
                  <input placeholder="Segment Name" class="form-control" value="{{isset($segment->segment_name) ? $segment->segment_name : ''}}" type="text" name="segment_name" id="segment_name" required="">
                
                </div>
                <div class="col-sm-12 col-md-4">             
                 
                  <span data-placement="top" data-toggle="popover" data-trigger="hover" data-content="{{ __('messages.common.who_access_seg_text') }}" data-tippy-placement="top">
                  <select class="is_public" id="is_public">  
                    <option {{(isset($segment->is_public) && $segment->is_public == "1")?'selected':''}} value="1">Whole Team</option>
                    <option {{(isset($segment->is_public) && $segment->is_public == "0")?'selected':''}} value="0">Just For Me</option>                             
                  </select></span>
                </div>
                <div class="col-sm-12 col-md-4 text-left">
                  <button data-rowid="{{isset($segment->id) ? $segment->id : ''}}" disabled type="button" class="btn btn-success" id="save_customer_segment">Save {{($edit_segment) ? 'Changes'  : ''}}</button>   <button type="button" class="btn btn-danger" id="cancel_customer_segment">Cancel</button>                         

                </div>
               
              </div>
            </div>
          </div>

         <!-- <div class="docs-link">
            <a href="" target="_blank">
              <button type="button" class="btn btn-outline-info">
                 <span>View Docs <i class="fa fa-info-circle" aria-hidden="true"></i></span>
              </button>
            </a>
          </div>-->                       



           <!-- end toggle button section -->
         </div>
       
    


      </div>
    </div>
  </div>  

</div>

<!-- start hr tag diversion--->
<div class="container-fluid pt-0">
  <div class="row">
    <div class="col-12">
      <hr class="hrpdtlb">
    </div>
  </div>
</div>
<!-- end hr tag diversion--->

<!-- start rxport button diversion--->
<div id="customer_export_grid"></div>
<div class="container-fluid export-search-section pt-0">
  <div class="row">
    <div class="col-sm-12 col-md-6">
      <?php 
        $btn_permission = checkPermission(Auth::user()->role->slug, 'action_btn'); 
        
        if(empty(in_array('add_product', $btn_permission))){
      ?>
      <a href="{{url('product')}}" class="btn btn-info mb-2 mr-3 pull-left">Add Product</a>      
    <?php } ?>
      <button class="btn btn-info mb-2 mr-3 pull-left" type="button" data-toggle="modal" data-target="#modal-export" id="export_btn">Export {{(isset($products['products']) && $products['products']->total() > 0)?$products['products']->total():0}} products</button>   
       <div class="form-group customer-srch-dw">
          <input id="search_customer" type="text" placeholder="{{ __('messages.products.search_products') }}" class="form-control" value="{{!empty(session('whereProductVal'))?session('whereProductVal'):''}}">
        </div>

    </div>

     <div class="col-sm-12 col-md-6 text-right pull-right">     


 
  <div class="mobile-show-btn-grid">
      <div class="btn-group btn-collection btn-icon-group">
            <button class="btn btn-outline-info customer_grid_view" type="button"><span class="btn-icon ua-icon-grid"></span></button>
            <button class="btn btn-outline-info customer_list_view" type="button"><span class="btn-icon ua-icon-list"></span></button>
      </div>
</div>
<!--end mobile-show-btn-grid -->

      <div class="orderby-label">{{ __('messages.customers.Order_By') }}</div>       

        <span class="pull-right filter-icons">
          <?php 
          

                $up = 'fa-arrow-down text-danger';
              $value = 'desc';

              if(!empty(session('orderbyProduct') && session('orderbyProduct') == "asc")){
               
                 $up ='fa-arrow-up text-primary';
               $value = 'asc';

              }
            ?>
         <i class="fa {{$up}}" aria-hidden="true" id="up" value={{$value}}></i>  
        </span>      
        
         <div class="form-group orderby-select">
          <select class="form-control" data-placeholder=" {{ __('messages.customers.Order_By') }} "  id="orderby">
            <option value="products.stock_quantity" {{!empty(session('orderbyValP') && session('orderbyValP') == "products.stock_quantity")?'selected':''}}>{{ __('messages.products.Stock_Quantity') }}</option>
                <option value="products.stock_status" {{!empty(session('orderbyValP') && session('orderbyValP') == "products.stock_status")?'selected':''}}>{{ __('messages.products.In_Stock') }}</option>
                <option value="net_revenue" {{!empty(session('orderbyValP') && session('orderbyValP') == "net_revenue")?'selected':''}}>{{ __('messages.products.Net_Revenue') }}</option>
                <option value="net_sold" {{!empty(session('orderbyValP') && session('orderbyValP') == "net_sold")?'selected':''}}>{{ __('messages.products.Net_Sold') }}</option>
                <option value="products.name" {{!empty(session('orderbyValP') && session('orderbyValP') == "products.name")?'selected':''}}>{{ __('messages.products.Title') }}</option>
                <option value="products.sku" {{!empty(session('orderbyValP') && session('orderbyValP') == "products.sku")?'selected':''}}>{{ __('messages.products.SKU') }}</option>
                <option value="products.price" {{!empty(session('orderbyValP') && session('orderbyValP') == "products.price")?'selected':''}}>{{ __('messages.products.Regular_Price') }}</option>
                <option value="products.date_created" {{!empty(session('orderbyValP') && session('orderbyValP') == "products.date_created")?'selected':''}}>{{ __('messages.products.Created') }}</option>
                <option value="products.date_modified" {{!empty(session('orderbyValP') && session('orderbyValP') == "products.date_modified")?'selected':''}}>{{ __('messages.products.Updated') }}</option>
          </select>
        </div>

    </div>


  </div>
</div>

<!-- end rxport button diversion--->


    
<div class="container-fluid"> 
  <div class="main-container order-custom-container ipad-overflow-scroll">       
      <div class="row">
          <div class="col-12" id="productTable">
             <div class="d-flex justify-content-center my-3 width100 pull-left">
                <div class="spinner-border" role="status">
                  <span class="sr-only">Loading...</span>
                </div>
            </div>                  
        </div>
      </div> 
  </div>
<!--end container-fluid-->


<div id="modal-msg" class="modal fade custom-modal-tabs show" data-backdrop="static" aria-modal="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header has-border export_msg">
      </div>
    </div>
  </div>
</div>

<!-- Modal for delete segment -->
<div id="modal-delete-segment" data-backdrop="static" class="modal fade custom-modal-tabs show" aria-modal="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header has-border">        
        <div class="text-center width100">
        <h3>Delete segment</h3>        
      </div>
        <button type="button" class="close custom-modal__close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true" class="ua-icon-modal-close"></span>
        </button>
      </div>
      <div class="modal-body text-center">      
        <div>         
          <p>
              Are you sure you want to delete the segment <span id="modal-delete-segment-msg">"{{isset($segment->segment_name) ? $segment->segment_name : ''}}"</span> ?
            </p>
        </div>  
      </div>
      <div class="modal-footer modal-footer--center"> 
         <button type="button" class="btn btn-secondary" data-dismiss="modal" aria-label="Close">
         <span>Cancel</span>
        </button>

        <input type="hidden" name="delete_segment_id" value="{{isset($segment->id) ? $segment->id : ''}}" id="delete_segment_id">
          <button type="button" class="btn btn-danger" data-rowid="{{isset($segment->id) ? $segment->id : ''}}" id="delete_segment"> <span>Delete</span></button>
    
      
    </div>
    </div>
  </div>
</div>


<!-- Modal for export -->
<div id="modal-export" class="modal fade custom-modal-tabs show" data-backdrop="static" aria-modal="true">
  <div class="modal-dialog" role="document" id="append_popup">
    {!!view('segments.exports.export_popup', $modalData)->render()!!}
  </div>
</div>



<!-- Modal for delete segment -->
 <div id="modal-delete-product" class="modal fade custom-modal custom-modal-tabs show " aria-modal="true">
      <div class="modal-dialog" role="document">
          <div class="modal-content">
              <div class="modal-header custom-modal__image">   
               <img class="deliveryicon mt-0" src="{!! asset('images/Error.png') !!}" alt="" style="display: block;z-index:1">             
                  <button type="button" class="close custom-modal__close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true" class="ua-icon-modal-close"></span>
                  </button>
              </div>
              <div class="modal-body text-center">
                 
                  <div class="order-delivery-content">
                      <h2 class="order-delivery-title">{{__("messages.customer_detail.Are you sure")}}</h2>
                  </div>
              </div>
              <div class="modal-footer" style="border:none">
                 <div class="custom-modal__buttons">
                  <button type="button" class="btn btn-danger" data-id=""  id="delete_product_btn">{{__("messages.customer_detail.Confirm")}}</button>
                  <button type="button" class="btn btn-secondary " data-dismiss="modal" aria-label="Close">
                      {{__("messages.common.cancel")}}
                  </button>
                </div>
              </div>
          </div>
      </div>
  </div>
<!-- End modal for delete segment -->

</div>
