<?php
namespace Ax8\Api;

use WP_REST_Controller;
use Ax8\Ax8_Helper as Helper;
/**
 * REST_API Handler
 */
class Ax8_Settings extends WP_REST_Controller {
    protected $meta_keys = ['date_format','language'];
    /**
     * [__construct description]
     */
    public function __construct() {
        $this->namespace = 'autoxline/v1';
        $this->rest_base = "/settings/";
        foreach($this->meta_keys as $i=>$key){
            $this->meta_keys[$i] = AUTO_X_LINE_PREFIX.$key;
        }
        $this->db = false;//new \Ax8\Models\Ax8_Settings();
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
                    'callback'            => array( $this, 'get' ),
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
                    'callback'            => array( $this, 'save' ),
                    'permission_callback' => array( $this, 'get_items_permissions_check' ),
                    'args'                => $this->get_collection_params(),
                )
            )
        );
    }
    public function get($request){
        if($this->meta_keys){
            $settings = [];
            foreach($this->meta_keys as $key){
                $settings[$key] = get_option($key) ? get_option($key) : '';
            }
        }
        $time = time();
        $date_formats = [
            'WP_FORMAT'         => Helper::trans("system_format")." - ".Helper::formatDate($time,'WP_FORMAT'),
            'd M Y h:i A'       => Helper::formatDate($time,'d M Y h:i A'),
            'd M Y H:i:s T'     => Helper::formatDate($time,'d M Y H:i:s T'),
        ];
        $dateFormatKey = Helper::getMetaKey("date_format");

        $settings[$dateFormatKey] = !$settings[$dateFormatKey] ? 'WP_FORMAT' : $settings[$dateFormatKey];
        $items['date_formats']  = $date_formats;
        $items['settings']      = $settings;
        $response               = rest_ensure_response( $items );
        $response->set_status(200);
        return $response;
    }
    public function save($request){
        $post = $request->get_params();
        foreach($this->meta_keys as $key){
            if(isset($post[$key])){
                update_option($key,sanitize_text_field($post[$key]));
            }
        }
        $message = Helper::trans("settings_updated");//'Settings Updated';
        $response = ['status'=>true,'message'=>$message];
        $langKey = Helper::getMetaKey("language");
        if(isset($post[$langKey])){
            ax8DeleteCacheData();
            $langResult = setAx8LanguageCookie(sanitize_text_field($post[$langKey]));
            
            $response['reload'] = $langResult['updated'];
        }

        $response = rest_ensure_response( $response );
        $response->set_status(200);
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
