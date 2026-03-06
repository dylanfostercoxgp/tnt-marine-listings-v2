<?php
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

while ( have_posts() ) : the_post();
    $pid = get_the_ID();

    // Core fields
    $price    = get_post_meta( $pid, '_tnt_price',    true );
    $year     = get_post_meta( $pid, '_tnt_year',     true );
    $length   = get_post_meta( $pid, '_tnt_length',   true );
    $location = get_post_meta( $pid, '_tnt_location', true );
    $class    = get_post_meta( $pid, '_tnt_class',    true );
    $capacity = get_post_meta( $pid, '_tnt_capacity', true );
    $model    = get_post_meta( $pid, '_tnt_model',    true );
    $hours    = get_post_meta( $pid, '_tnt_hours',    true );

    // Measurements
    $length_overall = get_post_meta( $pid, '_tnt_length_overall', true );
    $beam           = get_post_meta( $pid, '_tnt_beam',           true );
    $dry_weight     = get_post_meta( $pid, '_tnt_dry_weight',     true );
    $fuel_tanks     = get_post_meta( $pid, '_tnt_fuel_tanks',     true );

    // Propulsion
    $engine_count = intval( get_post_meta( $pid, '_tnt_engine_count', true ) ) ?: 1;
    $engines = [];
    for ( $e = 1; $e <= $engine_count; $e++ ) {
        $engines[] = [
            'make'  => get_post_meta( $pid, '_tnt_engine_make_'  . $e, true ),
            'model' => get_post_meta( $pid, '_tnt_engine_model_' . $e, true ),
            'power' => get_post_meta( $pid, '_tnt_engine_power_' . $e, true ),
            'hours' => get_post_meta( $pid, '_tnt_engine_hours_' . $e, true ),
            'fuel'  => get_post_meta( $pid, '_tnt_engine_fuel_'  . $e, true ),
        ];
    }

    // Features
    $description      = get_post_meta( $pid, '_tnt_description',      true );
    $power_features   = get_post_meta( $pid, '_tnt_power_features',   true );
    $cockpit_features = get_post_meta( $pid, '_tnt_cockpit_features', true );
    $cabin_features   = get_post_meta( $pid, '_tnt_cabin_features',   true );
    $trailer_features = get_post_meta( $pid, '_tnt_trailer_features', true );
    $bonus            = get_post_meta( $pid, '_tnt_bonus',            true );

    // Gallery — featured image always first, then additional gallery images (no duplicates)
    $gallery_ids_raw  = get_post_meta( $pid, '_tnt_gallery_ids', true );
    $gallery_extra    = $gallery_ids_raw ? array_values( array_filter( array_map( 'intval', explode( ',', $gallery_ids_raw ) ) ) ) : [];
    $featured_id      = (int) get_post_thumbnail_id( $pid );
    if ( $featured_id ) {
        // Remove featured from gallery list if already present, then prepend it
        $gallery_extra = array_values( array_filter( $gallery_extra, function( $id ) use ( $featured_id ) { return $id !== $featured_id; } ) );
        $gallery_ids   = array_merge( [ $featured_id ], $gallery_extra );
    } else {
        $gallery_ids = $gallery_extra;
    }

    function tnt_feature_list( $raw ) {
        if ( ! $raw ) return '';
        $lines = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
        if ( empty( $lines ) ) return '';
        $out = '<ul class="tnt-feature-list">';
        foreach ( $lines as $line ) {
            $out .= '<li>' . esc_html( $line ) . '</li>';
        }
        $out .= '</ul>';
        return $out;
    }

    function tnt_accordion_section( $id, $title, $content, $open = false ) {
        if ( ! trim( $content ) ) return;
        $state = $open ? ' tnt-open' : '';
        echo '<div class="tnt-accordion-item' . $state . '">';
        echo '<button class="tnt-accordion-trigger" aria-expanded="' . ( $open ? 'true' : 'false' ) . '" data-target="acc-' . esc_attr( $id ) . '">';
        echo esc_html( $title );
        echo '<span class="tnt-accordion-icon"></span>';
        echo '</button>';
        echo '<div class="tnt-accordion-body" id="acc-' . esc_attr( $id ) . '" ' . ( $open ? '' : 'style="display:none;"' ) . '>';
        echo $content;
        echo '</div></div>';
    }
?>

<div class="tnt-single-wrap">

    <!-- HERO -->
    <div class="tnt-hero">
        <?php if ( ! empty( $gallery_ids ) ) :
            $main_img = wp_get_attachment_image_url( $gallery_ids[0], 'full' );
        ?>
        <div class="tnt-gallery">
            <div class="tnt-gallery-main">
                <img id="tnt-main-photo" src="<?php echo esc_url( $main_img ); ?>" alt="<?php the_title_attribute(); ?>">
                <?php if ( count( $gallery_ids ) > 1 ) : ?>
                    <button class="tnt-gallery-nav tnt-prev" aria-label="Previous photo">&#8249;</button>
                    <button class="tnt-gallery-nav tnt-next" aria-label="Next photo">&#8250;</button>
                <?php endif; ?>
            </div>
            <?php if ( count( $gallery_ids ) > 1 ) : ?>
            <div class="tnt-gallery-thumbs">
                <?php foreach ( $gallery_ids as $i => $gid ) :
                    $thumb_url = wp_get_attachment_image_url( $gid, 'medium' );
                    $full_url  = wp_get_attachment_image_url( $gid, 'full' );
                ?>
                <img
                    src="<?php echo esc_url( $thumb_url ); ?>"
                    data-full="<?php echo esc_url( $full_url ); ?>"
                    class="tnt-thumb <?php echo $i === 0 ? 'active' : ''; ?>"
                    alt="Photo <?php echo $i + 1; ?>"
                    data-index="<?php echo $i; ?>"
                >
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- OVERVIEW PANEL -->
        <div class="tnt-overview-panel">
            <div class="tnt-overview-header">
                <h1 class="tnt-listing-title"><?php the_title(); ?></h1>
                <?php if ( $location ) : ?>
                    <p class="tnt-listing-location"><?php echo esc_html( $location ); ?></p>
                <?php endif; ?>
                <?php if ( $price ) : ?>
                    <p class="tnt-listing-price">$<?php echo number_format( floatval( $price ) ); ?></p>
                <?php endif; ?>
            </div>
            <div class="tnt-overview-specs">
                <?php
                $spec_items = [
                    [ 'Engine',          $engines[0]['make'] ?? '' . ' ' . ($engines[0]['model'] ?? '') ],
                    [ 'Total Power',     array_sum( array_column( $engines, 'power' ) ) ? array_sum( array_column( $engines, 'power' ) ) . 'hp' : '' ],
                    [ 'Engine Hours',    $hours ? $hours : '' ],
                    [ 'Class',           $class ],
                    [ 'Length',          $length ? $length . 'ft' : '' ],
                    [ 'Year',            $year ],
                    [ 'Model',           $model ],
                    [ 'Capacity',        $capacity ],
                ];
                foreach ( $spec_items as $spec ) :
                    if ( ! trim( $spec[1] ) ) continue;
                ?>
                <div class="tnt-spec-item">
                    <span class="tnt-spec-label"><?php echo esc_html( $spec[0] ); ?></span>
                    <span class="tnt-spec-value"><?php echo esc_html( $spec[1] ); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <a href="#tnt-inquiry" class="tnt-btn-primary tnt-cta-btn">Inquire About This Vessel</a>
        </div>
    </div>

    <!-- ACCORDION SECTIONS -->
    <div class="tnt-detail-sections">

        <?php
        // Description
        if ( $description ) {
            tnt_accordion_section( 'desc', 'Description', '<div class="tnt-description-text">' . wp_kses_post( $description ) . '</div>', true );
        }

        // Measurements
        $meas_html = '';
        if ( $length || $length_overall || $beam || $dry_weight || $fuel_tanks ) {
            $meas_html .= '<div class="tnt-spec-grid tnt-two-col">';
            $meas_html .= '<div><h4>Dimensions</h4><table class="tnt-spec-table">';
            if ( $length )         $meas_html .= '<tr><td>Length</td><td>' . esc_html( $length ) . 'ft</td></tr>';
            if ( $length_overall ) $meas_html .= '<tr><td>Length Overall</td><td>' . esc_html( $length_overall ) . 'ft</td></tr>';
            if ( $beam )           $meas_html .= '<tr><td>Beam</td><td>' . esc_html( $beam ) . 'ft</td></tr>';
            $meas_html .= '</table></div>';
            $meas_html .= '<div>';
            if ( $dry_weight ) $meas_html .= '<h4>Weights</h4><table class="tnt-spec-table"><tr><td>Dry Weight</td><td>' . esc_html( $dry_weight ) . ' Lb</td></tr></table>';
            if ( $fuel_tanks ) $meas_html .= '<h4>Tanks</h4><table class="tnt-spec-table"><tr><td>Fuel Tanks</td><td>' . esc_html( $fuel_tanks ) . ' gal</td></tr></table>';
            $meas_html .= '</div></div>';
            tnt_accordion_section( 'meas', 'Measurements & Weights', $meas_html );
        }

        // Propulsion
        $prop_html = '<div class="tnt-spec-grid tnt-multi-col">';
        foreach ( $engines as $i => $eng ) {
            $has_data = array_filter( $eng );
            if ( ! $has_data ) continue;
            $prop_html .= '<div><h4>Engine ' . ( $i + 1 ) . '</h4><table class="tnt-spec-table">';
            if ( $eng['make']  ) $prop_html .= '<tr><td>Engine Make</td><td>' . esc_html( $eng['make'] )  . '</td></tr>';
            if ( $eng['model'] ) $prop_html .= '<tr><td>Engine Model</td><td>' . esc_html( $eng['model'] ) . '</td></tr>';
            if ( $eng['power'] ) $prop_html .= '<tr><td>Total Power</td><td>' . esc_html( $eng['power'] ) . 'hp</td></tr>';
            if ( $eng['hours'] ) $prop_html .= '<tr><td>Engine Hours</td><td>' . esc_html( $eng['hours'] ) . '</td></tr>';
            if ( $eng['fuel']  ) $prop_html .= '<tr><td>Fuel Type</td><td>'    . esc_html( $eng['fuel'] )  . '</td></tr>';
            $prop_html .= '</table></div>';
        }
        $prop_html .= '</div>';
        if ( $engine_count ) tnt_accordion_section( 'prop', 'Propulsion', $prop_html );

        // Feature sections
        if ( $power_features )   tnt_accordion_section( 'power',   'Key Features',       tnt_feature_list( $power_features ) );
        if ( $cockpit_features ) tnt_accordion_section( 'cockpit', 'Additional Details', tnt_feature_list( $cockpit_features ) );
        if ( $cabin_features )   tnt_accordion_section( 'cabin',   'Condition & History',tnt_feature_list( $cabin_features ) );
        if ( $trailer_features ) tnt_accordion_section( 'trailer', 'Included Items',     tnt_feature_list( $trailer_features ) );
        if ( $bonus ) {
            tnt_accordion_section( 'bonus', 'Notes / Disclaimers', '<p class="tnt-bonus-text">' . esc_html( $bonus ) . '</p>' );
        }
        ?>

    </div>

    <!-- INQUIRY FORM -->
    <?php echo tnt_marine_inquiry_form( $pid, get_the_title() ); ?>

    <p class="tnt-back-link"><a href="https://tntcustommarine.com/tnt-marine-listings/">&larr; Back to All Listings</a></p>

</div>

<?php endwhile; ?>

<?php get_footer(); ?>
