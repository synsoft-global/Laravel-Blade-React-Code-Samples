<?php
/**
 * Description: Handle Product module functions.
 * Version: 1.0.0
 * Author: Synsoft Global
 * Author URI: https://www.synsoftglobal.com/ 
 */

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\Product;
use App\Model\Category;
use App\Model\ProductGroup;
use App\Model\ProductGroupItem;
use App\Model\ProductMeta;
use App\Model\ProductCategory;
use App\Model\Order;
use App\Model\OrderItem;
use App\Model\RefundItem;
use App\Model\Customer;
use App\Model\ProductTag;
use DB, Session;
use DateTime;
use DatePeriod;
use DateInterval;
Use App\Model\Segment;
use Maatwebsite\Excel\Facades\Excel;
Use App\Helpers\ProductHelper;
use Mail,Auth, Config;
use App\Model\storeData;
use App\Exports\Export_Products;
use App\Exports\ExportProducts;
use App\Exports\ExportProductsChart;
use Carbon\CarbonPeriod;
use App\Http\Controllers\Api\SyncDatabaseController;
use Illuminate\Support\Facades\Validator; 

// Define Product Controller class 
class ProductController extends Controller
{
  protected $model;
	public function __construct(){
    $this->model = new Product();  

  }  

    /**
     * Product List
     *
     * @param Request $request     
     *
     */   
    public function list(Request $request){
      // Get products list
      try{           
        $data['products'] = '';
        $action = $request->input('saved-segment');
        $data['segment']='';       
        $data['segment_btn_text']=__('messages.common.save_segment');     
        $data['edit_segment']=false;
        $data['is_default_segment']=false;
        // Check segment action.
         $data['segment_name_warning'] = '';
        if(!empty($action)){
          $segment=Segment::find($action);  
          if(isset($segment) && $segment->module_type=='products'){

            if(Auth::user()->role->slug =='producer'){
              if(($segment->is_public == 1) || (Auth::user()->id ==$segment->user_id)){
                 $data['segment'] =$segment;                
                 $data['segment_name_warning'] = 'Segment '.$segment->segment_name.' applied';
                 $data['edit_segment']=true;   
                 $data['segment_btn_text']='Update';
                 if($segment->default){
                    $data['edit_segment']=false;
                    $data['is_default_segment']=true;
                    $data['segment_btn_text']= __('messages.products.save_as_custom_segments');
                 } 
              }else{
                Session::flash('errormessage', __('messages.products.segment_not_allowed'));
                return redirect('/products');
                exit("here");
              }
            }else{
             $data['segment'] =$segment;                
             $data['segment_name_warning'] = 'Segment '.$segment->segment_name.' applied';
             $data['edit_segment']=true;   
             $data['segment_btn_text']='Update';
             if($segment->default){
                $data['edit_segment']=false;
                $data['is_default_segment']=true;
                $data['segment_btn_text']=__('messages.products.save_as_custom_segments');
             } 
            } 
          }else{
            // If segment not exists or deleted by admin redirect to product page.
            Session::flash('errormessage', __('messages.products.segment_not_allowed'));
            return redirect('/products');
            exit("here");
          }
        }
        // load product meta key
        $products_meta = storeData::whereIn('option_name',array('products_meta'))->first();
        $data['modalData']['customerMeta']=[];
        if(isset($products_meta) && !empty($products_meta)){
          $data['modalData']['customerMeta']=json_decode($products_meta->option_value);
        }
       
        // Get all segment list
        $user_id=Auth::user()->id;
        $data['segments'] =Segment::query()->where('module_type','products')
         ->where(function($query) use($user_id) {               
              return $query->where('user_id', $user_id)                   
                  ->orWhere('is_public', '=', 1);
          })
        ->orderBy('created_at','desc')->take(20)->get(); 
      
        $data['filter_options']= ProductHelper::get_filters_dropdown(); // Get customer 

        // Get column for export settings
        $columns = get_product_export_heading();
        $data['modalData']['customise_columns'] = array();
        foreach ($columns as $k => $v) {
            $data['modalData']['customise_columns'][] = $k;
        }
      }catch(Exception $e){
         return redirect('/');
         die();
      }

	    return view('products.list')->with($data);
    }
    

   
    /**
     * get group detail view.  
     *
     * @param Request $request     
     */
    public function groupDetail(Request $request){
      if(!empty($request->segment(2)))
      {
        try{

          if(!empty($request->input('chartby'))){
            session(['chartby' =>  $request->input('chartby')]);    
          }

          $group_id=$request->segment(2);
          $query= ProductGroup::query()
          ->join('product_group_items', 'product_group_items.product_group_id', '=', 'product_groups.id', 'right')
          ->join('products', 'products.id', '=', 'product_group_items.item_id', 'left')
          ->where('product_group_items.product_group_id',$group_id)
          ->groupBy('product_groups.id')
          ->select('product_groups.*','products.images',
            DB::raw('IF(products.stock_quantity IS NULL, 0, SUM(distinct products.stock_quantity)) as stock_quantity'),
            DB::raw('IF(products.stock_quantity IS NULL, 0, AVG(distinct products.stock_quantity)) as avg_stock_quantity'),
            DB::raw('IF(product_group_items.item_id IS NULL, 0, count(distinct products.id)) as number_of_products'));

          $current_role=Auth::user()->role->slug;
          $cat=[];
          if($current_role=='producer'){
            $users_categories=Auth::user()->users_categories;
            if(!empty($users_categories)){
              $cat=json_decode($users_categories,true);
            }
          }

          if($current_role=='producer'){
              $query->join('product_categories', 'product_categories.product_id', '=', 'products.id','left');
              $query->whereIn('product_categories.category_id',$cat);
          }  
          $group['groups'] =$query->get();

          global $start_date,$end_date;
          if (!empty(session('product_range_picker'))) {
            $date_exp = explode('-', session('product_range_picker'));

            $start_date=date('Y-m-d',strtotime($date_exp[0]));
            $end_date=date('Y-m-d',strtotime($date_exp[1]));
          }else{
            $date=session('store_start_date');
            $start_date=date('Y-m-d',strtotime($date));
            $end_date=date('Y-m-d');

            $start_date_f=date('m/d/Y',strtotime($date));
            $end_date_f=date('m/d/Y');
            session(['product_range_picker' =>  $start_date_f.' - '.$end_date_f]);           
          } 

          global $start_date_compare, $end_date_compare;
          if (!empty(session('compare_range_picker'))) {
            $date_exp = explode('-', session('compare_range_picker'));

            $start_date_compare=date('Y-m-d',strtotime($date_exp[0]));
            $end_date_compare=date('Y-m-d',strtotime($date_exp[1]));   
          }else{
            $date=session('store_start_date');
            $start_date_compare=date('Y-m-d',strtotime($date));
            $end_date_compare=date('Y-m-d');

            $start_date_compare_f=date('m/d/Y',strtotime($date));
            $end_date_compare_f=date('m/d/Y');
            session(['compare_range_picker' =>  $start_date_compare_f.' - '.$end_date_compare_f]);
          }

          $start_date=$start_date." 00:00:00";
          $end_date=$end_date." 23:59:59"; 
          $start_date_compare=$start_date_compare." 00:00:00";
          $end_date_compare=$end_date_compare." 23:59:59";

          if(!empty($group['groups']) && count($group['groups'])>0) {
         
            /*
            * get product list for groups.
            * @param group_id int
            */ 
            $product_response=ProductHelper::get_products_of_groups($group_id);
            if($request->input('orderbyValP')){
              session(['orderbyValDetail' =>  $request->input('orderbyValP')]);
              $groupBy = $request->input('orderbyValP');
            }else{
              $groupBy = "billing_country";
              session(['orderbyValDetail' =>  $groupBy]);
            }

            if($request->input('groupbyVal')) {
              session(['orderbyValDetailS' =>  $request->input('groupbyVal')]);
              $sub_groupBy = $request->input('groupbyVal');
            }else{
              $sub_groupBy = '';
              session(['orderbyValDetailS' =>  '']);
            }

            /*
            * get net details of order placed using a  product.
            * @param product_id int
            */ 
            $net_data=ProductHelper::get_net_details_of_product_sales($product_response['product_ids'], $start_date, $end_date, $start_date_compare, $end_date_compare, "group");

            /*
            * get order details of order placed using a  product grouped by provided string.
            * @param product_id int, groupBy string
            */ 
            $order_placed_data_grouped=ProductHelper::get_details_of_order_placed_product_grouped($product_response['product_ids'], $groupBy, $start_date, $end_date, $start_date_compare, $end_date_compare, $sub_groupBy, 'group');

            /*
            * get sales details for chart.
            * @param product_id int, groupBy string
            */ 

            $chartby=session('chartby');
            $chart_data=ProductHelper::get_details_for_sales_chart_of_product($product_response['product_ids'], $chartby, $start_date, $end_date, 'group');
            session(['chartby' =>  $chart_data['chart']['unit']]); 
            $data=array_merge($group, $product_response, $chart_data, $net_data, $order_placed_data_grouped);
          }else{
            $data=array();
          }
          

          if ($data && $data['groups']) {            
            if(isset($_POST) && count($_POST)>0){
              $result = view("groups.groups",$data)->render();
              $view = view("products.ajax.ajaxfilter",$data['order_placed_data'])->render();
              $chart = view("products.ajax.ajaxchart",$data['chart'])->render();
              return response()->json(['html'=>$view, 'chart'=> $data['chart']['chart'], 'label'=>$data['chart']['label'], 'result'=>$result,'unit'=>$chart_data['chart']['unit']]);
            }else{
              return view("groups.groups",$data)->render();
            }
          } else {
            if(isset($_POST)  && count($_POST)>0){
              $result = view("groups.groups",$data)->render();
              $view = view("products.ajax.ajaxfilter",$data['order_placed_data'])->render();
              $chart = view("products.ajax.ajaxchart",$data['chart'])->render();
              return response()->json(['html'=>$view, 'chart'=> $data['chart']['chart'], 'label'=>$data['chart']['label'], 'result'=>$result,'unit'=>$chart_data['chart']['unit']]);
            }else{
              Session::flash('errormessage', __('messages.products.no_results_could_be_displayed'));
              return redirect('product-groups');
            }           
          }
        }catch(Exception $e){
          return redirect('/groups');
          die();
        } 
      }
    }

    /*
    * @return Export product order detail data of sales grouped by
    *
    * @param Request $request (form data)
    */
    public function exportProductOrderDetail(Request $request) {
      try{
        $product_id = $request->input('productId');

        if($request && !empty($request->input('orderbyValP'))){
          $groupBy = $request->input('orderbyValP');
        }else{
          $groupBy = "billing_country";
        }

        $sub_group = explode('_', $groupBy)[0];
        $query_order = OrderItem::query()
        ->join('refund_items', function($join){
          $join->on('refund_items.product_id', '=',DB::raw('order_items.product_id')) 
          ->on('refund_items.order_id','=', DB::raw('order_items.order_id'));
        },'','','left')->join("orders",function($join){   
              $join->on("orders.id","=","order_items.order_id")
                ->whereIn('status',['completed','processing','on-hold']);
       },'','','inner');

        if($request->input('export_for') == "category") {
          $query_order->whereIn('order_items.product_id',  json_decode($product_id));
        }else{
          $query_order->where('order_items.product_id', '=', $product_id);
        }

        if($request->input('groupbyVal')  && $request->input('groupbyVal')!='' ) {
          $sub_groupBy = $request->input('groupbyVal');
        }else{
          $sub_groupBy = '';
        }

        if($sub_groupBy && $sub_groupBy != "''") {
          $query_order->where('orders.'.$sub_group.'_country', '=', $sub_groupBy);
        }

        $orders = $query_order->groupBy('orders.'.$groupBy)
          ->select('orders.id AS order_id',
            'orders.'.$groupBy,
            DB::raw('IF(order_items.quantity IS NULL, 0, SUM(order_items.quantity)) + IF(refund_items.quantity IS NULL, 0, SUM(refund_items.quantity)) as net_sold'),
            DB::raw('IF(order_items.total IS NULL, 0, SUM(order_items.total)) + IF(refund_items.total IS NULL, 0, SUM(refund_items.total)) as net_revenue'), 
            DB::raw('IF(order_items.quantity IS NULL, 0, SUM(order_items.quantity)) as gross_sold'),  
            DB::raw('IF(order_items.total IS NULL, 0, SUM(order_items.total)) as gross_revenue'), 
            DB::raw('IF(order_items.id IS NULL, 0, count(order_items.id)) + IF(refund_items.id IS NULL, 0, count(refund_items.id)) as net_orders'),
            DB::raw('IF(refund_items.id IS NULL, 0, count(refund_items.id)) as refunds_count'), 
            DB::raw('IF(refund_items.id IS NULL, 0, SUM(refund_items.total)) as refunds')
          )->get();

        $data['products'] = $orders;
        $name = 'product-'.$product_id.'grouped-by-'.$groupBy.'-data-'.time().'.csv';
        return Excel::download(new ExportProducts($data,$groupBy), $name);
      }catch(Exception $e){
        return redirect('/');
        die();
      }
    }

    /**
    * @return Export product chart detail data of sales grouped by
    *
    * @param Request $request (form data)
    */
    public function exportProductChartDetail(Request $request) {

     
      try{
        $product_id = $request->input('product_id');
        if(!empty($request->input('range_picker'))){
          session(['product_range_picker' =>  $request->input('range_picker')]);
        }

        global $start_date,$end_date;
        if (!empty(session('product_range_picker'))) {
            $date_exp = explode('-', session('product_range_picker'));

            $start_date=date('Y-m-d',strtotime($date_exp[0]));
            $end_date=date('Y-m-d',strtotime($date_exp[1]));         
           
         }else{
            $date=session('store_start_date');
            $start_date=date('Y-m-d',strtotime($date));
            $end_date=date('Y-m-d');

            $start_date_f=date('m/d/Y',strtotime($date));
            $end_date_f=date('m/d/Y');
            session(['product_range_picker' =>  $start_date_f.' - '.$end_date_f]);           
        } 

        $start_date=$start_date." 00:00:00";
        $end_date=$end_date." 23:59:59"; 

        /*
        * get sales details for chart.
        * @param product_id int, groupBy string
        */ 
        $chartby=session('chartby');
        $chart_data= ProductHelper::get_details_for_sales_chart_of_product($product_id, $chartby, $start_date, $end_date, $request->input('export_for'));


        $data['chart'] = (object)((object)($chart_data['chart']['chart']));

        $name = 'product-sales-report-'.time().'.csv';
        return Excel::download(new ExportProductsChart($chart_data['chart']['chart']),$name);
      }catch(Exception $e){
        return redirect('/');
        die();
      }
    }


     /**
    * @return product html filter
    */
    public function getProductFilterHtml(Request $request){
      try{
        $condition = $request->input('condition');  
        $filter = $request->input('filter');  
        $rowid = $request->input('rowid');  
        $count = $request->input('count');  
        if(!empty($condition)){
           $filters_options=ProductHelper::get_filters_options();
           $data['condition']=$filters_options['segment_product_filter'][$condition];
           $data['count']=$rowid;
           $view = view("products.ajax.filterHtml",$data)->render();         
           return response()->json(['html'=>$view,'filter'=>$filter,'rowid'=>$rowid,'count'=>$count]);
        }
      }catch(Exception $e){
        return redirect('/');
        die();
      }
    } 

     /**
    * @return product html filter
    */
    public function getProductInitialFilterHtml(Request $request){
      try{ 
        $count = $request->input('count');  
        $input_option = $request->input('input_option');  
        $edit = $request->input('edit');  
        $data['count']=$count ;
        $data['edit']=$edit ;
        $data['filter_options']=ProductHelper::get_filters_dropdown();
        $data['input_option']=$input_option;

        if($edit){
        $view = view("products.ajax.initialFilterHtmlEdit",$data)->render();  
        }else{
        $view = view("products.ajax.initialFilterHtml",$data)->render();    
        }

        return response()->json(['html'=>$view,'count'=>$count, 'input_option'=>$input_option]);
      }catch(Exception $e){
        return redirect('/');
        die();
      }
    }

     /**
    * @return segments html filter
    */
    public function segmentsFilterHtml(){
      try{
        $data['filter_options'] = get_filters_dropdown($_POST['segment_filter']);
        $view = view("products.ajax.ajaxfilter",$data)->render();
        return response()->json(['html'=>$view]);
      }catch(Exception $e){
        return redirect('/');
        die();
      }
    }

    /* @param $request post requst object
    * @return customer html filter
    */
    public function dropdownProduct(Request $request){
      try{
        $search = $request->input('q');
        $productids = $request->input('productids');

        $query = Product::query()->where('products.name', 'like', '%' . $search . '%');
        if(isset($productids) && !empty($productids) && count($productids)>0){
           $query->whereNotIn('products.id',$productids);
        }

        $query->select('products.id','products.name as text')
        ->join('order_items', 'order_items.product_id', '=', 'products.id')
        ->groupBy('products.id')
        ->orderBy('date_modified','desc');
       $data= $query->take(50)->get();
        return response()->json($data);
      }catch(Exception $e){
        return redirect('/');
        die();
      }             
    } 

     /* @param $request post requst object
    * @return customer html filter
    */
    public function get_product_tag_list(Request $request){
      try{
        $search = $request->input('term');
        $productids = $request->input('productids');

        $query = ProductTag::query()->where('name', 'like', '%' . $search . '%');
        if(isset($productids) && !empty($productids) && count($productids)>0){
           $query->whereNotIn('products.id',$productids);
        }

        $query->select('id','name as value')      
        ->orderBy('name','desc');
       $data= $query->take(50)->get();
        return response()->json($data);
      }catch(Exception $e){
        return redirect('/');
        die();
      }             
    } 


    /* @param $request post requst object
    * @return customer html filter
    */
    public function dropdownReferer(Request $request){
      try{
        $referer = storeData::whereIn('option_name',array('_metorik_referer'))->first(); 
        $meta=[];
        if(isset($referer)){
          $meta=json_decode($referer->option_value);  
        }   
        return response()->json($meta); 
      }catch(Exception $e){
        return redirect('/');
        die();
      }           
    } 

     /* @param $request post requst object
    * @return customer html filter
    */
    public function dropdownSegmentCustomer(Request $request){
      try{
        $search = $request->input('q');
        $productids = $request->input('productids');
        $query =  Segment::query()->where('segment_name', 'like', '%' . $search . '%');
        if(isset($productids) && !empty($productids) && count($productids)>0){
           $query->whereNotIn('id',$productids);
        }
         $query->where('module_type', 'customers')
        ->select('id','segment_name as text')
        ->orderBy('created_at','desc');
       $data= $query->get();
        return response()->json($data);  
      }catch(Exception $e){
        return redirect('/');
        die();
      }          
    } 

     /* @param $request post requst object
    * @return customer html filter
    */
    public function dropdownProductGroup(Request $request){
      try{
        $search = $request->input('q');
         $productids = $request->input('productids');

         $query = ProductGroup::query()->where('name', 'like', '%' . $search . '%');
          if(isset($productids) && !empty($productids) && count($productids)>0){
             $query->whereNotIn('id',$productids);
          }
        $query->select('id','name as text')
        ->orderBy('created_at','desc');
        $data= $query->take(50)->get();
        return response()->json($data);           
      }catch(Exception $e){
        return redirect('/');
        die();
      } 
    } 

    /* @param $request post requst object
    * @return customer html filter
    */
    public function dropdownCustomerCategory(Request $request){
      try{
        $search = $request->input('q');
        $productids = $request->input('productids');

        $query = Category::query()->where('name', 'like', '%' . $search . '%');
        if(isset($productids) && !empty($productids) && count($productids)>0){
           $query->whereNotIn('id',$productids);
        }

        $current_role=Auth::user()->role->slug;
        $cat=[];
        if($current_role=='producer'){
          $users_categories=Auth::user()->users_categories;
          if(!empty($users_categories)){
            $cat=json_decode($users_categories,true);
          }
        }

        if($current_role=='producer'){
          $query->whereIn('id',$cat);
        }  

       $query->select('id','name as text')             
        ->orderBy('name','asc');

       $data= $query->take(50)->get();
        return response()->json($data);
      }catch(Exception $e){
        return redirect('/');
        die();
      }            
    } 


     /* @param $request post requst object
    * @return customer html filter
    */
    public function dropdownCustomer(Request $request){
      try{
        $search = $request->input('q');
          $productids = $request->input('productids');

        $query = Product::query()->where('products.name', 'like', '%' . $search . '%');
        if(isset($productids) && !empty($productids) && count($productids)>0){
           $query->whereNotIn('products.id',$productids);
        }

        $current_role=Auth::user()->role->slug;
        $cat=[];
        if($current_role=='producer'){
          $users_categories=Auth::user()->users_categories;
          if(!empty($users_categories)){
            $cat=json_decode($users_categories,true);
          }
        }
        
        $query->select('products.id','products.name as text')
          ->join('order_items', 'order_items.product_id', '=', 'products.id','left');
          
          
        if($current_role=='producer'){
          $query->join('product_categories', 'product_categories.product_id', '=', 'products.id','left');
          $query->whereIn('product_categories.category_id',$cat);
        }  


        $data= $query->groupBy('products.id')
            ->orderBy('date_modified','desc')
            ->take(50)->get();
        return response()->json($data);
      }catch(Exception $e){
        return redirect('/');
        die();
      }             
    } 


     /* @param $request post requst object
    * @return customer html filter
    */
    public function dropdownCustomerList(Request $request){ 
      try{
        $search = $request->input('q');
         $productids = $request->input('productids');

        $query = Customer::query()->where(DB::raw("concat(first_name,' ',last_name)"), 'like', '%' . $search . '%');
        if(isset($productids) && !empty($productids) && count($productids)>0){
           $query->whereNotIn('wp_id',$productids);
        }

       $query->select('wp_id',DB::raw("concat(first_name,' ',last_name)  as text"));            
       $query->orderBy('updated_at','desc');

       $data= $query->take(50)->get();
       $encoded_data=(json_encode($data));
       $data=str_replace('"wp_id"', '"id"', $encoded_data);
       $data=(json_decode($data));
       
        return response()->json($data);
      }catch(Exception $e){
        return redirect('/');
        die();
      }            
    } 

     /* @param $request post requst object
    * @return customer html filter
    */
    public function list_html(Request $request){
       return view('products.list_html');          
       
    }

      /* @param $request post requst object
    * @return customer html filter
    */
    public function profile_page(Request $request){
       return view('products.profile_html');          
       
    } 

    /* @param $request post requst object
    * @return customer html filter
    */
    public function product_group_list(Request $request){
       return view('products.product_group_list');          
       
    } 

   /* @param $request post requst object
    * @return customer html filter
    */
    public function order_profile_page(Request $request){
       return view('products.order_profile_page');          
       
    } 

   /* @param $request post requst object
    * @return customer html filter
    */
    public function reports_revenue_list(Request $request){
       return view('products.reports_revenue_list');          
       
    } 

    /* @param $request post requst object
    * @return customer html filter
    */
    public function reports_orders_list(Request $request){
       return view('products.reports_orders_list');          
       
    } 
    /* @param $request post requst object
    * @return customer html filter
    */
    public function reports_refunds_list(Request $request){
       return view('products.reports_refunds_list');          
       
    } 
    /* @param $request post requst object
    * @return customer html filter
    */
    public function reports_customers_list(Request $request){
       return view('products.reports_customers_list');          
       
    } 
    /* @param $request post requst object
    * @return customer html filter
    */
    public function dashboard_html(Request $request){
       return view('products.dashboard_html');          
       
    } 


      /**
     * Edit customer
     *
     * @param $request
     * @return  json object
     */
    public function edit_product(Request $request){
        try{
            $data['data']=array();
            $data['categories']=[]; 
            $data['images']=[]; 
            $data['tags']=[]; 
            if(!empty($request->id)){
               $data['data'] = Product::findOrfail($request->id);
              
               if(!empty($data['data'])){
                  $cat=$data['data']->product_category;
                  if(!empty( $cat) && count( $cat) > 0){
                    foreach ($cat as $key => $value) {
                     $data['categories'][]=$value->category_id;
                    }
                    
                  }
               }

                if(!empty($data['data']->image_list)){
                    $data['image_list']=json_decode($data['data']->image_list);
                    
                }

                 if(!empty($data['data']->tags)){
                    $data['tags']=json_decode($data['data']->tags);
                    
                } 

                if(!empty($data['data']->date_on_sale_from)){
                    $data['data']->date_on_sale_from=date("Y-m-d",strtotime($data['data']->date_on_sale_from));
                }

                if(!empty($data['data']->date_on_sale_to)){
                    $data['data']->date_on_sale_to=date("Y-m-d",strtotime($data['data']->date_on_sale_to));
                }
            }
            return view('products.ajax.addProductModal', $data);
        }catch(Exception $e){
            return redirect('/products');
        }
    }

     /**
     * Delete user
     *
     * @param $request
     * @return  json object
     */
    public function delete_product(Request $request){
        try{
           $sync= new SyncDatabaseController();
            $response= $sync->delete_product_wp($request->id);
            if(!empty($response) && $response['status']){
                  Product::where('id', $request->id)->delete();
              return response()->json(['status'=>1, 'msg'=>__('messages.products.product_deleted_successfully')]);
            }else{
               return response()->json(['status'=>0, 'msg'=>$response['msg']]);
            }
            
        }catch(Exception $e){
            return response()->json(['status'=>0, 'msg'=>__('messages.common.something_went_wrong')]);
        }

    } 

    
     public function validate_product(Request $request){
       try{

          $products=ProductHelper::prepare_product_data($request);           
         
          $sync= new SyncDatabaseController();
          $response= $sync->add_product_tag_wp($products); 
          if($response){
            if($request->input('id')){
                return response()->json(['status'=>1, 'msg'=>__('messages.products.product_updated_successfully')]);

            }else{              
                return response()->json(['status'=>1, 'msg'=>__('messages.products.product_added_successfully')]);                
            }
          }else{
             return response()->json(['status'=>0, 'msg'=>__('messages.common.something_went_wrong')]);
          }
         
        }catch(Exception $e){
            return response()->json(['status'=>0, 'msg'=>__('messages.common.something_went_wrong')]);
        }
      }
    

    /**
    * Add user in user management
    *
    *@param $request
    *
    * @return status
    */
    public function add_product_create(Request $request){
      try{

        $request->tasgInput=json_decode($request->tasgInput);
        $products=ProductHelper::prepare_product_data($request);
        $request->tasgInputCreate=json_decode($request->tasgInputCreate);

        for ($x = 0; $x < $request->TotalImages; $x++) {
          if ($request->hasFile('images'.$x)) {                  
            //Image Validation Rule    
            $rules1 = ['images'.$x =>'mimes:jpeg,jpg,png,gif'];
            $validator = Validator::make($request->all(), $rules1);
            //Not valid image extention
            if ($validator->fails()) {
              return response()->json(["message" => __('messages.social_feed.image_type_must_be'), 'type' => 'error']);
            }
          }
        }

        if($request->TotalImages > 0)
        {
              // Declare array varibale to store images data
              $imagenames = array();             
              if (!file_exists(public_path('uploads/products/'))) {
                  mkdir(public_path('uploads/products'));
              }
              for ($x = 0; $x < $request->TotalImages; $x++) {
                if ($request->hasFile('images'.$x)) {         

                  $file      = $request->file('images'.$x);
                  $filename  = $file->getClientOriginalName();
                  $extension = $file->getClientOriginalExtension();
                  $picture   =$request->post('images_name'.$x);    ;                 
                  $img = asset('uploads/products').'/'.$picture;
                  $file->move(public_path('uploads/products'), $picture);   
                  array_push($products['images'],array("src"=>asset('/uploads/products/'.$picture)));                 
                 
                }else{                
                  if(!empty($request->input('text_img'.$x))){
                    array_push($products['images'],array("id"=>$request->input('text_img_id'.$x),"src"=>$request->input('text_img'.$x))); 
                  }
                }
              }                 
           
         }
       
          if(!empty($request->tasgInputCreate) && count($request->tasgInputCreate) > 0){
             $sync= new SyncDatabaseController();
             $response= $sync->add_product_tag_wp($request->tasgInputCreate);
           
             if(!empty($response->create)){
                foreach ($response->create as $key => $value) {
                    array_push($products['tags'],array('id'=>$value->id));
                }
             }          
          }  
         
     
           $categoriesList=[];
          foreach ($products['categories'] as $key => $value) {
            $categoriesList[]=array("id"=>$value);
          }  
          $products['categories']= $categoriesList;  
          $sync= new SyncDatabaseController();

          $response= $sync->add_product_wp($products); 

          if($response){
            if($request->input('id')){
                return response()->json(['status'=>1, 'msg'=>__('messages.products.product_updated_successfully'), 'id' => isset($response->id)?$response->id:0]);

            }else{              
                return response()->json(['status'=>1, 'msg'=>__('messages.products.product_added_successfully') , 'id' => isset($response->id)?$response->id:0]);                
            }
          }else{
             return response()->json(['status'=>0, 'msg'=>__('messages.common.something_went_wrong')]);
          }
         
        }catch(Exception $e){
            return response()->json(['status'=>0, 'msg'=>__('messages.common.something_went_wrong')]);
        }
    }

}
