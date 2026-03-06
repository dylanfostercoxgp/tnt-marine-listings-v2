<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tnt_marine_template_loader( $template ) {
    if ( is_singular( 'marine_listing' ) ) {
        $plugin_template = TNT_MARINE_PATH . 'templates/single-marine_listing.php';
        if ( file_exists( $plugin_template ) ) {
            return $plugin_template;
        }
    }
    return $template;
}
add_filter( 'template_include', 'tnt_marine_template_loader' );
