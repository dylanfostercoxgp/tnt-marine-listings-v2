<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tnt_marine_register_post_type() {
    $labels = [
        'name'               => 'Marine Listings',
        'singular_name'      => 'Marine Listing',
        'add_new'            => 'Add New Listing',
        'add_new_item'       => 'Add New Marine Listing',
        'edit_item'          => 'Edit Marine Listing',
        'new_item'           => 'New Marine Listing',
        'view_item'          => 'View Listing',
        'search_items'       => 'Search Listings',
        'not_found'          => 'No listings found',
        'not_found_in_trash' => 'No listings found in Trash',
        'menu_name'          => 'Marine Listings',
    ];

    $args = [
        'labels'             => $labels,
        'public'             => true,
        'has_archive'        => true,
        'rewrite'            => [ 'slug' => 'listings' ],
        'supports'           => [ 'title', 'thumbnail' ],
        'menu_icon'          => 'dashicons-admin-site-alt',
        'show_in_rest'       => false,
    ];

    register_post_type( 'marine_listing', $args );
}
add_action( 'init', 'tnt_marine_register_post_type' );

function tnt_marine_activation() {
    tnt_marine_register_post_type();
    flush_rewrite_rules();
}
register_activation_hook( TNT_MARINE_PATH . 'tnt-marine-listings.php', 'tnt_marine_activation' );

function tnt_marine_deactivation() {
    flush_rewrite_rules();
}
register_deactivation_hook( TNT_MARINE_PATH . 'tnt-marine-listings.php', 'tnt_marine_deactivation' );
