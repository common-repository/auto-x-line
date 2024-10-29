<?php
namespace Ax8\Api;
use WP_REST_Controller;
use Ax8\Ax8_Helper as Helper;
/**
 * REST_API Handler
 */
class Ax8_Accounts extends WP_REST_Controller {
    /**
     * [__construct description]
     */
    public function __construct() {
        $this->namespace = 'autoxline/v1';
        $this->rest_base = "/accounts/";
        $this->db = new \Ax8\Models\Ax8_Accounts();
    }
    /**
     * Register the routes
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            $this->rest_base,
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_items' ),
                    'permission_callback' => array( $this, 'get_items_permissions_check' ),
                    'args'                => $this->get_collection_params(),
                )
            )
        );
        /* SAVE ACCOUNT ROUTE */
        register_rest_route(
            $this->namespace,
            $this->rest_base,
            array(
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'save_account' ),
                    'permission_callback' => array( $this, 'get_items_permissions_check' ),
                    'args'                => $this->get_collection_params(),
                )
            )
        );
        /* TEST CONNECTION ROUTE */
        register_rest_route(
            $this->namespace,
            $this->rest_base."test-connection",
            array(
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'test_connection' ),
                    'args'                => $this->get_collection_params(),
                    'permission_callback' => array( $this, 'get_items_permissions_check' ),
                )
            )
        );
        /* FETCH ACCOUNT LOGS ROUTE */
        register_rest_route(
            $this->namespace,
            $this->rest_base."log-history",
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_log_history' ),
                    'args'                => $this->get_collection_params(),
                    'permission_callback' => array( $this, 'get_items_permissions_check' ),
                )
            )
        );
        /* FETCH COMMON DATA ROUTE */
        register_rest_route(
            $this->namespace,
            "/common-data",
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_common_data' ),
                    'permission_callback' => array( $this, 'get_items_permissions_check' ),
                    'args'                => $this->get_collection_params(),
                )
            )
        );
        /* DELETE LOG ROUTE */
        register_rest_route(
            $this->namespace,
            $this->rest_base."clear-log",
            array(
                array(
                    'methods'             => \WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'clear_log' ),
                    'permission_callback' => array( $this, 'get_items_permissions_check' ),
                    'args'                => $this->get_collection_params(),
                )
            )
        );
    }
    public function filter_data($item){
        return [
            'slug'=> $item->slug,
            'name' => $item->name
        ];
    }
    public function get_common_data($request){
        $tags = $categories = $post_types = $post_formats = $authors = [];
        if(!empty($categories)){
            $categories = array_map([$this,'filter_data'],$categories);
        }
        if(!empty($tags)){
            $tags = array_map([$this,'filter_data'],$tags);
        }
        $account = $this->db->where('account_id',1)->first();
        $form = false;
        if(!empty($account)){
            $form = [
                'account_id'            => $account->account_id,
                'channel_access_token'  => $account->access_token,
                'channel_id'            => $account->where_to_post,
                'message_format'        => $account->message_format,
                'send_delay'            => $account->send_delay
            ];
            if($account->has_filter){
                $form['filter_posts']           = true;
                $form['filter_posts_fields']    = json_decode($account->filters,true);
            }
        }
        $items = [
            'tags'                  => $tags, 
            'categories'            => $categories,
            'post_types'            => $post_types,
            'post_formats'          => $post_formats,
            'authors'               => $authors,
            'account'               => $form,
            'user_id'               => wp_get_current_user(),
            'next_task_schedule'    => wp_next_scheduled('ax8_schedule_tasks') ? Helper::formatDate(wp_next_scheduled('ax8_schedule_tasks'),'d M Y h:i:s A') : '',
        ];
        $response = rest_ensure_response( $items );
        $response->set_status(200);
        return $response;
    }
    public function get_log_history($request){
        $post = $request->get_params();
        $logger = new \Ax8\Models\Ax8_Logs();
        $where = ['account_id'=>1];
        $rows = $logger->getLogs($where,$request);
        $items = ['status'=>true,'rows'=>$rows];
        $response = rest_ensure_response( $items );
        $response->set_status(200);
        return $response;
    }
    public function clear_log(){
        $logger = new \Ax8\Models\Ax8_Logs();
        $where = ['account_id'=>1];
        $logger->where($where)->delete();
        $response = rest_ensure_response( ['status'=>true,'message'=>Helper::trans("log_cleared")] );
        $response->set_status(200);
        return $response;
    }
    public function save_account($request){
        $post = $request->get_params();
        $data = [
            'account_type'      => sanitize_text_field('line'),
            'access_token'      => sanitize_text_field($post['channel_access_token']),
            'where_to_post'     => sanitize_text_field($post['channel_id']),
            'user_id'           => get_current_user_id(),      
            'message_format'    => sanitize_textarea_field($post['message_format']),
            'has_filter'        => $post['filter_posts'] ?? false,
            'filters'           => '',
            'send_delay'        => $post['send_delay']
        ];
        if($data['has_filter']){
            $data['filters'] = json_encode($post['filter_posts_fields']);
        }
        if(isset($post['account_id'])){
            $message = Helper::trans("account_details_updated");//"Account Details Updated!";
            $this->db->where('account_id', $post['account_id'])->update($data);
        }else{
            $message = Helper::trans("account_details_saved");//"Account Details Saved!";
            $id = $this->db->insertGetId($data);
        }
        $response = ['status'=>true,'message'=>$message];
        $response = rest_ensure_response( $response );
        $response->set_status(200);
        return $response;
    }
    public function test_connection($request){
        $post = $request->get_params();
        $url = Helper::getBaseApiUrl('line','bot/message/broadcast');
        $headers = [
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$post['access_token'],
            //'X-Line-Retry-Key: '.$post['channel_id']
        ];
        $test_connection_msg = Helper::trans("test_api_connection_autoxline");
        $test_connection_msg .= " : ".get_site_url();
        $data = ['messages'=>[
            [
                'type'=>'text',
                'text'=> $test_connection_msg,//'This is testing message to verify your connection with the AutoXline'
            ]
        ]];
        $response = Helper::makeRequest($url,"post",json_encode($data),$headers);
        if($response['status']){
            $response['message'] = "Message has been sent to given channel. Test Connection is successful!";
        }
        $response = rest_ensure_response( $response );
        $response->set_status(200);
        return $response;
    }    
    /**
     * Retrieves a collection of items.
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_items( $request ) {
        $items = [];
        $response = rest_ensure_response( $items );
        return $response;
    }
    /**
     * Checks if a given request has access to read the items.
     *
     * @param  WP_REST_Request $request Full details about the request.
     *
     * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
     */
    public function get_items_permissions_check( $request ) {
        return true;
    }
    /**
     * Retrieves the query params for the items collection.
     *
     * @return array Collection parameters.
     */
    public function get_collection_params() {
        return [];
    }
}