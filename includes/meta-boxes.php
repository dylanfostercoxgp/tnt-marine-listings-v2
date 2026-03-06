<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tnt_marine_add_meta_boxes() {
    add_meta_box( 'tnt_listing_status',      'Listing Status',         'tnt_meta_status_cb',      'marine_listing', 'side',   'high' );
    add_meta_box( 'tnt_listing_overview',    'Overview',               'tnt_meta_overview_cb',    'marine_listing', 'normal', 'high' );
    add_meta_box( 'tnt_listing_measurements','Specifications',         'tnt_meta_measurements_cb','marine_listing', 'normal', 'high' );
    add_meta_box( 'tnt_listing_propulsion',  'Propulsion',             'tnt_meta_propulsion_cb',  'marine_listing', 'normal', 'high' );
    add_meta_box( 'tnt_listing_features',    'Features & Description', 'tnt_meta_features_cb',    'marine_listing', 'normal', 'high' );
    add_meta_box( 'tnt_listing_gallery',     'Photo Gallery',          'tnt_meta_gallery_cb',     'marine_listing', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'tnt_marine_add_meta_boxes' );

/* ------------------------------------------------------------------ helpers */
function tnt_field( $post_id, $key ) {
    return esc_html( get_post_meta( $post_id, $key, true ) );
}
function tnt_input( $post_id, $key, $label, $type = 'text', $placeholder = '' ) {
    $value = esc_attr( get_post_meta( $post_id, $key, true ) );
    echo '<p><label><strong>' . esc_html( $label ) . '</strong><br>';
    echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $key ) . '" value="' . $value . '" placeholder="' . esc_attr( $placeholder ) . '" class="widefat"></label></p>';
}
function tnt_textarea( $post_id, $key, $label, $rows = 6 ) {
    $value = esc_textarea( get_post_meta( $post_id, $key, true ) );
    echo '<p><label><strong>' . esc_html( $label ) . '</strong><br>';
    echo '<textarea name="' . esc_attr( $key ) . '" rows="' . intval( $rows ) . '" class="widefat">' . $value . '</textarea></label></p>';
}
function tnt_nonce( $action ) {
    wp_nonce_field( $action, $action . '_nonce' );
}

/* ------------------------------------------------------------------ status */
function tnt_meta_status_cb( $post ) {
    tnt_nonce( 'tnt_listing_status' );
    $sold = get_post_meta( $post->ID, '_tnt_sold', true );
    echo '<p><label><input type="checkbox" name="_tnt_sold" value="1" ' . checked( $sold, '1', false ) . '> Mark as Sold (hides listing)</label></p>';
}

/* ------------------------------------------------------------------ overview */
function tnt_meta_overview_cb( $post ) {
    tnt_nonce( 'tnt_listing_overview' );
    $pid = $post->ID;
    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 20px;">';
    tnt_input( $pid, '_tnt_price',    'Price ($)',         'number', '699000' );
    tnt_input( $pid, '_tnt_year',     'Year',              'number', '2025' );
    tnt_input( $pid, '_tnt_length',   'Length (ft)',       'number', '39' );
    tnt_input( $pid, '_tnt_location', 'Location',          'text',   'Miami, FL' );
    tnt_input( $pid, '_tnt_class',    'Class / Type',      'text',   'Center Console' );
    tnt_input( $pid, '_tnt_capacity', 'Capacity',          'text',   '' );
    tnt_input( $pid, '_tnt_model',    'Model',             'text',   '390 Sport Center Console' );
    tnt_input( $pid, '_tnt_hours',    'Engine(s) Hours',   'number', '180' );
    echo '</div>';
}

/* ------------------------------------------------------------------ measurements */
function tnt_meta_measurements_cb( $post ) {
    tnt_nonce( 'tnt_listing_measurements' );
    $pid = $post->ID;
    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 20px;">';
    tnt_input( $pid, '_tnt_length_overall', 'Length Overall (ft)', 'text', '39' );
    tnt_input( $pid, '_tnt_beam',           'Beam (ft)',           'text', '10' );
    tnt_input( $pid, '_tnt_dry_weight',     'Dry Weight (lbs)',    'text', '12500' );
    tnt_input( $pid, '_tnt_fuel_tanks',     'Fuel Tanks (gal)',    'text', '285' );
    echo '</div>';
}

/* ------------------------------------------------------------------ propulsion */
function tnt_meta_propulsion_cb( $post ) {
    tnt_nonce( 'tnt_listing_propulsion' );
    $pid    = $post->ID;
    $engines = intval( get_post_meta( $pid, '_tnt_engine_count', true ) ) ?: 1;

    echo '<p><label><strong>Number of Engines</strong><br>';
    echo '<select name="_tnt_engine_count" id="tnt_engine_count" class="widefat">';
    for ( $i = 1; $i <= 6; $i++ ) {
        echo '<option value="' . $i . '" ' . selected( $engines, $i, false ) . '>' . $i . '</option>';
    }
    echo '</select></label></p>';

    $fields = [
        '_tnt_engine_make'  => 'Engine Make',
        '_tnt_engine_model' => 'Engine Model',
        '_tnt_engine_power' => 'Power (hp)',
        '_tnt_engine_hours' => 'Hours',
        '_tnt_engine_fuel'  => 'Fuel Type',
    ];

    for ( $e = 1; $e <= 6; $e++ ) {
        $display = $e <= $engines ? '' : ' style="display:none;"';
        echo '<div class="tnt-engine-block" data-engine="' . $e . '"' . $display . '>';
        echo '<h4 style="border-top:1px solid #ddd;padding-top:10px;">Engine ' . $e . '</h4>';
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 20px;">';
        foreach ( $fields as $key => $label ) {
            $field_key = $key . '_' . $e;
            $value     = esc_attr( get_post_meta( $pid, $field_key, true ) );
            echo '<p><label><strong>' . esc_html( $label ) . '</strong><br>';
            echo '<input type="text" name="' . esc_attr( $field_key ) . '" value="' . $value . '" class="widefat"></label></p>';
        }
        echo '</div></div>';
    }
}

/* ------------------------------------------------------------------ features */
function tnt_meta_features_cb( $post ) {
    tnt_nonce( 'tnt_listing_features' );
    $pid = $post->ID;
    tnt_textarea( $pid, '_tnt_description',    'Description (HTML or plain text)', 8 );
    tnt_textarea( $pid, '_tnt_power_features', 'Key Features (one per line — highlights buyers will care most about)', 6 );
    tnt_textarea( $pid, '_tnt_bonus',          'Notes / Disclaimers', 3 );
}

/* ------------------------------------------------------------------ gallery */
function tnt_meta_gallery_cb( $post ) {
    tnt_nonce( 'tnt_listing_gallery' );
    $gallery_ids = get_post_meta( $post->ID, '_tnt_gallery_ids', true );
    ?>
    <div id="tnt-gallery-wrap">
        <p style="color:#666;font-size:12px;margin:0 0 10px;">Drag thumbnails to reorder. The first image will be used as the main photo on the listing page and the card thumbnail on the listings grid.</p>
        <ul id="tnt-gallery-preview" style="list-style:none;margin:0 0 12px;padding:0;display:flex;flex-wrap:wrap;gap:8px;min-height:70px;">
            <?php
            if ( $gallery_ids ) {
                $ids = array_filter( array_map( 'intval', explode( ',', $gallery_ids ) ) );
                foreach ( $ids as $id ) {
                    $img = wp_get_attachment_image( $id, [80, 60] );
                    echo '<li class="tnt-gallery-thumb" data-id="' . intval( $id ) . '" style="position:relative;cursor:grab;list-style:none;">'
                       . $img
                       . '<span class="tnt-remove-img" title="Remove" style="position:absolute;top:0;right:0;background:#c00;color:#fff;font-size:10px;padding:1px 5px;cursor:pointer;border-radius:0 0 0 3px;line-height:16px;">&#10005;</span>'
                       . '</li>';
                }
            }
            ?>
        </ul>
        <input type="hidden" id="tnt_gallery_ids" name="_tnt_gallery_ids" value="<?php echo esc_attr( $gallery_ids ); ?>">
        <button type="button" id="tnt-add-gallery" class="button">Add / Edit Photos</button>
    </div>
    <?php
}

/* ------------------------------------------------------------------ save */
function tnt_marine_save_meta( $post_id ) {
    $nonces = [
        'tnt_listing_status',
        'tnt_listing_overview',
        'tnt_listing_measurements',
        'tnt_listing_propulsion',
        'tnt_listing_features',
        'tnt_listing_gallery',
    ];

    $valid = false;
    foreach ( $nonces as $n ) {
        if ( isset( $_POST[ $n . '_nonce' ] ) && wp_verify_nonce( $_POST[ $n . '_nonce' ], $n ) ) {
            $valid = true;
        }
    }
    if ( ! $valid ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $text_fields = [
        '_tnt_sold', '_tnt_price', '_tnt_year', '_tnt_length', '_tnt_location',
        '_tnt_class', '_tnt_capacity', '_tnt_model', '_tnt_hours',
        '_tnt_length_overall', '_tnt_beam', '_tnt_dry_weight', '_tnt_fuel_tanks',
        '_tnt_engine_count',
        '_tnt_description', '_tnt_power_features', '_tnt_cockpit_features',
        '_tnt_cabin_features', '_tnt_trailer_features', '_tnt_bonus',
        '_tnt_gallery_ids',
    ];

    // Engine fields up to 6
    for ( $e = 1; $e <= 6; $e++ ) {
        foreach ( ['_tnt_engine_make','_tnt_engine_model','_tnt_engine_power','_tnt_engine_hours','_tnt_engine_fuel'] as $k ) {
            $text_fields[] = $k . '_' . $e;
        }
    }

    foreach ( $text_fields as $field ) {
        if ( $field === '_tnt_sold' ) {
            update_post_meta( $post_id, $field, isset( $_POST[ $field ] ) ? '1' : '' );
        } elseif ( $field === '_tnt_description' ) {
            update_post_meta( $post_id, $field, wp_kses_post( wp_unslash( $_POST[ $field ] ?? '' ) ) );
        } else {
            update_post_meta( $post_id, $field, sanitize_textarea_field( wp_unslash( $_POST[ $field ] ?? '' ) ) );
        }
    }
}
add_action( 'save_post_marine_listing', 'tnt_marine_save_meta' );
