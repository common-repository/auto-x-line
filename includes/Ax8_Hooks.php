<?php
namespace Ax8;
use Ax8\Ax8_Helper as Helper;
/**
 * Hooks Handler (Common Hooks)
 */
class Ax8_Hooks {

    public function __construct() {
        $this->init_hooks();
        $this->init_cron();
    }

    /**
     * Initialize our hooks for application
     *
     * @return void
     */
    public function init_hooks() {
        add_action( 'doSendMessage', [ $this, 'doSendMessage' ], 10, 1 );
        add_action( 'save_post', [ $this, 'ax8OnSavePost' ], 25, 3 );
    }
    public function init_cron(){
        $cron_one = Helper::getMetaKey('schedule_tasks'); //ax8_schedule_tasks
        $cron_two = Helper::getMetaKey('schedule_tasks_ten_mins'); //ax8_schedule_tasks_ten_mins

        //Cron Commands
        add_action( $cron_one, [$this,'getScheduleTasks'] );
        add_action( $cron_two, [$this,'getScheduleTasks'] );
        add_action( 'ax8_do_schedule_task', [ $this, 'getScheduleTasks' ], 10, 1 );

        if (!wp_next_scheduled($cron_one))
        {
            wp_schedule_event(time(),Helper::getMetaKey('tasks'), $cron_one);
            //ax8tasks
        }
        if (!wp_next_scheduled($cron_two))
        {
            wp_schedule_event(time() + (60*5), Helper::getMetaKey('tasks_ten_mins'), $cron_two);
            //ax8tasks_ten_mins
        }
    }
    public function getScheduleTasks($params=[]){
        // do_action('at_write_log',$params,'running','getScheduleTasks');

        $current_time = current_time('mysql',true);
        $task = new \Ax8\Models\Ax8_Tasks();
        $where = [
            'started'=>0,'completed'=>0
        ];
        if(isset($params['where'])){
            $where = $params['where'];
        }
        $rows = $task->where($where)
        //->where("time_gmt",'<=',$current_time)
        ->limit(20)->orderBy('time_gmt', 'ASC')->get()->toArray();
        // do_action('at_write_log',['time'=>$current_time,'tasks'=>$rows],'Running Cron Job To Get Tasks','ax8tasks');
        if(!empty($rows)){
            
            $process_list = [];
            $autoConfig = $this->getAccountConfig();
            $autoConfig['filters'] = json_decode($autoConfig['filters'],true);
            $params = ['autoConfig'=>$autoConfig];
            foreach($rows as $i=>$row){
                $current_time   = current_time('mysql',true);
                $time_gmt       = $row['time_gmt'];

                $row['current_time'] = $current_time;
                $d1 = new \DateTime($current_time);
                $d2 = new \DateTime($time_gmt);
                $interval = $d1->diff($d2);
                //if($d1 > $d2){
                if($interval->invert == 1 && ($interval->s > 0 || $interval->i > 0 || $interval->h > 0)){
                    $task->where('task_id', $row['task_id'])->update([
                        'started'=>1
                    ]);
                    $post = get_post($row['post_id']);
                    $params['post'] = $post;
                    $params['thread_no'] = $row['thread_no'];
                    $params['endProcess'] = (count($rows) - 1 == $i);
                    $process_list[] = $params;
                }
                
                $row['interval'] = $interval;
                // do_action('at_write_log',$row,'ROW - '.$row['task_id'],'ax8tasks');
            }
            if(!empty($process_list)){
                foreach($process_list as $process_task){
                    $this->doSendMessage($process_task);
                }
            }
        }
    }
    private function getPostLink($post_id,$post_title=''){
        $post_link = get_permalink($post_id);
        if($post_title == ''){
            $post_link_anchor = "POST ID (<a href='{$post_link}' target='_blank'>{$post_id}</a>) ";
        }else{
            $post_link_anchor = "POST ID ({$post_id}) : <a href='{$post_link}' target='_blank'>{$post_title}</a>";
        }
        return $post_link_anchor;
    }
    public function ax8OnSavePost($post_id, $post, $update){
        $nonce = AUTO_X_LINE_PREFIX.'filter_fields';
        $is_valid_nonce = ( isset( $_POST[ $nonce ] ) && wp_verify_nonce( sanitize_text_field(wp_unslash($_POST[$nonce])), "ax8OnSavePost" ));

        $input_name = Helper::getMetaKey('send_delay_edit');//AUTO_X_LINE_PREFIX."send_delay_edit";
        if($is_valid_nonce && isset($_POST[$input_name]) && sanitize_text_field($_POST[$input_name]) != 'dont_send'){
            $autoConfig = $this->getAccountConfig();
            $autoConfig['filters'] = json_decode($autoConfig['filters'],true);
            
            #do_action('at_write_log',$autoConfig,'','auto-config');
            #do_action('at_write_log',(array) $post,'POST','auto-config');
            $addScheduleTask = $sendNow = false;
            if($autoConfig){
                $newPost = false;
                if($post->post_status == 'publish' && Helper::checkPostMeta($post_id)){
                    add_post_meta($post_id,Helper::getMetaKey('new_post'),current_time('mysql'),true);
                    add_post_meta($post_id,Helper::getMetaKey('account_id'),$autoConfig['account_id'],true); //adding account Id to detect post
                    // $log_message = Helper::trans("new_post_added")." ".$this->getPostLink($post_id,$post->post_title);
                    $log_message = str_replace(["{post_title_with_link}"],[$this->getPostLink($post_id,$post->post_title)],Helper::trans("new_post_added"));
                    Helper::addLog($log_message,$autoConfig['account_id']);
                    $newPost = $addScheduleTask = true;
                    //wp_schedule_single_event( $run_time, 'doSendMessage',[$params] );
                    //$this->doSendMessage($params);
                }
                if(!Helper::isInQueue($post_id) && !$newPost){
                    /*To avoid from adding more than once in the Queue*/
                    if($post->post_status == 'publish' && Helper::checkPostMeta($post_id,false)){
                        if(isset($_POST[$input_name])){
                            $send_delay_edit = sanitize_text_field($_POST[$input_name]);
                            $add_log = false;
                            if($send_delay_edit == 'with_delay'){
                                $addScheduleTask = $add_log = true;
                            }else if($send_delay_edit == 'send_now'){
                                $autoConfig['send_delay'] = 1;
                                $addScheduleTask = $add_log = $sendNow = true;
                            }else{
                            }
                            if($add_log){
                                $log_message = str_replace(["{post_title_with_link}"],[$this->getPostLink($post_id,$post->post_title)],Helper::trans("post_updated"));
                                Helper::addLog($log_message,$autoConfig['account_id']);
                            }
                        }
                    }
                }
                if($addScheduleTask){
                    $delay = intval($autoConfig['send_delay']); //in seconds
                    $time_gmt = intval(current_time('timestamp',true) + $delay);
                    $time = intval(current_time('timestamp') + $delay);
                    
                    $log_message = str_replace(["{delay}"],[$delay],Helper::trans("filter_message_process"));
                    $params = [
                        'time_gmt'      => $time_gmt,
                        'time'          => $time,
                        'post'          => $post,
                        'account_id'    => $autoConfig['account_id'],
                        'post_id'       => $post_id
                    ];
                    $insert_data = $this->addScheduleTask($params);
                    if(isset($insert_data['id'])){
                        Helper::isInQueue($post_id,'add'); //add in list of queue
                        if($sendNow){
                            $schedule_where = ['task_id'=>$insert_data['id']];
                            wp_schedule_single_event( time()+2, 'ax8_do_schedule_task',[$schedule_where] );
                        }
                    }
                }
            }
        }
    }
    public function doSendMessage($params=[]){
        extract($params);
        $has_filter     = $autoConfig['has_filter'];
        $token          = $autoConfig['access_token'];
        $channel_id     = $autoConfig['where_to_post'];
        $stopProcess    = false;
        //FILTER_PROCESS _GOES HERE
        stopProcess:
        if($stopProcess){
            //process terminated because of filters
        }else{
            
            $format_str = [
                "TITLE"             => $post->post_title,
                "URL"               => get_permalink($post),
                "EXCERPT"           => get_the_excerpt($post),
                "TAGS"              => wp_get_post_tags( $post->ID, [ 'fields' => 'names' ] ),
                "CATS"              => wp_get_post_categories( $post->ID, [ 'fields' => 'names' ] ),
                "HTAGS"             => false,
                "HCATS"             => false,
                "AUTHORNAME"        => get_the_author_meta( 'display_name' , $post->post_author ),
                "SITENAME"          => get_bloginfo('name'),
            ];
            $send_message = $message_format = $autoConfig['message_format'];
            foreach($format_str as $format_str_key => $format_str_val){
                $check_str = "%{$format_str_key}%";
                $replace_str = false;
                if(stripos($message_format, $check_str)){
                    switch($check_str){
                        case ($check_str == "%FULLTEXT%"):
                            $content        = apply_filters('the_content', $format_str_val);
                            $replace_str    = str_replace(']]>', ']]&gt;', $content);
                        break;

                        case ($check_str == "%TAGS%" || $check_str == "%CATS%"):
                            
                            if(is_array($format_str_val) && !empty($format_str_val)){
                                $replace_str    = implode(",",$format_str_val);
                            }
                        break;

                        case ($check_str == "%HTAGS%"):
                            if(is_array($format_str['TAGS']) && !empty($format_str['TAGS'])){
                                $hash_arr       = $format_str['TAGS'];
                                array_walk($hash_arr, function(&$elem) { $elem = "#{$elem}"; });
                                $replace_str    = implode(" ",$hash_arr);
                            }
                        break;

                        case ($check_str == "%HCATS%"):
                            if(is_array($format_str['CATS']) && !empty($format_str['CATS'])){
                                $hash_arr       = $format_str['CATS'];
                                array_walk($hash_arr, function(&$elem) { $elem = "#{$elem}"; });
                                $replace_str    = implode(" ",$hash_arr);                       
                            }
                        break;

                        default:
                            $replace_str = (!empty($format_str_val)) ? $format_str_val : '';
                        break;

                    }
                }
                if($replace_str){
                    $send_message = str_replace($check_str,$replace_str,$send_message);
                }else{
                    $send_message = str_replace($check_str,"",$send_message);
                }
            }
            
            $result = $this->sendLineMessage($token,$channel_id,$send_message);
            $log_type = "success";
            $plink = $this->getPostLink($post->ID);
            if($result['status']){
                $log_message = str_replace(["{post_title_with_link}"],[$plink],Helper::trans("message_send_to_line_channel"));
            }else{
                $log_message    = str_replace(["{post_link}","{sending_error}"],[
                    $plink,$result['error']
                ],Helper::trans("message_send_line_fail"));
                $log_type       = "error";
            }
            $task = new \Ax8\Models\Ax8_Tasks();
            $task->where('thread_no', $thread_no)->update([
                'completed'=>1
            ]);
            Helper::isInQueue($post->ID,'delete'); //deleting queue meta
            Helper::addLog($log_message,$autoConfig['account_id'],$post->ID,$log_type);
            add_post_meta($post->ID,'at_axl_post_sent',current_time('mysql'),true);
        }
    }
    public function sendLineMessage($token,$channel_id,$message){
        $url = Helper::getBaseApiUrl('line','bot/message/broadcast');
        $headers = [
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$token,
            //'X-Line-Retry-Key: '.$post['channel_id']
        ];

        $data = json_encode(['messages'=>[
            [
                'type' => 'text',
                'text' => $message
            ]
        ]]);
        return Helper::makeRequest($url,"post",$data,$headers);
    }
    public function applyFilter($key,$params){
        extract($params);
        $result = ['stop'=>false];
        $selected_slugs  = $filters[$key]['list'];
        #do_action('at_write_log',$selected_slugs,"{$key} Selected Slugs",'filter-process');
        //$selected_tags  = json_decode($filters['tags']['list'],TRUE);
        if(!empty($selected_slugs)){
            $mode   = $filters[$key]['mode'];
            $slugs  = [];
            $lang_key = $key;
            if($key == 'categories'){
                $slugs  = wp_get_post_categories( $post->ID, [ 'fields' => 'slugs' ] );
            }
            if($key == 'tags'){
                $slugs  = wp_get_post_tags( $post->ID, [ 'fields' => 'slugs' ] );
            }
            if($key == 'post_types'){
                $slugs = get_post_types();
            }
            if($key == 'post_formats'){
                $lang_key = "post_format";
                $slugs = Helper::getPostFormats();
            }
            if($key == 'authors'){
                $lang_key = "author";
                $authors = Helper::getAuthors();
                if(!empty($authors)){ foreach($authors as $author){
                    $slugs[] = $author->user_email;
                } }
            }

            /*do_action('at_write_log',
                [$key => $slugs,'key'=>$key,'selected_slugs'=>$selected_slugs],
                "{$key} DB Slugs",'filter-process');*/

            $common_slugs = array_intersect($slugs,$selected_slugs);

            if( ($mode == 'include' && count($common_slugs) <= 0) || 
                ($mode == 'exclude' && count($common_slugs) >= 1) ){
                $selected_slugs_str = implode(',',$selected_slugs);
                #:: must {$mode} - {$selected_slugs_str} 
                $log_message = "Post not having {$key} as per filter | Process Terminated :: ".$this->getPostLink($post->ID,$post->post_title);
                Helper::addLog($log_message,$account['account_id'],$post->ID,'terminate');
                $log_message = str_replace(["{filter_name}","{post_title_with_link}"],[
                    Helper::trans($lang_key),$this->getPostLink($post->ID,$post->post_title)
                ],Helper::trans("filter_error_message"));
                Helper::addLog($log_message,$account['account_id'],$post->ID,'terminate');
                $result['stop'] = true;
            }
        }
        return $result;
    }
    public function getAccountConfig($type='line'){
        $account = new \Ax8\Models\Ax8_Accounts();
        $row = $account->where('account_type',$type)->first();
        if(!empty($row)){
            return $row->toArray();
        }
        return false;
    }
    public function addScheduleTask($params){
        $task = new \Ax8\Models\Ax8_Tasks();
        $data = [
            'thread_no'     => uniqid(),
            'account_id'    => $params['account_id'],
            'post_id'       => $params['post_id'],
            'post_id'       => $params['post_id'],
            'time'          => gmdate('Y-m-d H:i:s',intval($params['time'])),
            'time_gmt'      => gmdate('Y-m-d H:i:s',intval($params['time_gmt']))
        ];
        $insertId = $task->insertGetId($data);
        return [
            'id'=>$insertId,
            'thread_no'=>$data['thread_no']
        ];
    } 
}
