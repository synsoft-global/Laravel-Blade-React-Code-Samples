<?php
/**
 * Description: Syn current database to remote database.
 * Version: 1.0.0
 * Author: Synsoft Global
 * Author URI: https://www.synsoftglobal.com/
 *
 */
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller; 
use Illuminate\Http\Request;
use Automattic\WooCommerce\Client;

Use App\Parser\Products;
Use App\Parser\Categories;
Use App\Parser\Customers;
Use App\Parser\Orders;
Use App\Parser\Coupons;
Use App\Parser\Tags;
use App\Model\storeData;

Use App\Model\Customer;
Use App\Model\Category;
Use App\Model\Order;
Use App\Model\Product;
Use App\Model\Coupon;
Use App\Model\ProductTag;

use App\Model\DataMigration;

use App\Http\Controllers\API\Product\CouponController; 
use App\Http\Controllers\API\Product\CustomerController; 
use App\Http\Controllers\API\Product\OrderController; 
use App\Http\Controllers\API\Product\ProductController; 
use App\Http\Controllers\web\ExportsController;

use Eloquent, DB, Log;

class SyncDatabaseController extends Controller
{
    private $woocommerce;
    protected $setting = '';
    protected $data_setting = array(
      'store_name'=>'',
      'store_url'=>'',
      'sync_process'=>'',
      'sync_process_status'=>'',
      'private_key'=>'',
      'secret_key'=>'',
      'store_currency'=>'',
      'check_failed_pending'=>'',
      'admin_slug_url'=>'',      
      'last_run_sync'=>'',      
      'download_limit'=>''
  );
    /** 
     *
     * @param $woocommerce
     */
    public function __construct()
    {
        $this->setting=storeData::whereIn('option_name',array('store_name','store_url','private_key','secret_key'))->get();
         $data['setting']=array();
          foreach($this->setting as $setting){
            $data['setting'][$setting->option_name]=$setting->option_value;
            $this->data_setting[$setting->option_name]=$setting->option_value;
          }

        if(count($data['setting']) > 0){
           $this->woocommerce = new Client($data['setting']['store_url'], $data['setting']['private_key'], $data['setting']['secret_key'], [
                'version' => 'wc/v3',
                'timeout' => 50,
                'verify_ssl' => false
            ]); 
        }

        
    }

    /**
     * Import process for all resources.
     *
     * @param Request $request    
     */
    //public function index(Request $request)
    public function index() {
                    
      try{
             $moduleName=array('customers','orders','categories','products','coupons','tags');
             $processing_data=DataMigration::whereIn('status',['new','failed'])->select('name','total_count')->get()->toArray();

             $action = '';  
             $parameter=array("per_page"=>"50","page"=>1);    

                
           
            if(!empty($action) && in_array($action,  $moduleName)){
              $this->{"process_$action"}($parameter);
             }else{
                foreach ($processing_data as $key => $value) {                        
                     DataMigration::updateorcreate([
                      'name' =>$value['name']                        
                      ],[
                          'name' => $value['name'] ,                                
                          'status' => 'processing'                           
                      ]); 
                      $total_count=$value['total_count']; 

                     if($value['name']=='customers'){
                       $count=Customer::count();  
                        if($total_count > 0 && $count > $parameter['per_page']){                          
                          $parameter['page']= (floor($count/$parameter['per_page']))-1;
                        }                   

                     }else  if($value['name']=='categories'){
                       $count=Category::count();  
                        if($total_count > 0 && $count > $parameter['per_page']){                          
                          $parameter['page']= (floor($count/$parameter['per_page']))-1;
                        }                   

                     }else if($value['name']=='orders'){
                       $count=Order::count();   
                        if($total_count > 0 && $count > $parameter['per_page']){                          
                          $parameter['page']= (floor($count/$parameter['per_page']))-1;
                        }                    

                     }else if($value['name']=='products'){
                       $count=Product::count();
                        if($total_count > 0 && $count > $parameter['per_page']){                          
                          $parameter['page']= (floor($count/$parameter['per_page']))-1;
                        }
                       

                     }else if($value['name']=='coupons'){
                       $count=Coupon::count();
                        if($total_count > 0 && $count > $parameter['per_page']){                          
                          $parameter['page']= (floor($count/$parameter['per_page']))-1;
                        }
                       

                    }else if($value['name']=='tags'){
                       $count=ProductTag::count();
                        if($total_count > 0 && $count > $parameter['per_page']){                          
                          $parameter['page']= (floor($count/$parameter['per_page']))-1;
                        }
                       

                     }

                     if($parameter['page'] < 1){
                       $parameter['page']=1;
                     }
                     $process_name=$value['name'];                     
                    $this->{"process_$process_name"}($parameter);          
                }    
            } 

           ExportsController::get_value_groupby();  
            
              
        } catch (HttpClientException $e) {         
             storeData::updateorcreate([
                        'option_name' => 'last_run_sync'                     
                    ],[
                        'option_name' => 'last_run_sync',
                        'option_value' => now()                   
                    ]); 
             storeData::updateorcreate([
                  'option_name' => 'sync_process_status'                     
                ],[
                    'option_name' => 'sync_process_status',
                    'option_value' => 'failed'                   
                ]); 

             $msg=json_encode(['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'Previous'=>$e->getPrevious()]);

              sendErrorMail(array('msg'=>$msg,'subject'=>MIGRATION_JOB_FAILED));
              
              Log::critical($msg);

        } catch (\Exception $e) {         
               storeData::updateorcreate([
                        'option_name' => 'last_run_sync'                     
                    ],[
                        'option_name' => 'last_run_sync',
                        'option_value' => now()                   
                    ]); 
               storeData::updateorcreate([
                  'option_name' => 'sync_process_status'                     
                ],[
                    'option_name' => 'sync_process_status',
                    'option_value' => 'failed'                   
                ]); 
              $msg=json_encode(['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'Previous'=>$e->getPrevious()]);

              sendErrorMail(array('msg'=>$msg,'subject'=>MIGRATION_JOB_FAILED));
              
              Log::critical($msg);
            
        }    
    }

     

     /**
     * Process product response.
     *
     * @param Array $parameter    
     */
    public function process_products($parameter){
      $user = auth()->user();      
       try {
            $results = $this->woocommerce->get('products',$parameter);              
            
            
            if(!empty($results) && count($results) > 0){
                foreach($results as $content){             
                    Eloquent::unguard();                   
                    $req_dump='';
                    $req_dump .= print_r($content, TRUE);
                    $fp = fopen('request.log', 'a');
                    fwrite($fp, $req_dump);
                    fclose($fp); 
                    $pid=Products::create_product($content);                    
                    storeData::updateorcreate([
                        'option_name' => 'last_run_sync'                     
                    ],[
                        'option_name' => 'last_run_sync',
                        'option_value' => now()                   
                    ]);                        
                }
                $parameter['page']=$parameter['page']+1;
                $this->process_products($parameter);
            }else{               
                 DataMigration::updateorcreate([
                      'name' => 'products'                         
                  ],[
                      'name' => 'products',                                
                      'status' => 'completed'                           
                  ]); 
               $msg=json_encode(['message'=>'']);

              sendErrorMail(array('msg'=>$msg,'subject'=>MIGRATION_JOB_PRODUCTS_FAILED));
              
              Log::critical($msg);
            }            
         } catch (\Exception $e) {            
          
             DataMigration::updateorcreate([
                  'name' => 'products'                         
              ],[
                  'name' => 'products',                                
                  'status' => 'failed'                           
              ]);
            
             $msg=json_encode(['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'Previous'=>$e->getPrevious()]);

              sendErrorMail(array('msg'=>$msg,'subject'=>MIGRATION_JOB_PRODUCTS_FAILED));
              
              Log::critical($msg);
 

           

            
        } 
    }

     /**
     * Process product response.
     *
     * @param Array $parameter    
     */
    public function process_categories($parameter){
      $user = auth()->user();      
       try {
            $results = $this->woocommerce->get('products/categories',$parameter);              
            
            
            if(!empty($results) && count($results) > 0){
                foreach($results as $content){             
                    Eloquent::unguard();                   
                    $req_dump='';
                    $req_dump .= print_r($content, TRUE);
                    $fp = fopen('request.log', 'a');
                    fwrite($fp, $req_dump);
                    fclose($fp); 
                    $pid=Categories::create_category($content);                    
                    storeData::updateorcreate([
                        'option_name' => 'last_run_sync'                     
                    ],[
                        'option_name' => 'last_run_sync',
                        'option_value' => now()                   
                    ]);                        
                }
                $parameter['page']=$parameter['page']+1;
                $this->process_categories($parameter);
            }else{               
                 DataMigration::updateorcreate([
                      'name' => 'categories'                         
                  ],[
                      'name' => 'categories',                                
                      'status' => 'completed'                           
                  ]); 
               $msg=json_encode(['message'=>'']);

              sendErrorMail(array('msg'=>$msg,'subject'=>MIGRATION_JOB_PRODUCTS_FAILED));
              
              Log::critical($msg);
            }            
         } catch (\Exception $e) {            
          
             DataMigration::updateorcreate([
                  'name' => 'categories'                         
              ],[
                  'name' => 'categories',                                
                  'status' => 'failed'                           
              ]);
            
             $msg=json_encode(['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'Previous'=>$e->getPrevious()]);

              sendErrorMail(array('msg'=>$msg,'subject'=>MIGRATION_JOB_PRODUCTS_FAILED));
              
              Log::critical($msg);
 

           

            
        } 
    }

     /**
     * Process product response.
     *
     * @param Array $parameter    
     */
    public function process_tags($parameter){

     
      $user = auth()->user();      
       try {
            $results = $this->woocommerce->get('products/tags',$parameter);              
            
            
            if(!empty($results) && count($results) > 0){
                foreach($results as $content){             
                    Eloquent::unguard();                  
                 
                    $pid=Tags::create_tags($content);                    
                    storeData::updateorcreate([
                        'option_name' => 'last_run_sync'                     
                    ],[
                        'option_name' => 'last_run_sync',
                        'option_value' => now()                   
                    ]);                        
                }
                $parameter['page']=$parameter['page']+1;
                $this->process_tags($parameter);
            }else{               
                 DataMigration::updateorcreate([
                      'name' => 'tags'                         
                  ],[
                      'name' => 'tags',                                
                      'status' => 'completed'                           
                  ]); 
               $msg=json_encode(['message'=>'']);

              sendErrorMail(array('msg'=>$msg,'subject'=>MIGRATION_JOB_PRODUCTS_FAILED));
              
              Log::critical($msg);
            }            
         } catch (\Exception $e) {            
          
             DataMigration::updateorcreate([
                  'name' => 'tags'                         
              ],[
                  'name' => 'tags',                                
                  'status' => 'failed'                           
              ]);
            
             $msg=json_encode(['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'Previous'=>$e->getPrevious()]);

              sendErrorMail(array('msg'=>$msg,'subject'=>MIGRATION_JOB_PRODUCTS_FAILED));
              
              Log::critical($msg);
 

           

            
        } 
    }

    /**
     * Process customer response.
     *
     * @param Array $parameter    
     */
    public function process_customers($parameter){
       $user = auth()->user();      
          try {
            $parameter['role']='all';
            $results = $this->woocommerce->get('customers',$parameter);
            if(!empty($results) && count($results) > 0){
                foreach($results as $content){  
                    $req_dump='';
                    $req_dump .= print_r($content, TRUE);
                    $fp = fopen('request.log', 'a');
                    fwrite($fp, $req_dump);
                    fclose($fp); 
                    Eloquent::unguard();                
                    $uid = Customers::Create($content);  
                    storeData::updateorcreate([
                        'option_name' => 'last_run_sync'                     
                    ],[
                        'option_name' => 'last_run_sync',
                        'option_value' => now()                   
                    ]);                
                }
                $parameter['page']=$parameter['page']+1;
                $this->process_customers($parameter);
            }else{
              
                 DataMigration::updateorcreate([
                      'name' => 'customers'                         
                  ],[
                      'name' => 'customers',                                
                      'status' => 'completed'                           
                  ]);
                $msg=json_encode(['message'=>'']);

               sendErrorMail(array('msg'=>$msg,'subject'=>MIGRATION_JOB_CUSTOMERS_FAILED));
              
              Log::critical($msg);
            } 
         } catch (\Exception $e) {            
          
             DataMigration::updateorcreate([
                  'name' => 'customers'                         
              ],[
                  'name' => 'customers',                                
                  'status' => 'failed'                           
              ]);
             $msg=json_encode(['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'Previous'=>$e->getPrevious()]);

              sendErrorMail(array('msg'=>$msg,'subject'=>MIGRATION_JOB_CUSTOMERS_FAILED));
              
              Log::critical($msg);
        }    
         
    }
     /**
     * Process orders response.
     *
     * @param Array $parameter    
     */
    public function process_orders($parameter){
       $user = auth()->user();      
     
       try {
            $results = $this->woocommerce->get('orders',$parameter);
            if(!empty($results) && count($results) > 0){
                foreach($results as $content){
                    $req_dump='';
                    $req_dump .= print_r($content, TRUE);
                    $fp = fopen('request.log', 'a');
                    fwrite($fp, $req_dump);
                    fclose($fp);                 
                    Eloquent::unguard();                  
                    $oid = Orders::Create($content);
                     storeData::updateorcreate([
                        'option_name' => 'last_run_sync'                     
                    ],[
                        'option_name' => 'last_run_sync',
                        'option_value' => now()                   
                    ]); 
                  
                }
                $parameter['page']=$parameter['page']+1;
                $this->process_orders($parameter);
            }else{
                
                 DataMigration::updateorcreate([
                      'name' => 'orders'                         
                  ],[
                      'name' => 'orders',                                
                      'status' => 'completed'                           
                  ]);
                 $msg=json_encode(['message'=>'']);

              sendErrorMail(array('msg'=>$msg,'subject'=>MIGRATION_JOB_ORDERS_FAILED));
              
              Log::critical($msg);
            }              
         } catch (\Exception $e) {            
          
             DataMigration::updateorcreate([
                  'name' => 'orders'                         
              ],[
                  'name' => 'orders',                                
                  'status' => 'failed'                           
              ]);
            $msg=json_encode(['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'Previous'=>$e->getPrevious()]);

              sendErrorMail(array('msg'=>$msg,'subject'=>MIGRATION_JOB_ORDERS_FAILED));
              
              Log::critical($msg);
        } 
    }
     /**
     * Process coupon response.
     *
     * @param Array $parameter    
     */
    public function process_coupons($parameter){
       $user = auth()->user();      
       try {
            $results = $this->woocommerce->get('coupons',$parameter);
            if(!empty($results) && count($results) > 0){
                foreach($results as $content){
                    $req_dump='';
                    $req_dump .= print_r($content, TRUE);
                    $fp = fopen('request.log', 'a');
                    fwrite($fp, $req_dump);
                    fclose($fp);                 
                    Eloquent::unguard();                    
                    $cid = Coupons::Create($content);
                    storeData::updateorcreate([
                        'option_name' => 'last_run_sync'                     
                    ],[
                        'option_name' => 'last_run_sync',
                        'option_value' => now()                   
                    ]);              
                }
                $parameter['page']=$parameter['page']+1;
                $this->process_coupons($parameter);
            }else{
               
                DataMigration::updateorcreate([
                      'name' => 'coupons'                         
                  ],[
                      'name' => 'coupons',                                
                      'status' => 'completed'                           
                  ]);
                 $msg=json_encode(['message'=>'']);

              sendErrorMail(array('msg'=>$msg,'subject'=>MIGRATION_JOB_COUPONS_FAILED));
              
              Log::critical($msg);
            }            
         } catch (\Exception $e) {           
          
              DataMigration::updateorcreate([
                  'name' => 'coupons'                         
              ],[
                  'name' => 'coupons',                                
                  'status' => 'failed'                           
              ]);
              $msg=json_encode(['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'Previous'=>$e->getPrevious()]);

              sendErrorMail(array('msg'=>$msg,'subject'=>MIGRATION_JOB_COUPONS_FAILED));
              
              Log::critical($msg);
        } 
    }


    public function add_user_wp($userdata=[]){ 
      $response='';
      if(!empty($userdata) && count($userdata) > 0){
          $response=$this->woocommerce->post('create_user',array('data'=> $userdata));  
          if($response && $response->status=='success'){
              $customers=$this->woocommerce->get('customers/'.$response->user_id);  
              CustomerController::create($customers);
          }        
          
      }  

      return $response;
          
    }

     public function send_order_wp_email($userdata=[]){ 
      $response='';
      if(!empty($userdata) && count($userdata) > 0){
          $response=$this->woocommerce->post('order_notification',array('data'=> $userdata));               
          
      }  

      return $response;
          
    }

    public function add_product_wp($userdata=[]){ 

      $response=false;
      if(!empty($userdata) && count($userdata) > 0){ 
        if(!empty($userdata['id'])){
          $response=$this->woocommerce->put('products/'.$userdata['id'],$userdata); 
        }else{
          $response=$this->woocommerce->post('products',$userdata); 
        }
        if($response && !empty($response->id)){
            //$product=$this->woocommerce->get('products/'.$response->user_id);  
            ProductController::create($response);
        }        
          
      }  

      return $response;
          
    }

    public function add_order_wp($postData1=[]){  
       

      $postData['update'][]=$postData1;
      $response='';
      if(!empty($postData) && count($postData) > 0){      
          $response=$this->woocommerce->put('orders/batch' ,$postData);  
          if($response && $response->update[0]){
            if($response && !empty($response->update[0]->id)){                 
                OrderController::create($response->update[0]);
            }             
          }     
          
      }

      return $response;
          
    }

    public function add_product_tag_wp($userdata=[]){ 

      $response=false;
      if(!empty($userdata) && count($userdata) > 0){
          $response=$this->woocommerce->post('products/tags/batch',array('create'=>$userdata));         
      }  
      return $response;
          
    }


     public function delete_user_wp($user_id){      
      if(!empty($user_id)){
        try{
          $user=$this->woocommerce->delete("customers/$user_id",array('force'=>true));  
          return array('status'=>true,'user'=>$user); 
        }catch (HttpClientException $e) {
           return array('status'=>false,'msg'=>$e->getMessage()); 
        } catch (\Exception $e) {          
           return array('status'=>false,'msg'=>$e->getMessage()); 
         }   
       }  
    }

     public function delete_product_wp($id){      
      if(!empty($id)){
        try{
          $product=$this->woocommerce->delete("products/$id",array('force'=>true));  
          return array('status'=>true,'product'=>$product); 
        }catch (HttpClientException $e) {                     
        
           return array('status'=>false,'msg'=>$e->getMessage()); 
        } catch (\Exception $e) {   
          return array('status'=>false,'msg'=>$e->getMessage()); 
        }   
       }  
    }

    public function delete_order_wp($id){      
      if(!empty($id)){
        try{
          $orders=$this->woocommerce->delete("orders/$id",array('force'=>true));  
          return array('status'=>true,'orders'=>$orders); 
        }catch (HttpClientException $e) {                     
        
           return array('status'=>false,'msg'=>$e->getMessage()); 
        } catch (\Exception $e) {   
          return array('status'=>false,'msg'=>$e->getMessage()); 
        }   
       }  
    }



    public function check_failed_pending(){ 
       $data['setting']=$this->data_setting;
       if($this->data_setting['check_failed_pending']!='running'){
          storeData::updateorcreate([
              'option_name' => 'check_failed_pending'                     
          ],[
              'option_name' => 'check_failed_pending',
              'option_value' => 'running'                   
          ]); 
         try {           
              
            $response=$this->woocommerce->post('check_failed_data',array());  
            
            if(!empty($response) && $response->products){
              $response_count['failed']=[];
              foreach ($response->products as $key => $value) {
                $parameter=json_decode($value->value,true);
                $parameter['remote_id']=$value->id;             
                $response_count=$this->process_item($parameter);
                if(!$response_count['status']){
                  $response_count['failed'][]=$response_count['msg'];
                }                    
              }
              if(count($response_count['failed']) > 0){
                 $msg=json_encode($response_count['failed']);
                 sendErrorMail(array('msg'=>$msg,'subject'=>FAILED_PENDING_PROCESS_Error));               
              } 
            }
            storeData::updateorcreate([
              'option_name' => 'check_failed_pending'                     
            ],[
                'option_name' => 'check_failed_pending',
                'option_value' => ''                   
            ]);  
             Log::critical("check_failed_pending");         
          } catch (\Exception $e) {   

           storeData::updateorcreate([
              'option_name' => 'check_failed_pending'                     
          ],[
              'option_name' => 'check_failed_pending',
              'option_value' => ''                   
          ]); 

          $msg=json_encode(['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'Previous'=>$e->getPrevious()]);

           sendErrorMail(array('msg'=>$msg,'subject'=>FAILED_PENDING_PROCESS_EXCEPTION));
                
           Log::critical($msg); 
        }       
      }else{
         $msg="Process already running"; 
         sendErrorMail(array('msg'=>$msg,'subject'=>FAILED_PENDING_PROCESS_ALREADY_RUNNING));
              
         Log::critical($msg); 
      }         
      
    }  

    public function get_user_roles(){     

       $data['setting']=$this->data_setting;      
         try {           
              
            $response=$this->woocommerce->post('get_user_roles',array());  
            
            if(!empty($response) && $response->roles){           
              storeData::updateorcreate([
              'option_name' => 'customer_role'                     
              ],[
                  'option_name' => 'customer_role',
                  'option_value' => json_encode($response->roles)                  
              ]); 
          
            }           
             
          } catch (\Exception $e) {            

          $msg=json_encode(['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'Previous'=>$e->getPrevious()]);

           sendErrorMail(array('msg'=>$msg,'subject'=>FAILED_GET_CUSTOMER_ROLES));
                
           Log::critical($msg); 
        }       
        
      
    } 

    public function woocommerce_allowed_countries(){ 

       $data['setting']=$this->data_setting;      
         try {           
              
            $response=$this->woocommerce->get('settings/general/woocommerce_allowed_countries');  
            var_dump($response);
            exit;
            if(!empty($response) && $response->roles){           
              storeData::updateorcreate([
              'option_name' => 'customer_role'                     
              ],[
                  'option_name' => 'customer_role',
                  'option_value' => json_encode($response->roles)                  
              ]); 
          
            }           
             
          } catch (\Exception $e) {            

          $msg=json_encode(['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'Previous'=>$e->getPrevious()]);

           sendErrorMail(array('msg'=>$msg,'subject'=>FAILED_GET_CUSTOMER_ROLES));
                
           Log::critical($msg); 
        }       
        
      
    }  


     /**
     * Import process for all resources.
     *
     * @param Request $request    
     */
    //public function index(Request $request)
    public function process_item($parameter)
    {
     
        try {        
                

             $moduleName=array('customer','product','order','coupon');  
             $moduleNameDelete=array('customer.deleted','product.deleted','order.deleted','coupon.deleted');                      

         
            $action=isset($parameter['X-WC-Webhook-Topic']) ? $parameter['X-WC-Webhook-Topic'] : '';
            $resource=isset($parameter['X-WC-Webhook-Resource']) ? $parameter['X-WC-Webhook-Resource'] : '';

             if(!empty($resource) && in_array($resource,  $moduleName) && !empty($parameter['id'])){
                 if(!empty($action) && !in_array($action,  $moduleNameDelete)){
                    $content= $this->{"process_{$resource}s_single"}($parameter);
                 }

                if($action=='customer.created'){
                    CustomerController::create($content);
                }else if($action=='customer.deleted'){
                    CustomerController::delete($content);
                }else if($action=='customer.updated'){
                    CustomerController::update($content);
                }else if($action=='product.created'){
                   ProductController::create($content);
                }else if($action=='product.deleted'){
                    ProductController::delete($content);
                }else if($action=='product.updated'){
                    ProductController::update($content);
                }else if($action=='product.restored'){
                    ProductController::restore($content);
                }else if($action=='order.created'){
                   OrderController::create($content);
                }else if($action=='order.deleted'){
                    OrderController::delete($content);
                }else if($action=='order.updated'){
                    OrderController::update($content);
                }else if($action=='order.restored'){
                    OrderController::restore($content);
                }else if($action=='coupon.created'){
                   CouponController::create($content);
                }else if($action=='coupon.deleted'){
                    CouponController::delete($content);
                }else if($action=='coupon.updated'){
                    CouponController::update($content);
                }else if($action=='coupon.restored'){
                    CouponController::restore($content);
                } 

               $response= $this->woocommerce->post('update_processed_data',array('id' => $parameter['remote_id'],'status'=>'completed' )); 
               return array('status'=>true,'msg'=>'');               

                
             }else{
              $response= $this->woocommerce->post('update_processed_data',array('id' => $parameter['remote_id'],'status'=>'failed' ));              
            
              return array('status'=>false,'msg'=>$parameter);  
             }
        } catch (HttpClientException $e) {

             $msg=json_encode(['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'Previous'=>$e->getPrevious()]);          
            
            Log::critical($msg);
           return array('status'=>false,'msg'=>array($parameter,$msg)); 
        } catch (\Exception $e) {          
          
            
              $msg=json_encode(['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'Previous'=>$e->getPrevious()]);
              Log::critical($msg);
              return array('status'=>false,'msg'=>array($parameter,$msg)); 
              
              
        }        
    } 


     /**
     * Process product response.
     *
     * @param Array $parameter    
     */
    public function process_products_single($parameter){
         $user = auth()->user();      
       try {
           return $content = $this->woocommerce->get('products/'.$parameter['id']);    
                              
         } catch (\Exception $e) {            
            $msg=json_encode(['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'Previous'=>$e->getPrevious()]);
            Log::critical($msg);
            
        } 
    }
    /**
     * Process customer response.
     *
     * @param Array $parameter    
     */
    public function process_customers_single($parameter){
       $user = auth()->user();      
          try {
            $parameter['role']='all';
            return $content =  $this->woocommerce->get('customers/'.$parameter['id']);  
          
         } catch (\Exception $e) {            
          $msg=json_encode(['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'Previous'=>$e->getPrevious()]);
            Log::critical($msg);
        }    
         
    }
     /**
     * Process orders response.
     *
     * @param Array $parameter    
     */
    public function process_orders_single($parameter){
      
       $user = auth()->user();      
       try {
            return $this->woocommerce->get('orders/'.$parameter['id']);
           
                     
         } catch (\Exception $e) {            
          $msg=json_encode(['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'Previous'=>$e->getPrevious()]);
            Log::critical($msg);
        } 
    }
     /**
     * Process coupon response.
     *
     * @param Array $parameter    
     */
    public function process_coupons_single($parameter){
         $user = auth()->user();      
       try {
           return $this->woocommerce->get('coupons/'.$parameter['id']);
          
         } catch (\Exception $e) {            
          $msg=json_encode(['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'Previous'=>$e->getPrevious()]);
            Log::critical($msg);
        } 
    }


    public function update_order($postData){
      if(!empty($postData) && count($postData) > 0){      
          $response=$this->woocommerce->put('orders/'.$postData['id'] ,$postData);  
          if($response){
            if($response && !empty($response)){                 
                OrderController::create($response);
            }             
          }   
      return $response;
      }
    }  

    public function delete_order_refund($postData){

      if(!empty($postData) && count($postData) > 0){      
          $response=$this->woocommerce->delete('orders/'.$postData['order_id'].'/refunds/'.$postData['refund_id'] ,['force' => true]);
          if($response){
              $orders=$this->woocommerce->get('orders/'.$postData['order_id']);  
              OrderController::create($orders);
          }
          
      return $response;
      }
    }

    public function create_order_refund($postData){
      $postData['api_refund']=false;
      if(!empty($postData) && count($postData) > 0){      
          $response=$this->woocommerce->post('orders/'.$postData['order_id'].'/refunds',$postData);
          if($response){
              $orders=$this->woocommerce->get('orders/'.$postData['order_id']);  
              OrderController::create($orders);
          }  
      return $response;
      }
    }




}
