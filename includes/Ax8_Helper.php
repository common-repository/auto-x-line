<?php
namespace Ax8;
class Ax8_Helper
{
    public static function shout(string $string){
        return strtoupper($string);
    }
    public static function formatDate($date,$format='d M Y',$isTimestamp=false){
        $format = ($format == 'WP_FORMAT') ? self::getSystemFormat() : $format;
        if(!$isTimestamp){ $date = strtotime($date); }
        return date_i18n($format, $date);
    }
    public static function getBaseApiUrl($service,$append_url=''){
        $url = '';
        switch($service){
            case 'line':
                $url = "https://api.line.me/v2/";
            break;
            default:
                return false;
        }
        return $url.$append_url;
    }
    public static function getMetaKey($key_name){
        return esc_attr(AUTO_X_LINE_PREFIX.$key_name);
    }
    public static function checkPostMeta($post_id,$checkNew=true){
        $hasMeta = metadata_exists("post",$post_id,self::getMetaKey('new_post'));
        if($hasMeta){
            if($checkNew){
                return get_post_meta($post_id,self::getMetaKey('post_sent'),true) == false 
                && get_post_meta($post_id,self::getMetaKey('new_post'),true) == false;
            }
            $postMeta = get_post_meta($post_id,self::getMetaKey('new_post'),true);
            return $postMeta != false;
        }
        return true;
    }
    public static function isInQueue($post_id,$mode='',$data=[]){
        $metaKey = self::getMetaKey('in_queue');
        if($mode =='add'){
            add_post_meta($post_id,$metaKey,current_time('mysql'),true);
            return true;
        }
        if($mode == 'delete'){
            delete_post_meta($post_id,$metaKey);
            return true;
        }
        $hasMeta = metadata_exists("post",$post_id,self::getMetaKey('in_queue'));
        return $hasMeta;
    }
    public static function getMenuUrl($page){
        $filetime = filemtime( AUTO_X_LINE_PATH . '/assets/js/vendors.min.js' );
        return esc_url(admin_url("admin.php?page=auto-x-line#/{$page}?{$filetime}"));
    }
    public static function getSystemFormat($mode="datetime"){
        if($mode == 'datetime') return get_option('date_format').' '.get_option('time_format');
        if($mode == 'date') return get_option('date_format');
        if($mode == 'time') return get_option('time_format');
        return '';
    }
    public static function makeRequest($url, $method = "get", $request_fields = array(), $headers = [],$userpwd='') {
        $args = ['method'=>strtoupper($method),'headers'=>$headers,'body'=>$request_fields];
        $return = wp_remote_request($url,$args);
        $result = ['status'=>FALSE,'error'=>''];
        if(is_wp_error($return)){
            $result['error'] = $return->get_error_message();
        }else{
            $response = $return['response'];
            $result['result'] = $response;
            $httpcode = $response['code'];
            if($httpcode == 200){
                $result['status'] = TRUE;
            }
        }
        // do_action('at_write_log',$result,"{$method} - {$url}",'request-log');
        return $result;
    }
    public static function option_exists($name, $site_wide=false){
        $sql = "SELECT * FROM ". ($site_wide ? $wpdb->base_prefix : $wpdb->prefix). "options WHERE option_name ='$name' LIMIT 1";
        global $wpdb; return $wpdb->query($wpdb->prepare($sql));
    }
    public static function addLog($log_message,$account_id,$post_id=null,$type='info'){
        $logger     = new \Ax8\Models\Ax8_Logs();
        $log_time   = current_time('d-M-Y h:i:s A T',true);
        if(is_array($log_message)){
            $log_message = json_encode($log_message);
        }
        $insert = [
            'log_time'      => $log_time,
            'log_type'      => $type,
            'message'       => $log_message,
            'account_id'    => $account_id
        ];
        if(is_numeric($post_id)){ $insert['post_id'] = $post_id; }
        return $logger->insertGetId($insert);
    }
    public static function getPostFormats(){
        $post_formats = [];
        if ( current_theme_supports( 'post-formats' ) ) {
            $post_formats = get_theme_support( 'post-formats' );
            if ( is_array( $post_formats[0] ) ) {
                $post_formats = array_shift($post_formats);
            }
        }
        return $post_formats;
    }
    public static function getPostTypes(){
        return ['post'=>'post'];
    }
    public static function getAuthors(){
        return get_users(array(
            'role__in'    => ['author','administrator'],
            'fields'      => ['display_name','user_email','user_nicename']
        ));
    }
    public static function getLocale(){
        $locale = explode("_",get_locale());
        if(!empty($locale)){
            return esc_attr($locale[0]);
        }
        return esc_attr("ja");
    }
    public static function trans($key,$lang_short_form=''){
        $languages = [
            'en'    => 'English',
            'ja'    => 'Japanese'
        ];
        $cookie_name = self::getMetaKey('lang');// 'ax8_lang';
        if(empty($lang_short_form)){
            if(isset($_COOKIE[$cookie_name]) && array_key_exists(sanitize_text_field($_COOKIE[$cookie_name]),$languages)) {
                $lang_short_form = sanitize_text_field($_COOKIE[$cookie_name]);
                update_user_meta(get_current_user_id(),$cookie_name,$lang_short_form);
            }
        }
        if(empty($lang_short_form)){
            $user_lang = get_user_meta(get_current_user_id(),$cookie_name);
            if(!empty($user_lang)){
                $lang_short_form = $user_lang;
            }
        }
        if(empty($lang_short_form)){
            $lang = get_option(self::getMetaKey('language'));
            $lang_short_form = empty($lang) ? 'ja' : $lang;
        }
        $langFilePath = AUTO_X_LINE_PATH."/locales/{$lang_short_form}.json";
        if(file_exists($langFilePath)){
            $filemtime = filemtime($langFilePath);
            $optionKey = "{$cookie_name}_{$lang_short_form}_updated";
            // delete_option($optionKey);
            $cacheKey = "{$cookie_name}_translations_{$lang_short_form}";
            $lastUpdated = get_option($optionKey);
            $updateCache = empty($lastUpdated) || ($lang_short_form != get_option(self::getMetaKey('language')));            
            if($filemtime > $lastUpdated){
                $updateCache = true;
            }
            // $updateCache = true;
            if($updateCache){
                delete_transient($cacheKey);
                update_option($optionKey,$filemtime);
                $translations = @file_get_contents($langFilePath);
                $translations = json_decode(trim($translations),TRUE);
                $set = set_transient($cacheKey,$translations);
            }else{
                $translations = get_transient($cacheKey);
            }
            return isset($translations[$key]) ? esc_attr($translations[$key]) : '';
        }
        return '';
    }
}