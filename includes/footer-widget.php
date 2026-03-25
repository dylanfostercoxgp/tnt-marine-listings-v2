<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* =========================================================================
   TNT Marine Listings – Footer Widget
   Registers a WP widget, a shortcode [marine_footer], and the render fn.
   Automatically appended to the [marine_listings] output when enabled.

   Settings stored in: tnt_marine_footer_settings (separate option to
   avoid the multi-tab wipe bug in the main tnt_marine_settings option).
   ========================================================================= */

// ── Widget class ─────────────────────────────────────────────────────────

class TNT_Marine_Footer_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'tnt_marine_footer',
            '⚓ TNT Marine Footer',
            [ 'description' => '4-column marine footer: Contact, Recent News, Menu & Custom Image. Configure via Settings → TNT Marine → Footer Widget.' ]
        );
    }

    public function widget( $args, $instance ) {
        echo $args['before_widget'];
        echo tnt_marine_footer_render();
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        echo '<p>All settings are managed under <a href="' . esc_url( admin_url( 'options-general.php?page=tnt-marine-settings&tab=footer' ) ) . '"><strong>Settings → TNT Marine → Footer Widget</strong></a>.</p>';
    }

    public function update( $new, $old ) { return $old; }
}

add_action( 'widgets_init', function () {
    register_widget( 'TNT_Marine_Footer_Widget' );
} );

// ── Shortcode [marine_footer] ─────────────────────────────────────────────

add_shortcode( 'marine_footer', 'tnt_marine_footer_render' );

// ── Getter with defaults ──────────────────────────────────────────────────

function tnt_marine_get_footer_settings(): array {
    $saved = get_option( 'tnt_marine_footer_settings', [] );
    return wp_parse_args( $saved, [
        'footer_enabled'        => '',
        'footer_bg_color'       => '#1a1a1a',
        'footer_text_color'     => '#cccccc',
        'footer_accent_color'   => '#cc2129',
        // Column 1 – Contact
        'col1_heading'          => 'Contact Us',
        'col1_address_label'    => 'Address',
        'col1_phone_label'      => 'Phone Number',
        'col1_email_label'      => 'Email',
        'col1_address'          => '',
        'col1_phone'            => '',
        'col1_email'            => '',
        // Column 2 – Recent News
        'col2_heading'          => 'Recent News',
        'col2_count'            => 2,
        'col2_category'         => '',
        // Column 3 – Menu
        'col3_heading'          => 'Services',
        'col3_menu'             => '',
        // Column 4 – Custom Image
        'col4_heading'          => 'Brokerage Boat Sales',
        'col4_image_url'        => '',
        'col4_image_link'       => '',
        'col4_caption'          => '',
        // Copyright
        'footer_copyright'      => '',
        'footer_credit_text'    => 'Marine Listings powered by Cox Group',
    ] );
}

// ── Register separate option (same settings group so it saves with the form) ─

add_action( 'admin_init', function () {
    register_setting(
        'tnt_marine_settings_group',
        'tnt_marine_footer_settings',
        [ 'sanitize_callback' => 'tnt_marine_sanitize_footer_settings' ]
    );
} );

function tnt_marine_sanitize_footer_settings( $input ): array {
    if ( ! is_array( $input ) ) {
        return (array) get_option( 'tnt_marine_footer_settings', [] );
    }
    $bool = fn( $k ) => isset( $input[$k] ) && $input[$k] ? '1' : '';
    return [
        'footer_enabled'      => $bool( 'footer_enabled' ),
        'footer_bg_color'     => sanitize_hex_color( $input['footer_bg_color']   ?? '#1a1a1a' ) ?: '#1a1a1a',
        'footer_text_color'   => sanitize_hex_color( $input['footer_text_color'] ?? '#cccccc' ) ?: '#cccccc',
        'footer_accent_color' => sanitize_hex_color( $input['footer_accent_color'] ?? '#cc2129' ) ?: '#cc2129',
        // Col 1
        'col1_heading'        => sanitize_text_field( $input['col1_heading']       ?? 'Contact Us' ),
        'col1_address_label'  => sanitize_text_field( $input['col1_address_label'] ?? 'Address' ),
        'col1_phone_label'    => sanitize_text_field( $input['col1_phone_label']   ?? 'Phone Number' ),
        'col1_email_label'    => sanitize_text_field( $input['col1_email_label']   ?? 'Email' ),
        'col1_address'        => sanitize_textarea_field( $input['col1_address']   ?? '' ),
        'col1_phone'          => sanitize_text_field( $input['col1_phone']         ?? '' ),
        'col1_email'          => sanitize_email( $input['col1_email']              ?? '' ),
        // Col 2
        'col2_heading'        => sanitize_text_field( $input['col2_heading']  ?? 'Recent News' ),
        'col2_count'          => max( 1, min( 10, intval( $input['col2_count'] ?? 2 ) ) ),
        'col2_category'       => sanitize_text_field( $input['col2_category'] ?? '' ),
        // Col 3
        'col3_heading'        => sanitize_text_field( $input['col3_heading'] ?? 'Services' ),
        'col3_menu'           => sanitize_text_field( $input['col3_menu']    ?? '' ),
        // Col 4
        'col4_heading'        => sanitize_text_field( $input['col4_heading']    ?? 'Brokerage Boat Sales' ),
        'col4_image_url'      => esc_url_raw( $input['col4_image_url']          ?? '' ),
        'col4_image_link'     => esc_url_raw( $input['col4_image_link']         ?? '' ),
        'col4_caption'        => sanitize_text_field( $input['col4_caption']    ?? '' ),
        // Copyright
        'footer_copyright'    => sanitize_text_field( $input['footer_copyright']   ?? '' ),
        'footer_credit_text'  => sanitize_text_field( $input['footer_credit_text'] ?? 'Marine Listings powered by Cox Group' ),
    ];
}

// ── Main render function ──────────────────────────────────────────────────

function tnt_marine_footer_render( $atts = [] ) {
    $f  = tnt_marine_get_footer_settings();
    $cs = tnt_marine_get_settings(); // company settings for fallback

    if ( empty( $f['footer_enabled'] ) ) return '';

    $bg     = esc_attr( $f['footer_bg_color']     ?: '#1a1a1a' );
    $tc     = esc_attr( $f['footer_text_color']   ?: '#cccccc' );
    $accent = esc_attr( $f['footer_accent_color'] ?: '#cc2129' );

    /* ── Column 1: Contact ── */
    $c1_h  = esc_html( $f['col1_heading']       ?: 'Contact Us' );
    $c1_al = esc_html( $f['col1_address_label'] ?: 'Address' );
    $c1_pl = esc_html( $f['col1_phone_label']   ?: 'Phone Number' );
    $c1_el = esc_html( $f['col1_email_label']   ?: 'Email' );
    // Footer-specific values fall back to company settings
    $c1_addr  = nl2br( esc_html( $f['col1_address'] ?: $cs['company_address'] ) );
    $c1_phone = esc_html( $f['col1_phone'] ?: $cs['company_phone'] );
    $c1_email_raw = $f['col1_email'] ?: $cs['company_email'];
    $c1_email = esc_html( $c1_email_raw );

    /* ── Column 2: Recent News ── */
    $c2_h   = esc_html( $f['col2_heading'] ?: 'Recent News' );
    $c2_cnt = max( 1, intval( $f['col2_count'] ?: 2 ) );
    $c2_cat = sanitize_text_field( $f['col2_category'] );

    $news_args = [
        'post_type'      => 'post',
        'posts_per_page' => $c2_cnt,
        'post_status'    => 'publish',
        'no_found_rows'  => true,
    ];
    if ( $c2_cat ) $news_args['category_name'] = $c2_cat;
    $news_q = new WP_Query( $news_args );

    /* ── Column 3: Menu ── */
    $c3_h      = esc_html( $f['col3_heading'] ?: 'Services' );
    $menu_html = '';
    if ( $f['col3_menu'] ) {
        $menu_html = wp_nav_menu( [
            'menu'            => $f['col3_menu'],
            'echo'            => false,
            'container'       => false,
            'menu_class'      => 'tnt-footer-menu',
            'depth'           => 1,
            'fallback_cb'     => false,
        ] );
    }

    /* ── Column 4: Custom Image ── */
    $c4_h       = esc_html( $f['col4_heading']  ?: 'Explore More' );
    $c4_img     = esc_url( $f['col4_image_url'] );
    $c4_link    = esc_url( $f['col4_image_link'] );
    $c4_caption = esc_html( $f['col4_caption'] );

    /* ── Copyright bar ── */
    $copyright   = esc_html( $f['footer_copyright'] ?: '© ' . date( 'Y' ) . ' ' . ( $cs['company_name'] ?: 'TNT Custom Marine' ) . '. All rights reserved.' );
    $credit_text = esc_html( $f['footer_credit_text'] );

    ob_start();
    ?>
<div class="tnt-site-footer">
<style>
.tnt-site-footer{background:<?php echo $bg; ?>;color:<?php echo $tc; ?>;padding:52px 0 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,sans-serif;font-size:13px;line-height:1.75;
    /* ── Full-bleed: break out of theme's content wrapper ── */
    position:relative;left:50%;right:50%;
    margin-left:-50vw;margin-right:-50vw;
    width:100vw;max-width:100vw;
    /* ── Close the gap below: eat the theme's content-area bottom padding ── */
    margin-bottom:-120px;
    padding-bottom:0;
}
.tnt-site-footer *{box-sizing:border-box;}
.tnt-footer-inner{max-width:1200px;margin:0 auto;padding:0 40px;display:grid;grid-template-columns:repeat(4,1fr);gap:44px;align-items:start;}
.tnt-footer-col h4{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.10em;color:#ffffff;margin:0 0 20px;padding-bottom:12px;border-bottom:2px solid <?php echo $accent; ?>;}
.tnt-footer-col a{color:<?php echo $tc; ?>;text-decoration:none;transition:color .15s;}
.tnt-footer-col a:hover{color:<?php echo $accent; ?>;text-decoration:none;}
.tnt-footer-label{font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.07em;opacity:.5;margin:0 0 3px;}
.tnt-footer-value{opacity:.8;margin:0 0 16px;font-size:13px;}
.tnt-footer-value a{opacity:.85;}
.tnt-footer-menu{list-style:none;margin:0;padding:0;}
.tnt-footer-menu li{margin:0 0 10px;padding:0;}
.tnt-footer-menu li a{opacity:.8;font-size:13px;display:block;}
.tnt-footer-menu li a:hover{opacity:1;}
.tnt-footer-menu li.current-menu-item > a{color:<?php echo $accent; ?>;opacity:1;}
.tnt-footer-news-item{display:flex;gap:13px;margin-bottom:18px;align-items:flex-start;}
.tnt-footer-news-thumb{flex-shrink:0;width:58px;height:58px;border-radius:6px;overflow:hidden;background:rgba(255,255,255,.06);line-height:0;}
.tnt-footer-news-thumb img{width:100%;height:100%;object-fit:cover;display:block;}
.tnt-footer-news-title{font-size:13px;font-weight:600;opacity:.88;line-height:1.35;display:block;margin-bottom:4px;}
.tnt-footer-news-date{font-size:11px;opacity:.4;}
.tnt-footer-img-wrap{display:block;border-radius:7px;overflow:hidden;line-height:0;background:rgba(255,255,255,.04);}
.tnt-footer-img-wrap img{width:100%;height:auto;display:block;}
.tnt-footer-caption{margin-top:10px;font-size:12px;opacity:.5;line-height:1.5;}
.tnt-footer-placeholder{opacity:.3;font-size:12px;font-style:italic;}
.tnt-footer-hr{border:none;border-top:1px solid rgba(255,255,255,.07);margin:40px 0 0;}
.tnt-footer-bottom{max-width:1200px;margin:0 auto;padding:16px 40px;font-size:11px;opacity:.38;display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;}
@media(max-width:960px){
    .tnt-footer-inner{grid-template-columns:1fr 1fr;gap:36px;}
}
@media(max-width:560px){
    .tnt-footer-inner{grid-template-columns:1fr;gap:28px;padding:0 24px;}
    .tnt-footer-bottom{padding:14px 24px;}
}
</style>

    <div class="tnt-footer-inner">

        <!-- ── Column 1: Contact ── -->
        <div class="tnt-footer-col">
            <h4><?php echo $c1_h; ?></h4>
            <?php if ( $c1_addr ) : ?>
                <p class="tnt-footer-label"><?php echo $c1_al; ?></p>
                <p class="tnt-footer-value"><?php echo $c1_addr; ?></p>
            <?php endif; ?>
            <?php if ( $c1_phone ) : ?>
                <p class="tnt-footer-label"><?php echo $c1_pl; ?></p>
                <p class="tnt-footer-value">
                    <a href="tel:<?php echo esc_attr( preg_replace( '/[^+\d]/', '', $c1_phone ) ); ?>"><?php echo $c1_phone; ?></a>
                </p>
            <?php endif; ?>
            <?php if ( $c1_email ) : ?>
                <p class="tnt-footer-label"><?php echo $c1_el; ?></p>
                <p class="tnt-footer-value">
                    <a href="mailto:<?php echo esc_attr( $c1_email_raw ); ?>"><?php echo $c1_email; ?></a>
                </p>
            <?php endif; ?>
            <?php if ( ! $c1_addr && ! $c1_phone && ! $c1_email ) : ?>
                <p class="tnt-footer-placeholder">Add contact info in Footer Widget settings or Company &amp; Branding.</p>
            <?php endif; ?>
        </div>

        <!-- ── Column 2: Recent News ── -->
        <div class="tnt-footer-col">
            <h4><?php echo $c2_h; ?></h4>
            <?php if ( $news_q->have_posts() ) : ?>
                <?php while ( $news_q->have_posts() ) : $news_q->the_post(); ?>
                    <?php
                    $tid  = (int) get_post_thumbnail_id();
                    $turl = $tid ? wp_get_attachment_image_url( $tid, 'thumbnail' ) : '';
                    ?>
                    <div class="tnt-footer-news-item">
                        <?php if ( $turl ) : ?>
                            <a href="<?php the_permalink(); ?>" class="tnt-footer-news-thumb" aria-hidden="true" tabindex="-1">
                                <img src="<?php echo esc_url( $turl ); ?>" alt="<?php the_title_attribute(); ?>">
                            </a>
                        <?php endif; ?>
                        <div>
                            <a href="<?php the_permalink(); ?>" class="tnt-footer-news-title"><?php the_title(); ?></a>
                            <span class="tnt-footer-news-date"><?php echo esc_html( get_the_date() ); ?></span>
                        </div>
                    </div>
                <?php endwhile; wp_reset_postdata(); ?>
            <?php else : ?>
                <p class="tnt-footer-placeholder">No recent posts found.</p>
            <?php endif; ?>
        </div>

        <!-- ── Column 3: Menu ── -->
        <div class="tnt-footer-col">
            <h4><?php echo $c3_h; ?></h4>
            <?php if ( $menu_html ) : ?>
                <?php echo $menu_html; ?>
            <?php else : ?>
                <p class="tnt-footer-placeholder">Select a menu in Footer Widget settings.</p>
            <?php endif; ?>
        </div>

        <!-- ── Column 4: Custom Image ── -->
        <div class="tnt-footer-col">
            <h4><?php echo $c4_h; ?></h4>
            <?php if ( $c4_img ) : ?>
                <?php if ( $c4_link ) : ?>
                    <a href="<?php echo $c4_link; ?>" class="tnt-footer-img-wrap" target="_blank" rel="noopener noreferrer">
                        <img src="<?php echo $c4_img; ?>" alt="<?php echo $c4_h; ?>">
                    </a>
                <?php else : ?>
                    <div class="tnt-footer-img-wrap">
                        <img src="<?php echo $c4_img; ?>" alt="<?php echo $c4_h; ?>">
                    </div>
                <?php endif; ?>
                <?php if ( $c4_caption ) : ?>
                    <p class="tnt-footer-caption"><?php echo $c4_caption; ?></p>
                <?php endif; ?>
            <?php else : ?>
                <p class="tnt-footer-placeholder">Set an image URL in Footer Widget settings.</p>
            <?php endif; ?>
        </div>

    </div>

    <hr class="tnt-footer-hr">
    <div class="tnt-footer-bottom">
        <span><?php echo $copyright; ?></span>
        <?php if ( $credit_text ) : ?>
            <span><?php echo $credit_text; ?></span>
        <?php endif; ?>
    </div>

</div>
<script>
/* Dynamically snap the TNT footer flush to the bottom of the content area */
(function(){
    var footer = document.querySelector('.tnt-site-footer');
    if(!footer) return;
    function snap(){
        var parent = footer.parentElement;
        if(!parent) return;
        var style  = window.getComputedStyle(parent);
        var pb     = parseFloat(style.paddingBottom) || 0;
        var mb     = parseFloat(style.marginBottom)  || 0;
        // Walk up until we hit the element that actually has bottom space
        var el = parent;
        var extra = 0;
        while(el && el !== document.body){
            var cs = window.getComputedStyle(el);
            extra += parseFloat(cs.paddingBottom)||0;
            el = el.parentElement;
            if(extra > 5) break; // found it
        }
        footer.style.marginBottom = '-' + (extra + 2) + 'px';
    }
    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', snap);
    } else {
        snap();
    }
    window.addEventListener('resize', snap);
})();
</script>
    <?php
    return ob_get_clean();
}
