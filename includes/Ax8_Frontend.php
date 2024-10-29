<?php
namespace Ax8;
/**
 * Frontend Pages Handler
 */
class Ax8_Frontend {
    public function __construct() {
        add_shortcode( AUTO_X_LINE_PREFIX.'vue-app', [ $this, 'render_frontend' ] );
    }
    /**
     * Render frontend app
     *
     * @param  array $atts
     * @param  string $content
     *
     * @return string
     */
    public function render_frontend( $atts, $content = '' ) {
        wp_enqueue_style( AUTO_X_LINE_PREFIX.'frontend' );
        wp_enqueue_script( AUTO_X_LINE_PREFIX.'frontend' );
        $content .= '<div id="'.AUTO_X_LINE_PREFIX.'frontend-app"></div>';
        return $content;
    }
}
