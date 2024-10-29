<?php
/*
Plugin Name: Auto x LINE
Plugin URI: https://ax8.in/
Description: Auto send new posts to your LINE Channel.
Version: 1.0.0
Author: Ax8
Author URI: https://ax8.in/
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: auto-x-line
*/
/**
 * Copyright (c) 2023 Ax8 (email: asif@ax8.in). All rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * **********************************************************************
 */
// don't call the file directly
if ( !defined( 'ABSPATH' ) ) exit;
/**
 * Auto_x_line class
 *
 * @class Auto_x_line The class that holds the entire Auto_x_line plugin
 */
final class Auto_x_line {
    /**
     * Plugin version
     *
     * @var string
     */
    public $version = '1.0.0';
    /**
     * Holds various class instances
     *
     * @var array
     */
    private $container = array();
    /**
     * Constructor for the Auto_x_line class
     *
     * Sets up all the appropriate hooks and actions
     * within our plugin.
     */
    public function __construct() {
        $this->define_constants();
        add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }
    /**
     * Initializes the Auto_x_line() class
     *
     * Checks for an existing Auto_x_line() instance
     * and if it doesn't find one, creates it.
     */
    public static function init() {
        static $instance = false;
        if ( ! $instance ) {
            $instance = new Auto_x_line();
        }
        return $instance;
    }
    /**
     * Magic getter to bypass referencing plugin.
     *
     * @param $prop
     *
     * @return mixed
     */
    public function __get( $prop ) {
        if ( array_key_exists( $prop, $this->container ) ) {
            return $this->container[ $prop ];
        }
        return $this->{$prop};
    }
    /**
     * Magic isset to bypass referencing plugin.
     *
     * @param $prop
     *
     * @return mixed
     */
    public function __isset( $prop ) {
        return isset( $this->{$prop} ) || isset( $this->container[ $prop ] );
    }
    /**
     * Define the constants
     *
     * @return void
     */
    public function define_constants() {
        define( 'AUTO_X_LINE_NAME', "Auto x LINE" );
        define( 'AUTO_X_LINE_VERSION', $this->version );
        define( 'AUTO_X_LINE_FILE', __FILE__ );
        define( 'AUTO_X_LINE_PATH', dirname( AUTO_X_LINE_FILE ) );
        define( 'AUTO_X_LINE_INCLUDES', AUTO_X_LINE_PATH . '/includes' );
        define( 'AUTO_X_LINE_URL', plugins_url( '', AUTO_X_LINE_FILE ) );
        define( 'AUTO_X_LINE_ASSETS', AUTO_X_LINE_URL . '/assets' );
        define( 'AUTO_X_LINE_PREFIX','ax8_');
    }
    /**
     * Load the plugin after all plugis are loaded
     *
     * @return void
     */
    public function init_plugin() {
        if(get_option( AUTO_X_LINE_PREFIX.'version' ) != AUTO_X_LINE_VERSION){
            ax8DeleteCacheData();
            $this->activate();
        }
        $this->includes();
        $this->init_hooks();
    }
    /**
     * Placeholder for activation function
     *
     * Nothing being called here yet.
     */
    public function activate() {
        $installed = get_option( AUTO_X_LINE_PREFIX.'installed' );
        if ( ! $installed ) {
            update_option( AUTO_X_LINE_PREFIX.'installed', time() );
            setAx8LanguageCookie("ja");
        }
        $this->createTables();
        wp_clear_scheduled_hook(AUTO_X_LINE_PREFIX.'schedule_tasks');
        wp_clear_scheduled_hook(AUTO_X_LINE_PREFIX.'schedule_tasks_ten_mins');
        if (!wp_next_scheduled(AUTO_X_LINE_PREFIX.'schedule_tasks'))
        {
            wp_schedule_event(time(), AUTO_X_LINE_PREFIX.'tasks', AUTO_X_LINE_PREFIX.'schedule_tasks');
        }
        if (!wp_next_scheduled(AUTO_X_LINE_PREFIX.'schedule_tasks_ten_mins'))
        {
            wp_schedule_event(time() + (60*2.5), AUTO_X_LINE_PREFIX.'tasks_ten_mins', AUTO_X_LINE_PREFIX.'schedule_tasks_ten_mins');
        }
        update_option( 'auto_x_line_version', AUTO_X_LINE_VERSION );
    }
    
    /**
    * Create Required Tables
    */
    public function createTables(){
        global $wpdb;
        $prefix = $wpdb->prefix.AUTO_X_LINE_PREFIX;
        $tbl_accounts = $prefix . 'accounts';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = [];
        $sql[] = "CREATE TABLE IF NOT EXISTS $tbl_accounts ( 
            `account_id` INT NOT NULL AUTO_INCREMENT,
            `user_id` INT NOT NULL DEFAULT '0',
            `account_type` VARCHAR(200) NULL DEFAULT NULL,
            `access_token` VARCHAR(255) NULL DEFAULT NULL,
            `where_to_post` VARCHAR(200) NULL DEFAULT NULL,
            `message_format` TEXT NULL DEFAULT NULL,
            `has_filter` INT(1) NOT NULL DEFAULT '0',
            `filters` MEDIUMTEXT NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`account_id`)
        ) $charset_collate;";
        $tbl_logs = $prefix . 'logs';
        $sql[] = "CREATE TABLE IF NOT EXISTS $tbl_logs ( 
            `log_id` INT NOT NULL AUTO_INCREMENT,
            `log_time` VARCHAR(200) NULL DEFAULT NULL,
            `log_type` VARCHAR(10) NOT NULL DEFAULT 'info',
            `message` MEDIUMTEXT NULL DEFAULT NULL,
            `account_id` INT NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`log_id`)
        ) $charset_collate;";
        $tbl_tasks = $prefix . 'tasks';
        $sql[] = "CREATE TABLE IF NOT EXISTS $tbl_tasks ( 
            `task_id` INT NOT NULL AUTO_INCREMENT,
            `thread_no` VARCHAR(255) NULL DEFAULT NULL,
            `account_id` INT NOT NULL,
            `post_id` INT NOT NULL,
            `request` TEXT NULL DEFAULT NULL,
            `response` TEXT NULL DEFAULT NULL,
            `time` TIMESTAMP NOT NULL,
            `time_gmt` TIMESTAMP NOT NULL,
            `started` TINYINT NOT NULL DEFAULT '0',
            `completed` TINYINT NOT NULL DEFAULT '0',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`task_id`)
        ) $charset_collate;";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        foreach ($sql as $query) {
            dbDelta( $query );
        }
        $this->addColumnIfNotExist($tbl_logs,'post_id',"INT NOT NULL DEFAULT 0",'log_id');
        $this->addColumnIfNotExist($tbl_accounts,'send_delay',"INT(6) NOT NULL DEFAULT 0",'filters');
    }
    /**
     * Add table column if not exists
     * 
    */
    function addColumnIfNotExist($table, $column, $column_attr = "VARCHAR( 255 ) NULL",$after_column='' ){
        global $wpdb;
        $exists = false;
        $columns = $wpdb->get_results($wpdb->prepare('show columns from %1s',$table),ARRAY_A);
        foreach ($columns as $c) {
          if(strtolower($c['Field']) == strtolower($column)){
            $exists = true;
            break;
          }
        }   
        if(!$exists){
          $query = "ALTER TABLE %1s ADD %1s %1s";
          $s_attr = [$table,$column,$column_attr];
          if($after_column != ''){
            $query .= " AFTER %1s";
            $s_attr[] = $after_column;
          }
          $wpdb->query($wpdb->prepare($query,$s_attr));
        }
      }
    /**
     * Placeholder for deactivation function
     *
     * Nothing being called here yet.
     */
    public function deactivate() {
        ax8DeleteCacheData();
        wp_clear_scheduled_hook(AUTO_X_LINE_PREFIX.'schedule_tasks');
        wp_clear_scheduled_hook(AUTO_X_LINE_PREFIX.'schedule_tasks_ten_mins');
    }
    /**
     * Include the required files
     *
     * @return void
     */
    public function includes() {
        require __DIR__ . '/vendor/autoload.php';
        require_once AUTO_X_LINE_INCLUDES . '/Ax8_Assets.php';
        if ( $this->is_request( 'admin' ) ) {
            require_once AUTO_X_LINE_INCLUDES . '/Ax8_Admin.php';
        }
        require_once AUTO_X_LINE_INCLUDES . '/Ax8_Hooks.php';
        require_once AUTO_X_LINE_INCLUDES . '/Ax8_Api.php';
    }
    /**
     * Initialize the hooks
     *
     * @return void
     */
    public function init_hooks() {
        add_action( 'init', array( $this, 'init_classes' ) );
        // Localize our plugin
        // add_action( 'init', array( $this, 'localization_setup' ) );
        //CRON Schedules
        add_filter('cron_schedules', array( $this, 'ax8_cron_schedules' ) );
    }
    /**
     * Instantiate the required classes
     *
     * @return void
     */
    public function init_classes() {
        new \Ax8\Ax8_Hooks(); //initalize Hooks class
        if ( $this->is_request( 'admin' ) ) {
            $this->container['admin'] = new \Ax8\Ax8_Admin();
        }
        if ( $this->is_request( 'frontend' ) ) {
            $this->container['frontend'] = new \Ax8\Ax8_Frontend();
        }
        // if ( $this->is_request( 'ajax' ) ) {
        //     // $this->container['ajax'] =  new App\Ajax();
        // }
        $this->container['api'] = new Ax8\Ax8_Api();
        $this->container['assets'] = new \Ax8\Ax8_Assets();
    }
    
    public function ax8_cron_schedules($schedules){
        $schedules[AUTO_X_LINE_PREFIX.'tasks'] = array(
            'interval' => 60,
            'display' => __('Auto X LINE Schedules Per Miniute')
        );
        $schedules[AUTO_X_LINE_PREFIX.'tasks_ten_mins'] = array(
            'interval' => (60 * 10),
            'display' => __('Auto X LINE Schedules Per 10 Minutes')
        );
        return $schedules;
    }
    /**
     * What type of request is this?
     *
     * @param  string $type admin, ajax, cron or frontend.
     *
     * @return bool
     */
    private function is_request( $type ) {
        switch ( $type ) {
            case 'admin' :
                return is_admin();
            case 'ajax' :
                return defined( 'DOING_AJAX' );
            case 'rest' :
                return defined( 'REST_REQUEST' );
            case 'cron' :
                return defined( 'DOING_CRON' );
            case 'frontend' :
                return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
        }
    }
} // Auto_x_line
/*
* Set Language Code in Cookies
*/
function setAx8LanguageCookie($lang){
    $cookie_name = AUTO_X_LINE_PREFIX.'lang';
    $updated = true;
    if(isset($_COOKIE[$cookie_name]) && $_COOKIE[$cookie_name] == $lang) {
        $updated = false;
    }
    if($updated){
        setcookie($cookie_name, $lang, strtotime("+6 months"), "/");
    }
    update_option(AUTO_X_LINE_PREFIX.'language',$lang);
    update_user_meta(get_current_user_id(),$cookie_name,$lang);
    return ['updated'=>$updated,'lang'=>$lang];
}
function ax8DeleteCacheData(){
    $languages = [
        'en'    => 'English',
        'ja'    => 'Japanese'
    ];
    $cookie_name = AUTO_X_LINE_PREFIX.'lang';
    foreach($languages as $lang_short_form=>$lang_name){
        $optionKey = "{$cookie_name}_{$lang_short_form}_updated";
        delete_option($optionKey);
        $cacheKey = "{$cookie_name}_translations_{$lang_short_form}";
        // wp_cache_delete($cacheKey);
        delete_transient($cacheKey);
    }
}
$auto_x_line = Auto_x_line::init();
