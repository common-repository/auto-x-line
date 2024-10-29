<?php
namespace Ax8;
use Ax8\Ax8_Helper as Helper;
/**
 * Admin Pages Handler
 */
class Ax8_Admin {
    public function __construct() {
        add_action( 'save_post', array($this, 'save_metadata'));
        add_action( 'add_meta_boxes', array($this, 'all_meta_boxes'), 9, 2);
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
    }
    /**
     * Register our menu page
     *
     * @return void
     */
    public function admin_menu() {
        global $submenu;
        $capability = 'manage_options';
        $slug       = 'auto-x-line';
        $fileData = file_get_contents(AUTO_X_LINE_PATH."/assets/images/w-ax8.svg");
        $icon = 'data:image/svg+xml;base64,'.base64_encode($fileData);
        $hook = add_menu_page( __( 'Auto x LINE', 'auto_x_line' ), __( 'Auto x LINE', 'auto_x_line' ), $capability, $slug, [ $this, 'plugin_page' ], $icon );
        if ( current_user_can( $capability ) ) {
            $append_str = "?".filemtime( AUTO_X_LINE_PATH . '/assets/js/vendors.js' );
            $submenu[ $slug ][] = array( Helper::trans('account'), $capability, 'admin.php?page=' . $slug . '#/accounts/form'.$append_str );
            $submenu[ $slug ][] = array( Helper::trans('log'), $capability, 'admin.php?page=' . $slug . '#/log-history'.$append_str );
            $submenu[ $slug ][] = array( Helper::trans('settings'), $capability, 'admin.php?page=' . $slug . '#/settings'.$append_str );
        }
        add_action( 'load-' . $hook, [ $this, 'init_hooks'] );
    }
    /**
     * Initialize our hooks for the admin page
     *
     * @return void
     */
    public function init_hooks() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'init', [ $this, 'actions' ] );
    }
    public function all_meta_boxes($post_type, $post) {
        $checkNew = false;
        if($post->post_status == 'auto-draft'){
            $checkNew = true;
        }
        if(Helper::checkPostMeta($post->ID,$checkNew) 
            && in_array($post_type,Helper::getPostTypes())
        ){
            add_meta_box(
                'auto-x-line-delay-metabox',
                AUTO_X_LINE_NAME." - ". Helper::trans('post_timing_options',Helper::getLocale()),
                array($this, 'render_all_meta_boxes'),
                $post_type,
                "side",
                "high"
            );
        }
    }
    public function render_all_meta_boxes() {
        global $post;
        $input_name = Helper::getMetaKey('send_delay_edit');//AUTO_X_LINE_PREFIX."send_delay_edit";
        $locale = Helper::getLocale();
        ?>
        <table class="wpematico-data-table">
            <tr>
                <td>
                    <input type="radio" value="with_delay" checked name="<?php echo $input_name; ?>" id="<?php echo $input_name; ?>with_delay" />
                </td>
                <td>
                    <label for="<?php echo $input_name; ?>with_delay"><b><?php echo Helper::trans('post_with_delay',$locale); ?> [<a target="_blank" href="<?php echo esc_attr(Helper::getMenuUrl('accounts/form')) ?>"><?php echo Helper::trans('account',$locale) ?></a>]</b></label>
                </td>
            </tr>
            <tr>
                <td>
                    <input type="radio" value="send_now" checked name="<?php echo $input_name; ?>" id="<?php echo $input_name; ?>send_now" />
                </td>
                <td>
                    <label for="<?php echo $input_name; ?>send_now"><b><?php echo Helper::trans('post_immediately',$locale); ?></b></label>
                </td>
            </tr>
            <tr>
                <td>
                    <input type="radio" value="dont_send" checked name="<?php echo $input_name; ?>" id="<?php echo $input_name; ?>dont_send" />
                </td>
                <td>
                    <label for="<?php echo $input_name; ?>dont_send"><b><?php echo Helper::trans('not_posting',$locale); ?></b></label>
                </td>
            </tr>
        </table>
        <?php
        wp_nonce_field( "save_metadata", AUTO_X_LINE_PREFIX.'meta_fields' );
        wp_nonce_field( "ax8OnSavePost", AUTO_X_LINE_PREFIX.'filter_fields' );
    }
    public function save_metadata($post_id) {
        $nonce = AUTO_X_LINE_PREFIX.'meta_fields';
        $is_valid_nonce = ( isset( $_POST[ $nonce ] ) && wp_verify_nonce( sanitize_text_field(wp_unslash($_POST [$nonce])), "save_metadata" ));
        if(!$is_valid_nonce){
            return $post_id;
        }
        global $post;
        if((defined('DOING_AJAX') && DOING_AJAX) || isset($_REQUEST['bulk_edit']) || (isset($_REQUEST['action']) && $_REQUEST['action'] == 'inline-save')) {
            return $post_id;
        }
        if(defined('DOING_CRON') && DOING_CRON) {
            return $post_id;
        }
        if((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || (defined('DOING_AJAX') && DOING_AJAX) || isset($_REQUEST['bulk_edit']))
            return $post_id;
        if(!empty($post->post_type) && $post->post_type == 'wpematico')
            return $post_id;
        
        $input_name                     = Helper::getMetaKey('send_delay_edit');//AUTO_X_LINE_PREFIX."send_delay_edit";
        $fields                         = array();
        $fields[$input_name] = ( isset($_POST[$input_name]) && !empty($_POST[$input_name]) ) ? sanitize_text_field($_POST[$input_name]) : false;
        add_post_meta($post_id, $input_name, $fields[$input_name], true) or update_post_meta($post_id, $input_name, $fields[$input_name]);
    }
    public function actions(){
    }
    /**
     * Load scripts and styles for the app
     *
     * @return void
     */
    public function enqueue_scripts() {
        wp_enqueue_style( AUTO_X_LINE_PREFIX.'admin' );
        wp_enqueue_style( AUTO_X_LINE_PREFIX.'vendor' );
        wp_enqueue_script( AUTO_X_LINE_PREFIX.'admin' );
    }
    /**
     * Render our admin page
     *
     * @return void
     */
    public function plugin_page() {
        echo htmlspecialchars_decode(esc_attr('<div class="wrap ax8-app"><div id="'.AUTO_X_LINE_PREFIX.'vue-admin-app"></div></div>'));
    }
}
