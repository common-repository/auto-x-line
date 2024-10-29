<?php
namespace Ax8;
/**
 * Scripts and Styles Class
 */
class Ax8_Assets {
    function __construct() {
        if ( is_admin() ) {
            add_action( 'admin_enqueue_scripts', [ $this, 'register' ], 5 );
        } else {
            add_action( 'wp_enqueue_scripts', [ $this, 'register' ], 5 );
        }
    }
    /**
     * Register our app scripts and styles
     *
     * @return void
     */
    public function register() {
        $this->register_scripts( $this->get_scripts() );
        $this->register_styles( $this->get_styles() );
    }
    /**
     * Register scripts
     *
     * @param  array $scripts
     *
     * @return void
     */
    private function register_scripts( $scripts ) {
        foreach ( $scripts as $handle => $script ) {
            $deps      = isset( $script['deps'] ) ? $script['deps'] : false;
            $in_footer = isset( $script['in_footer'] ) ? $script['in_footer'] : false;
            $version   = isset( $script['version'] ) ? $script['version'] : AUTO_X_LINE_VERSION;
            wp_register_script( $handle, $script['src'], $deps, $version, $in_footer );
        }
        wp_localize_script( AUTO_X_LINE_PREFIX.'vendor', 'ax8',
            array( 
                'baseurl'   => site_url(),
                'ajaxurl'   => admin_url( 'admin-ajax.php' ),
                'security'  => wp_create_nonce( 'wp_rest' ),
            )
        );
    }
    /**
     * Register styles
     *
     * @param  array $styles
     *
     * @return void
     */
    public function register_styles( $styles ) {
        foreach ( $styles as $handle => $style ) {
            $deps       = isset( $style['deps'] ) ? $style['deps'] : false;
            $version    = isset( $style['version'] ) ? $style['version'] : AUTO_X_LINE_VERSION;
            wp_register_style( $handle, $style['src'], $deps, $version );
        }
    }
    /**
     * Get all registered scripts
     *
     * @return array
     */
    public function get_scripts() {
        $prefix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
        $scripts = [
            AUTO_X_LINE_PREFIX.'runtime' => [
                'src'       => AUTO_X_LINE_ASSETS . '/js/runtime'.$prefix.'.js',
                'version'   => filemtime( AUTO_X_LINE_PATH . '/assets/js/runtime'.$prefix.'.js' ),
                'in_footer' => true
            ],
            AUTO_X_LINE_PREFIX.'vendor' => [
                'src'       => AUTO_X_LINE_ASSETS . '/js/vendors'.$prefix.'.js',
                'version'   => filemtime( AUTO_X_LINE_PATH . '/assets/js/vendors'.$prefix.'.js' ),
                'in_footer' => true
            ],
            AUTO_X_LINE_PREFIX.'admin' => [
                'src'       => AUTO_X_LINE_ASSETS . '/js/admin'.$prefix.'.js',
                'deps'      => [ 'jquery', AUTO_X_LINE_PREFIX.'vendor', AUTO_X_LINE_PREFIX.'runtime' ],
                'version'   => filemtime( AUTO_X_LINE_PATH . '/assets/js/admin'.$prefix.'.js' ),
                'in_footer' => true
            ]
        ];
        return $scripts;
    }
    /**
     * Get registered styles
     *
     * @return array
     */
    public function get_styles() {
        $styles = [
            AUTO_X_LINE_PREFIX.'vendor' => [
                'src'       => AUTO_X_LINE_ASSETS . '/css/vendors.css',
                'version'   => filemtime( AUTO_X_LINE_PATH . '/assets/css/vendors.css' ),
            ],
        ];
        return $styles;
    }
}
