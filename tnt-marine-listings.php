<?php
/**
 * Plugin Name: TNT Marine Listings
 * Plugin URI:  https://ideaboss.io/
 * Description: Marine vessel listings with gallery, specs, sorting, and inquiry forms.
 * Version:     1.2.6
 * Author:      ideaBoss
 * Author URI:  https://ideaboss.io/
 * License:     GPL-2.0+
 * Text Domain: tnt-marine
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TNT_MARINE_VERSION', '1.2.5' );
define( 'TNT_MARINE_PATH',    plugin_dir_path( __FILE__ ) );
define( 'TNT_MARINE_URL',     plugin_dir_url( __FILE__ ) );

require_once TNT_MARINE_PATH . 'includes/post-type.php';
require_once TNT_MARINE_PATH . 'includes/meta-boxes.php';
require_once TNT_MARINE_PATH . 'includes/shortcodes.php';
require_once TNT_MARINE_PATH . 'includes/inquiry-form.php';
require_once TNT_MARINE_PATH . 'includes/template-loader.php';
require_once TNT_MARINE_PATH . 'includes/settings.php';

// GitHub auto-updater – enables one-click updates from the WP admin Plugins screen.
if ( is_admin() ) {
    require_once TNT_MARINE_PATH . 'includes/class-github-updater.php';
    new TNT_GitHub_Updater( __FILE__ );
}

// Google Drive auto-sync – watches a Drive folder and auto-posts boat listings.
require_once TNT_MARINE_PATH . 'includes/class-drive-sync.php';
new TNT_Drive_Sync();

// Clear the Drive sync cron on deactivation.
register_deactivation_hook( __FILE__, [ 'TNT_Drive_Sync', 'clear_cron' ] );

function tnt_marine_enqueue_assets() {
    wp_enqueue_style(
        'tnt-marine-style',
        TNT_MARINE_URL . 'assets/css/tnt-marine.css',
        [],
        TNT_MARINE_VERSION
    );
    wp_enqueue_script(
        'tnt-marine-script',
        TNT_MARINE_URL . 'assets/js/tnt-marine.js',
        [ 'jquery' ],
        TNT_MARINE_VERSION,
        true
    );
    wp_localize_script( 'tnt-marine-script', 'tntMarine', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'tnt_marine_nonce' ),
    ]);
}
add_action( 'wp_enqueue_scripts', 'tnt_marine_enqueue_assets' );

function tnt_marine_admin_enqueue_assets( $hook ) {
    global $post;
    if ( ( $hook === 'post.php' || $hook === 'post-new.php' ) && isset( $post ) && $post->post_type === 'marine_listing' ) {
        wp_enqueue_media();
        wp_enqueue_script(
            'tnt-marine-admin',
            TNT_MARINE_URL . 'assets/js/tnt-marine-admin.js',
            [ 'jquery' ],
            TNT_MARINE_VERSION,
            true
        );
        wp_enqueue_style(
            'tnt-marine-admin-style',
            TNT_MARINE_URL . 'assets/css/tnt-marine-admin.css',
            [],
            TNT_MARINE_VERSION
        );
    }
}
add_action( 'admin_enqueue_scripts', 'tnt_marine_admin_enqueue_assets' );
