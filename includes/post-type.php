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
        'supports'           => [ 'title', 'editor', 'thumbnail' ],
        'menu_icon'          => 'dashicons-admin-site-alt',
        'show_in_rest'       => true,
        'rest_base'          => 'marine_listing',
    ];

    register_post_type( 'marine_listing', $args );
}
add_action( 'init', 'tnt_marine_register_post_type' );

/**
 * Register all _tnt_ meta fields for REST API access.
 * Without this, WordPress silently ignores meta sent via the REST API.
 */
function tnt_marine_register_meta_fields() {
    $string_fields = [
        '_tnt_id',
        '_tnt_year',
        '_tnt_make',
        '_tnt_model',
        '_tnt_price',
        '_tnt_length',
        '_tnt_location',
        '_tnt_class',
        '_tnt_capacity',
        '_tnt_hours',
        '_tnt_length_overall',
        '_tnt_beam',
        '_tnt_dry_weight',
        '_tnt_fuel_tanks',
        '_tnt_engine_count',
        '_tnt_engine_1_make',
        '_tnt_engine_1_model',
        '_tnt_engine_1_power',
        '_tnt_engine_1_hours',
        '_tnt_engine_1_fuel',
        '_tnt_engine_2_make',
        '_tnt_engine_2_model',
        '_tnt_engine_2_power',
        '_tnt_engine_2_hours',
        '_tnt_engine_2_fuel',
        '_tnt_engine_3_make',
        '_tnt_engine_3_model',
        '_tnt_engine_3_power',
        '_tnt_engine_3_hours',
        '_tnt_engine_3_fuel',
        '_tnt_engine_4_make',
        '_tnt_engine_4_model',
        '_tnt_engine_4_power',
        '_tnt_engine_4_hours',
        '_tnt_engine_4_fuel',
        '_tnt_engine_5_make',
        '_tnt_engine_5_model',
        '_tnt_engine_5_power',
        '_tnt_engine_5_hours',
        '_tnt_engine_5_fuel',
        '_tnt_key_features',
        '_tnt_notes',
        '_tnt_photos',
    ];

    foreach ( $string_fields as $key ) {
        register_post_meta( 'marine_listing', $key, [
            'show_in_rest'  => true,
            'single'        => true,
            'type'          => 'string',
            'auth_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
        ] );
    }
}
add_action( 'init', 'tnt_marine_register_meta_fields' );

function tnt_marine_activation() {
    tnt_marine_register_post_type();
    flush_rewrite_rules();
}
register_activation_hook( TNT_MARINE_PATH . 'tnt-marine-listings.php', 'tnt_marine_activation' );

function tnt_marine_deactivation() {
    flush_rewrite_rules();
}
register_deactivation_hook( TNT_MARINE_PATH . 'tnt-marine-listings.php', 'tnt_marine_deactivation' );
