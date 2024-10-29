<?php
namespace Ax8;
use WP_REST_Controller;
/**
 * REST_API Handler
 */
class Ax8_Api extends WP_REST_Controller {
    /**
     * [__construct description]
     */
    public function __construct() {
        $this->includes();
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }
    /**
     * Include the controller classes
     *
     * @return void
     */
    private function includes() {
        if ( !class_exists( __NAMESPACE__ . '\Api\Ax8_Accounts'  ) ) {
            require_once __DIR__ . '/Api/Ax8_Accounts.php';
        }
        if ( !class_exists( __NAMESPACE__ . '\Api\Ax8_Settings'  ) ) {
            require_once __DIR__ . '/Api/Ax8_Settings.php';
        }
    }
    /**
     * Register the API routes
     *
     * @return void
     */
    public function register_routes() {
        (new \Ax8\Api\Ax8_Accounts())->register_routes();
        (new \Ax8\Api\Ax8_Settings())->register_routes();
    }
}
