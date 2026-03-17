<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* =========================================================================
   Shared card renderer (used by both shortcodes)
   ========================================================================= */

function tnt_marine_render_card( $pid, $opts = [] ) {
    $settings = tnt_marine_get_settings();
    $price    = get_post_meta( $pid, '_tnt_price',    true );
    $year     = get_post_meta( $pid, '_tnt_year',     true );
    $length   = get_post_meta( $pid, '_tnt_length',   true );
    $location = get_post_meta( $pid, '_tnt_location', true );
    $sold     = get_post_meta( $pid, '_tnt_sold',     true );

    $thumb_id = (int) get_post_thumbnail_id( $pid );
    if ( ! $thumb_id ) {
        $gallery_ids = get_post_meta( $pid, '_tnt_gallery_ids', true );
        if ( $gallery_ids ) {
            $ids      = array_filter( array_map( 'intval', explode( ',', $gallery_ids ) ) );
            $thumb_id = reset( $ids );
        }
    }
    $img_url = $thumb_id
        ? wp_get_attachment_image_url( $thumb_id, 'large' )
        : TNT_MARINE_URL . 'assets/img/placeholder.jpg';

    $show_sold_badge = ! empty( $opts['show_sold_badge'] );
    $badge_text  = esc_html( $settings['sold_badge_text'] ?: 'SOLD' );
    $badge_color = esc_attr( $settings['sold_badge_color'] ?: '#cc2129' );
    ?>
    <a class="tnt-card" href="<?php the_permalink(); ?>">
        <div class="tnt-card-image" style="background-image:url('<?php echo esc_url( $img_url ); ?>')">
            <?php if ( $show_sold_badge ) : ?>
                <span class="tnt-sold-badge" style="background:<?php echo $badge_color; ?>;"><?php echo $badge_text; ?></span>
            <?php endif; ?>
        </div>
        <div class="tnt-card-body">
            <h3 class="tnt-card-title"><?php the_title(); ?></h3>
            <?php if ( $location && $settings['show_location'] ) : ?>
                <p class="tnt-card-location"><?php echo esc_html( $location ); ?></p>
            <?php endif; ?>
            <div class="tnt-card-specs">
                <?php if ( $year   && $settings['show_year']   ) : ?><span><?php echo esc_html( $year ); ?></span><?php endif; ?>
                <?php if ( $length && $settings['show_length'] ) : ?><span><?php echo esc_html( $length ); ?>ft</span><?php endif; ?>
            </div>
            <?php if ( $price && $settings['show_price'] && empty( $opts['hide_price'] ) ) : ?>
                <p class="tnt-card-price">$<?php echo number_format( floatval( $price ) ); ?></p>
            <?php endif; ?>
        </div>
    </a>
    <?php
}

/* =========================================================================
   [marine_listings] – Active inventory
   Usage: [marine_listings per_page="12"]
   ========================================================================= */

function tnt_marine_listings_shortcode( $atts ) {
    $settings = tnt_marine_get_settings();
    $atts     = shortcode_atts( [ 'per_page' => intval( $settings['listings_per_page'] ) ?: 12 ], $atts, 'marine_listings' );

    $sort    = isset( $_GET['tnt_sort'] )    ? sanitize_text_field( $_GET['tnt_sort'] ) : 'date_desc';
    $paged   = max( 1, get_query_var( 'paged' ) );

    $price_min  = isset( $_GET['tnt_price_min'] )  ? intval( $_GET['tnt_price_min'] )  : '';
    $price_max  = isset( $_GET['tnt_price_max'] )  ? intval( $_GET['tnt_price_max'] )  : '';
    $length_min = isset( $_GET['tnt_length_min'] ) ? intval( $_GET['tnt_length_min'] ) : '';
    $length_max = isset( $_GET['tnt_length_max'] ) ? intval( $_GET['tnt_length_max'] ) : '';
    $year_min   = isset( $_GET['tnt_year_min'] )   ? intval( $_GET['tnt_year_min'] )   : '';
    $year_max   = isset( $_GET['tnt_year_max'] )   ? intval( $_GET['tnt_year_max'] )   : '';
    $hours_max  = isset( $_GET['tnt_hours_max'] )  ? intval( $_GET['tnt_hours_max'] )  : '';

    [ $orderby, $order, $meta_key ] = tnt_marine_sort_args( $sort );

    $meta_query = [
        'relation' => 'AND',
        [ 'key' => '_tnt_sold', 'compare' => '!=', 'value' => '1' ],
    ];

    if ( $price_min  !== '' ) $meta_query[] = [ 'key' => '_tnt_price',  'value' => $price_min,  'compare' => '>=', 'type' => 'NUMERIC' ];
    if ( $price_max  !== '' ) $meta_query[] = [ 'key' => '_tnt_price',  'value' => $price_max,  'compare' => '<=', 'type' => 'NUMERIC' ];
    if ( $length_min !== '' ) $meta_query[] = [ 'key' => '_tnt_length', 'value' => $length_min, 'compare' => '>=', 'type' => 'NUMERIC' ];
    if ( $length_max !== '' ) $meta_query[] = [ 'key' => '_tnt_length', 'value' => $length_max, 'compare' => '<=', 'type' => 'NUMERIC' ];
    if ( $year_min   !== '' ) $meta_query[] = [ 'key' => '_tnt_year',   'value' => $year_min,   'compare' => '>=', 'type' => 'NUMERIC' ];
    if ( $year_max   !== '' ) $meta_query[] = [ 'key' => '_tnt_year',   'value' => $year_max,   'compare' => '<=', 'type' => 'NUMERIC' ];
    if ( $hours_max  !== '' ) $meta_query[] = [ 'key' => '_tnt_hours',  'value' => $hours_max,  'compare' => '<=', 'type' => 'NUMERIC' ];

    $query_args = [
        'post_type'      => 'marine_listing',
        'post_status'    => 'publish',
        'posts_per_page' => intval( $atts['per_page'] ),
        'paged'          => $paged,
        'orderby'        => $orderby,
        'order'          => $order,
        'meta_query'     => $meta_query,
    ];
    if ( $meta_key ) $query_args['meta_key'] = $meta_key;

    $query          = new WP_Query( $query_args );
    $filters_active = ( $price_min !== '' || $price_max !== '' || $length_min !== '' || $length_max !== '' || $year_min !== '' || $year_max !== '' || $hours_max !== '' );
    $current_url    = strtok( $_SERVER['REQUEST_URI'], '?' );

    ob_start();
    ?>
    <div class="tnt-listings-wrap">

        <div class="tnt-filter-bar">
            <div class="tnt-filter-row">

                <div class="tnt-filter-group">
                    <label>Price ($)</label>
                    <div class="tnt-filter-range">
                        <input type="number" id="tnt_price_min" placeholder="Min" value="<?php echo esc_attr( $price_min ); ?>" min="0" step="1000">
                        <span class="tnt-range-sep">to</span>
                        <input type="number" id="tnt_price_max" placeholder="Max" value="<?php echo esc_attr( $price_max ); ?>" min="0" step="1000">
                    </div>
                </div>

                <div class="tnt-filter-group">
                    <label>Length (ft)</label>
                    <div class="tnt-filter-range">
                        <input type="number" id="tnt_length_min" placeholder="Min" value="<?php echo esc_attr( $length_min ); ?>" min="0">
                        <span class="tnt-range-sep">to</span>
                        <input type="number" id="tnt_length_max" placeholder="Max" value="<?php echo esc_attr( $length_max ); ?>" min="0">
                    </div>
                </div>

                <div class="tnt-filter-group">
                    <label>Year</label>
                    <div class="tnt-filter-range">
                        <input type="number" id="tnt_year_min" placeholder="From" value="<?php echo esc_attr( $year_min ); ?>" min="1900" max="2100">
                        <span class="tnt-range-sep">to</span>
                        <input type="number" id="tnt_year_max" placeholder="To" value="<?php echo esc_attr( $year_max ); ?>" min="1900" max="2100">
                    </div>
                </div>

                <div class="tnt-filter-group tnt-filter-group--single">
                    <label>Max Engine Hours</label>
                    <input type="number" id="tnt_hours_max" placeholder="e.g. 500" value="<?php echo esc_attr( $hours_max ); ?>" min="0">
                </div>

                <div class="tnt-filter-actions">
                    <button type="button" id="tnt-apply-filters" class="tnt-btn-filter-apply">Apply</button>
                    <?php if ( $filters_active ) : ?>
                        <a href="<?php echo esc_url( $current_url ); ?>" class="tnt-btn-filter-reset">Clear</a>
                    <?php endif; ?>
                </div>

            </div>
            <?php if ( $filters_active ) : ?>
                <p class="tnt-filter-active-notice">Filters active &mdash; <a href="<?php echo esc_url( $current_url ); ?>">view all listings</a></p>
            <?php endif; ?>
        </div>

        <?php echo tnt_marine_sort_bar( $sort, $query->found_posts ); ?>

        <?php if ( $query->have_posts() ) : ?>
            <div class="tnt-listings-grid">
                <?php while ( $query->have_posts() ) : $query->the_post();
                    tnt_marine_render_card( get_the_ID() );
                endwhile; wp_reset_postdata(); ?>
            </div>
            <?php echo tnt_marine_pagination( $paged, $query->max_num_pages ); ?>
        <?php else : ?>
            <p class="tnt-no-listings">No listings match your current filters. <a href="<?php echo esc_url( $current_url ); ?>">Clear filters</a> to see all listings.</p>
        <?php endif; ?>

    </div>
    <script>
    function tntSortChange(val) {
        var url = new URL(window.location.href);
        url.searchParams.set('tnt_sort', val);
        url.searchParams.delete('paged');
        window.location.href = url.toString();
    }
    document.addEventListener('DOMContentLoaded', function() {
        var btn = document.getElementById('tnt-apply-filters');
        if (!btn) return;
        btn.addEventListener('click', function() {
            var url = new URL(window.location.href);
            var fields = ['tnt_price_min','tnt_price_max','tnt_length_min','tnt_length_max','tnt_year_min','tnt_year_max','tnt_hours_max'];
            fields.forEach(function(id) {
                var el = document.getElementById(id);
                if (el && el.value.trim() !== '') {
                    url.searchParams.set(id, el.value.trim());
                } else {
                    url.searchParams.delete(id);
                }
            });
            url.searchParams.delete('paged');
            window.location.href = url.toString();
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'marine_listings', 'tnt_marine_listings_shortcode' );

/* =========================================================================
   [marine_sold_listings] – Sold inventory
   Usage: [marine_sold_listings per_page="12"]
   Identical layout to active listings but:
     • Only shows listings where _tnt_sold = 1
     • No price filter (price filter removed as requested)
     • SOLD badge overlaid on every card image
   ========================================================================= */

function tnt_marine_sold_listings_shortcode( $atts ) {
    $settings = tnt_marine_get_settings();
    $atts     = shortcode_atts( [ 'per_page' => intval( $settings['sold_per_page'] ) ?: 12 ], $atts, 'marine_sold_listings' );

    $sort    = isset( $_GET['tnt_sort'] )    ? sanitize_text_field( $_GET['tnt_sort'] ) : 'date_desc';
    $paged   = max( 1, get_query_var( 'paged' ) );

    // No price filter for sold listings
    $length_min = isset( $_GET['tnt_length_min'] ) ? intval( $_GET['tnt_length_min'] ) : '';
    $length_max = isset( $_GET['tnt_length_max'] ) ? intval( $_GET['tnt_length_max'] ) : '';
    $year_min   = isset( $_GET['tnt_year_min'] )   ? intval( $_GET['tnt_year_min'] )   : '';
    $year_max   = isset( $_GET['tnt_year_max'] )   ? intval( $_GET['tnt_year_max'] )   : '';
    $hours_max  = isset( $_GET['tnt_hours_max'] )  ? intval( $_GET['tnt_hours_max'] )  : '';

    [ $orderby, $order, $meta_key ] = tnt_marine_sort_args( $sort );

    // Only sold listings
    $meta_query = [
        'relation' => 'AND',
        [ 'key' => '_tnt_sold', 'compare' => '=', 'value' => '1' ],
    ];

    if ( $length_min !== '' ) $meta_query[] = [ 'key' => '_tnt_length', 'value' => $length_min, 'compare' => '>=', 'type' => 'NUMERIC' ];
    if ( $length_max !== '' ) $meta_query[] = [ 'key' => '_tnt_length', 'value' => $length_max, 'compare' => '<=', 'type' => 'NUMERIC' ];
    if ( $year_min   !== '' ) $meta_query[] = [ 'key' => '_tnt_year',   'value' => $year_min,   'compare' => '>=', 'type' => 'NUMERIC' ];
    if ( $year_max   !== '' ) $meta_query[] = [ 'key' => '_tnt_year',   'value' => $year_max,   'compare' => '<=', 'type' => 'NUMERIC' ];
    if ( $hours_max  !== '' ) $meta_query[] = [ 'key' => '_tnt_hours',  'value' => $hours_max,  'compare' => '<=', 'type' => 'NUMERIC' ];

    $query_args = [
        'post_type'      => 'marine_listing',
        'post_status'    => 'publish',
        'posts_per_page' => intval( $atts['per_page'] ),
        'paged'          => $paged,
        'orderby'        => $orderby,
        'order'          => $order,
        'meta_query'     => $meta_query,
    ];
    if ( $meta_key ) $query_args['meta_key'] = $meta_key;

    $query          = new WP_Query( $query_args );
    $filters_active = ( $length_min !== '' || $length_max !== '' || $year_min !== '' || $year_max !== '' || $hours_max !== '' );
    $current_url    = strtok( $_SERVER['REQUEST_URI'], '?' );

    ob_start();
    ?>
    <div class="tnt-listings-wrap">

        <!-- Filter bar – price filter deliberately omitted for sold listings -->
        <div class="tnt-filter-bar">
            <div class="tnt-filter-row">

                <div class="tnt-filter-group">
                    <label>Length (ft)</label>
                    <div class="tnt-filter-range">
                        <input type="number" id="tnt_length_min" placeholder="Min" value="<?php echo esc_attr( $length_min ); ?>" min="0">
                        <span class="tnt-range-sep">to</span>
                        <input type="number" id="tnt_length_max" placeholder="Max" value="<?php echo esc_attr( $length_max ); ?>" min="0">
                    </div>
                </div>

                <div class="tnt-filter-group">
                    <label>Year</label>
                    <div class="tnt-filter-range">
                        <input type="number" id="tnt_year_min" placeholder="From" value="<?php echo esc_attr( $year_min ); ?>" min="1900" max="2100">
                        <span class="tnt-range-sep">to</span>
                        <input type="number" id="tnt_year_max" placeholder="To" value="<?php echo esc_attr( $year_max ); ?>" min="1900" max="2100">
                    </div>
                </div>

                <div class="tnt-filter-group tnt-filter-group--single">
                    <label>Max Engine Hours</label>
                    <input type="number" id="tnt_hours_max" placeholder="e.g. 500" value="<?php echo esc_attr( $hours_max ); ?>" min="0">
                </div>

                <div class="tnt-filter-actions">
                    <button type="button" id="tnt-apply-filters" class="tnt-btn-filter-apply">Apply</button>
                    <?php if ( $filters_active ) : ?>
                        <a href="<?php echo esc_url( $current_url ); ?>" class="tnt-btn-filter-reset">Clear</a>
                    <?php endif; ?>
                </div>

            </div>
            <?php if ( $filters_active ) : ?>
                <p class="tnt-filter-active-notice">Filters active &mdash; <a href="<?php echo esc_url( $current_url ); ?>">view all sold listings</a></p>
            <?php endif; ?>
        </div>

        <?php echo tnt_marine_sort_bar( $sort, $query->found_posts ); ?>

        <?php if ( $query->have_posts() ) : ?>
            <div class="tnt-listings-grid">
                <?php while ( $query->have_posts() ) : $query->the_post();
                    tnt_marine_render_card( get_the_ID(), [ 'show_sold_badge' => true ] );
                endwhile; wp_reset_postdata(); ?>
            </div>
            <?php echo tnt_marine_pagination( $paged, $query->max_num_pages ); ?>
        <?php else : ?>
            <p class="tnt-no-listings">No sold listings found<?php echo $filters_active ? ' matching your filters. <a href="' . esc_url( $current_url ) . '">Clear filters</a>' : '.'; ?></p>
        <?php endif; ?>

    </div>
    <script>
    function tntSortChange(val) {
        var url = new URL(window.location.href);
        url.searchParams.set('tnt_sort', val);
        url.searchParams.delete('paged');
        window.location.href = url.toString();
    }
    document.addEventListener('DOMContentLoaded', function() {
        var btn = document.getElementById('tnt-apply-filters');
        if (!btn) return;
        btn.addEventListener('click', function() {
            var url = new URL(window.location.href);
            var fields = ['tnt_length_min','tnt_length_max','tnt_year_min','tnt_year_max','tnt_hours_max'];
            fields.forEach(function(id) {
                var el = document.getElementById(id);
                if (el && el.value.trim() !== '') {
                    url.searchParams.set(id, el.value.trim());
                } else {
                    url.searchParams.delete(id);
                }
            });
            url.searchParams.delete('paged');
            window.location.href = url.toString();
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'marine_sold_listings', 'tnt_marine_sold_listings_shortcode' );

/* =========================================================================
   Shared helpers
   ========================================================================= */

function tnt_marine_sort_args( string $sort ): array {
    $orderby  = 'date';
    $order    = 'DESC';
    $meta_key = '';
    switch ( $sort ) {
        case 'price_asc':   $orderby = 'meta_value_num'; $meta_key = '_tnt_price';  $order = 'ASC';  break;
        case 'price_desc':  $orderby = 'meta_value_num'; $meta_key = '_tnt_price';  $order = 'DESC'; break;
        case 'year_asc':    $orderby = 'meta_value_num'; $meta_key = '_tnt_year';   $order = 'ASC';  break;
        case 'year_desc':   $orderby = 'meta_value_num'; $meta_key = '_tnt_year';   $order = 'DESC'; break;
        case 'length_asc':  $orderby = 'meta_value_num'; $meta_key = '_tnt_length'; $order = 'ASC';  break;
        case 'length_desc': $orderby = 'meta_value_num'; $meta_key = '_tnt_length'; $order = 'DESC'; break;
    }
    return [ $orderby, $order, $meta_key ];
}

function tnt_marine_sort_bar( string $sort, int $found ): string {
    ob_start();
    ?>
    <div class="tnt-sort-bar">
        <label for="tnt-sort-select">Sort By:</label>
        <select id="tnt-sort-select" onchange="tntSortChange(this.value)">
            <option value="date_desc"   <?php selected( $sort, 'date_desc' ); ?>>Newest Listed</option>
            <option value="price_asc"   <?php selected( $sort, 'price_asc' ); ?>>Price: Low to High</option>
            <option value="price_desc"  <?php selected( $sort, 'price_desc' ); ?>>Price: High to Low</option>
            <option value="year_desc"   <?php selected( $sort, 'year_desc' ); ?>>Year: Newest First</option>
            <option value="year_asc"    <?php selected( $sort, 'year_asc' ); ?>>Year: Oldest First</option>
            <option value="length_asc"  <?php selected( $sort, 'length_asc' ); ?>>Length: Shortest First</option>
            <option value="length_desc" <?php selected( $sort, 'length_desc' ); ?>>Length: Longest First</option>
        </select>
        <span class="tnt-result-count"><?php echo intval( $found ); ?> listing<?php echo $found !== 1 ? 's' : ''; ?></span>
    </div>
    <?php
    return ob_get_clean();
}

function tnt_marine_pagination( int $paged, int $max_pages ): string {
    if ( $max_pages <= 1 ) return '';
    $big = 999999;
    ob_start();
    echo '<div class="tnt-pagination">';
    echo paginate_links( [
        'base'    => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
        'format'  => '?paged=%#%',
        'current' => $paged,
        'total'   => $max_pages,
    ] );
    echo '</div>';
    return ob_get_clean();
}
