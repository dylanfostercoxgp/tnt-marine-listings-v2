<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* =========================================================================
   TNT Marine Listings – Settings Page (v1.2.0)
   Tabbed admin settings covering: Email, Display, Inquiry Form,
   Company & Branding, and Social Media.
   ========================================================================= */

// ── Plugin action links ────────────────────────────────────────────────────

function tnt_marine_plugin_action_links( $links ) {
    $settings_link = '<a href="' . admin_url( 'options-general.php?page=tnt-marine-settings' ) . '">Settings</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_tnt-marine-listings-v2/tnt-marine-listings.php', 'tnt_marine_plugin_action_links' );

// ── Register settings ──────────────────────────────────────────────────────

function tnt_marine_register_settings() {
    // Legacy email settings option (backward compat)
    register_setting(
        'tnt_marine_settings_group',
        'tnt_marine_email_settings',
        [ 'sanitize_callback' => 'tnt_marine_sanitize_email_settings' ]
    );
    // New general settings option
    register_setting(
        'tnt_marine_settings_group',
        'tnt_marine_settings',
        [ 'sanitize_callback' => 'tnt_marine_sanitize_settings' ]
    );
}
add_action( 'admin_init', 'tnt_marine_register_settings' );

// ── Sanitise callbacks ─────────────────────────────────────────────────────

function tnt_marine_sanitize_email_settings( $input ): array {
    // If this option wasn't part of the submitted form (other tab was saved),
    // return the existing saved value unchanged to prevent it being wiped.
    if ( ! is_array( $input ) ) {
        return (array) get_option( 'tnt_marine_email_settings', [] );
    }
    return [
        'to_email'      => sanitize_email( $input['to_email']          ?? '' ),
        'from_name'     => sanitize_text_field( $input['from_name']    ?? '' ),
        'from_email'    => sanitize_email( $input['from_email']        ?? '' ),
        'reply_to'      => sanitize_email( $input['reply_to']          ?? '' ),
        'cc'            => sanitize_text_field( $input['cc']           ?? '' ),
        'bcc'           => sanitize_text_field( $input['bcc']          ?? '' ),
        'email_subject' => sanitize_text_field( $input['email_subject'] ?? '' ),
    ];
}

function tnt_marine_sanitize_settings( $input ): array {
    // If this option wasn't part of the submitted form (other tab was saved),
    // return the existing saved value unchanged to prevent it being wiped.
    if ( ! is_array( $input ) ) {
        return (array) get_option( 'tnt_marine_settings', [] );
    }
    $bool = function( $key ) use ( $input ) {
        return isset( $input[ $key ] ) && $input[ $key ] ? '1' : '';
    };
    return [
        // Display
        'listings_per_page'      => max( 1, intval( $input['listings_per_page'] ?? 12 ) ),
        'sold_per_page'          => max( 1, intval( $input['sold_per_page']     ?? 12 ) ),
        'card_columns'           => in_array( intval( $input['card_columns'] ?? 3 ), [ 2, 3, 4 ] ) ? intval( $input['card_columns'] ) : 3,
        'show_price'             => $bool( 'show_price' ),
        'show_year'              => $bool( 'show_year' ),
        'show_length'            => $bool( 'show_length' ),
        'show_location'          => $bool( 'show_location' ),
        'sold_badge_text'        => sanitize_text_field( $input['sold_badge_text']  ?? 'SOLD' ),
        'sold_badge_color'       => sanitize_hex_color( $input['sold_badge_color']  ?? '#cc2129' ) ?: '#cc2129',
        'primary_color'          => sanitize_hex_color( $input['primary_color']     ?? '#1a2e4a' ) ?: '#1a2e4a',
        'accent_color'           => sanitize_hex_color( $input['accent_color']      ?? '#cc2129' ) ?: '#cc2129',
        'save_inquiries_to_db'   => $bool( 'save_inquiries_to_db' ),
        // Inquiry form
        'form_heading'           => sanitize_text_field( $input['form_heading']         ?? '' ),
        'form_button_text'       => sanitize_text_field( $input['form_button_text']     ?? '' ),
        'form_success_message'   => sanitize_text_field( $input['form_success_message'] ?? '' ),
        'form_phone_required'    => $bool( 'form_phone_required' ),
        'form_default_message'   => sanitize_textarea_field( $input['form_default_message'] ?? '' ),
        'form_show_on_sold'      => $bool( 'form_show_on_sold' ),
        // Company
        'dashboard_logo_url'     => esc_url_raw( $input['dashboard_logo_url']      ?? '' ),
        'company_name'           => sanitize_text_field( $input['company_name']    ?? '' ),
        'company_phone'          => sanitize_text_field( $input['company_phone']   ?? '' ),
        'company_email'          => sanitize_email( $input['company_email']        ?? '' ),
        'company_address'        => sanitize_textarea_field( $input['company_address'] ?? '' ),
        'business_hours'         => sanitize_textarea_field( $input['business_hours']   ?? '' ),
        'google_maps_url'        => esc_url_raw( $input['google_maps_url']         ?? '' ),
        // Social
        'facebook_url'           => esc_url_raw( $input['facebook_url']  ?? '' ),
        'instagram_url'          => esc_url_raw( $input['instagram_url'] ?? '' ),
        'youtube_url'            => esc_url_raw( $input['youtube_url']   ?? '' ),
        'twitter_url'            => esc_url_raw( $input['twitter_url']   ?? '' ),
        'tiktok_url'             => esc_url_raw( $input['tiktok_url']    ?? '' ),
    ];
}

// ── Admin menu ─────────────────────────────────────────────────────────────

function tnt_marine_add_settings_page() {
    add_options_page(
        'TNT Marine Settings',
        'TNT Marine',
        'manage_options',
        'tnt-marine-settings',
        'tnt_marine_settings_page_html'
    );
}
add_action( 'admin_menu', 'tnt_marine_add_settings_page' );

// ── Output dynamic CSS variables on front-end ──────────────────────────────

add_action( 'wp_head', function () {
    $s = tnt_marine_get_settings();
    echo '<style id="tnt-marine-dynamic-css">:root{';
    echo '--tnt-primary:' . esc_attr( $s['primary_color'] ?: '#1a2e4a' ) . ';';
    echo '--tnt-accent:'  . esc_attr( $s['accent_color']  ?: '#cc2129' ) . ';';
    echo '}</style>' . "\n";
} );

// ── Render settings page ───────────────────────────────────────────────────

function tnt_marine_settings_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $email_opts = tnt_marine_get_email_settings();
    $opts       = tnt_marine_get_settings();
    $saved      = isset( $_GET['settings-updated'] ) && $_GET['settings-updated'];
    $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'email';

    $tabs = [
        'email'    => '📧 Email',
        'display'  => '🖼 Display & Layout',
        'form'     => '📋 Inquiry Form',
        'company'  => '🏢 Company & Branding',
        'social'   => '📱 Social Media',
        'footer'   => '🦶 Footer Widget',
    ];

    $tab_url = function( $slug ) {
        return admin_url( 'options-general.php?page=tnt-marine-settings&tab=' . $slug );
    };
    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:10px;margin-bottom:0;">
            <span style="display:inline-block;background:#cc2129;color:#fff;font-size:13px;font-weight:700;padding:3px 10px;border-radius:4px;letter-spacing:.04em;">TNT MARINE</span>
            Settings
        </h1>

        <?php if ( $saved ) : ?>
            <div class="notice notice-success is-dismissible" style="margin-top:16px;"><p><strong>Settings saved.</strong></p></div>
        <?php endif; ?>

        <!-- Tab nav -->
        <nav class="nav-tab-wrapper" style="margin-top:20px;">
            <?php foreach ( $tabs as $slug => $label ) : ?>
                <a href="<?php echo esc_url( $tab_url( $slug ) ); ?>"
                   class="nav-tab<?php echo $active_tab === $slug ? ' nav-tab-active' : ''; ?>">
                   <?php echo esc_html( $label ); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <form method="post" action="options.php" style="margin-top:0;">
            <?php settings_fields( 'tnt_marine_settings_group' ); ?>

            <!-- ══════════════════════════════════════════ TAB: EMAIL -->
            <?php if ( $active_tab === 'email' ) : ?>
            <div style="background:#fff;border:1px solid #ddd;border-top:none;padding:28px 28px 8px;border-radius:0 0 4px 4px;">
                <p style="color:#666;margin-top:0;">Configure where inquiry emails are sent and how they appear to recipients.</p>

                <table class="form-table" role="presentation">
                    <?php tnt_field_row( 'to_email',      'Send Inquiries To *',    'email', $email_opts['to_email'],      'tnt_marine_email_settings', 'The email address that receives all inquiry notifications.', 'you@yourdomain.com' ); ?>
                    <?php tnt_field_row( 'from_name',     'From Name',              'text',  $email_opts['from_name'],     'tnt_marine_email_settings', 'The name shown in the "From" field of inquiry emails.',      'TNT Custom Marine' ); ?>
                    <?php tnt_field_row( 'from_email',    'From Email',             'email', $email_opts['from_email'],    'tnt_marine_email_settings', 'The email address the inquiry is sent from.',                 'inquiry@yourdomain.com' ); ?>
                    <?php tnt_field_row( 'reply_to',      'Reply-To',               'email', $email_opts['reply_to'],      'tnt_marine_email_settings', 'When you click Reply in your inbox, this address will be used.', 'noreply@yourdomain.com' ); ?>
                    <?php tnt_field_row( 'cc',            'CC',                     'text',  $email_opts['cc'],            'tnt_marine_email_settings', 'Optional. Separate multiple addresses with commas.',          'person@example.com, other@example.com' ); ?>
                    <?php tnt_field_row( 'bcc',           'BCC',                    'text',  $email_opts['bcc'],           'tnt_marine_email_settings', 'Optional. Separate multiple addresses with commas.',          'archive@example.com' ); ?>
                    <?php tnt_field_row( 'email_subject', 'Email Subject Prefix',   'text',  $email_opts['email_subject'], 'tnt_marine_email_settings', 'Prepended to the listing title in the subject line. e.g. "New Inquiry: 2023 Sea Ray 390"', 'New Inquiry:' ); ?>
                </table>
            </div>

            <!-- ══════════════════════════════════════════ TAB: DISPLAY -->
            <?php elseif ( $active_tab === 'display' ) : ?>
            <div style="background:#fff;border:1px solid #ddd;border-top:none;padding:28px 28px 8px;border-radius:0 0 4px 4px;">
                <p style="color:#666;margin-top:0;">Control how listings are displayed across your site.</p>

                <h2 style="border-bottom:2px solid #cc2129;padding-bottom:8px;">Grid & Pagination</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="tnt_listings_per_page">Active Listings Per Page</label></th>
                        <td>
                            <input type="number" id="tnt_listings_per_page" name="tnt_marine_settings[listings_per_page]"
                                   value="<?php echo intval( $opts['listings_per_page'] ); ?>" class="small-text" min="1" max="100">
                            <p class="description">Default is 12. Used by the <code>[marine_listings]</code> shortcode.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_sold_per_page">Sold Listings Per Page</label></th>
                        <td>
                            <input type="number" id="tnt_sold_per_page" name="tnt_marine_settings[sold_per_page]"
                                   value="<?php echo intval( $opts['sold_per_page'] ); ?>" class="small-text" min="1" max="100">
                            <p class="description">Default is 12. Used by the <code>[marine_sold_listings]</code> shortcode.</p>
                        </td>
                    </tr>
                </table>

                <h2 style="border-bottom:2px solid #cc2129;padding-bottom:8px;margin-top:28px;">Card Details</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th>Show on Listing Cards</th>
                        <td>
                            <?php
                            $toggles = [
                                'show_price'    => 'Price',
                                'show_year'     => 'Year',
                                'show_length'   => 'Length',
                                'show_location' => 'Location',
                            ];
                            foreach ( $toggles as $key => $label ) : ?>
                                <label style="display:inline-block;margin-right:20px;margin-bottom:8px;">
                                    <input type="checkbox" name="tnt_marine_settings[<?php echo esc_attr( $key ); ?>]" value="1"
                                           <?php checked( $opts[ $key ], '1' ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">Choose which details appear on each listing card in the grid.</p>
                        </td>
                    </tr>
                </table>

                <h2 style="border-bottom:2px solid #cc2129;padding-bottom:8px;margin-top:28px;">Sold Badge</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="tnt_sold_badge_text">Badge Text</label></th>
                        <td>
                            <input type="text" id="tnt_sold_badge_text" name="tnt_marine_settings[sold_badge_text]"
                                   value="<?php echo esc_attr( $opts['sold_badge_text'] ); ?>" class="regular-text" placeholder="SOLD">
                            <p class="description">Text displayed on the SOLD badge overlaid on sold listing cards.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_sold_badge_color">Badge Color</label></th>
                        <td>
                            <input type="color" id="tnt_sold_badge_color" name="tnt_marine_settings[sold_badge_color]"
                                   value="<?php echo esc_attr( $opts['sold_badge_color'] ); ?>">
                            <span style="margin-left:8px;font-size:12px;color:#666;"><?php echo esc_html( $opts['sold_badge_color'] ); ?></span>
                            <p class="description">Background color of the sold badge.</p>
                        </td>
                    </tr>
                </table>

                <h2 style="border-bottom:2px solid #cc2129;padding-bottom:8px;margin-top:28px;">Brand Colors</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="tnt_primary_color">Primary Color</label></th>
                        <td>
                            <input type="color" id="tnt_primary_color" name="tnt_marine_settings[primary_color]"
                                   value="<?php echo esc_attr( $opts['primary_color'] ); ?>">
                            <span style="margin-left:8px;font-size:12px;color:#666;"><?php echo esc_html( $opts['primary_color'] ); ?></span>
                            <p class="description">Used for headings, card titles, overview panels. Default: <code>#1a2e4a</code> (navy).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_accent_color">Accent Color</label></th>
                        <td>
                            <input type="color" id="tnt_accent_color" name="tnt_marine_settings[accent_color]"
                                   value="<?php echo esc_attr( $opts['accent_color'] ); ?>">
                            <span style="margin-left:8px;font-size:12px;color:#666;"><?php echo esc_html( $opts['accent_color'] ); ?></span>
                            <p class="description">Used for prices, buttons, badges, and highlights. Default: <code>#cc2129</code> (red).</p>
                        </td>
                    </tr>
                </table>

                <h2 style="border-bottom:2px solid #cc2129;padding-bottom:8px;margin-top:28px;">Inquiry Database</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th>Save Inquiries to Database</th>
                        <td>
                            <label>
                                <input type="checkbox" name="tnt_marine_settings[save_inquiries_to_db]" value="1"
                                       <?php checked( $opts['save_inquiries_to_db'], '1' ); ?>>
                                Enable inquiry logging
                            </label>
                            <p class="description">When enabled, every inquiry form submission is saved to the <strong>Marine Listings → Inquiries</strong> database for your sales team.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ══════════════════════════════════════════ TAB: FORM -->
            <?php elseif ( $active_tab === 'form' ) : ?>
            <div style="background:#fff;border:1px solid #ddd;border-top:none;padding:28px 28px 8px;border-radius:0 0 4px 4px;">
                <p style="color:#666;margin-top:0;">Customise the inquiry form that appears on each listing page.</p>

                <h2 style="border-bottom:2px solid #cc2129;padding-bottom:8px;">Text & Labels</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="tnt_form_heading">Form Heading</label></th>
                        <td>
                            <input type="text" id="tnt_form_heading" name="tnt_marine_settings[form_heading]"
                                   value="<?php echo esc_attr( $opts['form_heading'] ); ?>" class="regular-text"
                                   placeholder="Inquire About This Vessel">
                            <p class="description">The title displayed above the inquiry form.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_form_button_text">Submit Button Text</label></th>
                        <td>
                            <input type="text" id="tnt_form_button_text" name="tnt_marine_settings[form_button_text]"
                                   value="<?php echo esc_attr( $opts['form_button_text'] ); ?>" class="regular-text"
                                   placeholder="Send Inquiry">
                            <p class="description">Text on the submit button.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_form_success_message">Success Message</label></th>
                        <td>
                            <input type="text" id="tnt_form_success_message" name="tnt_marine_settings[form_success_message]"
                                   value="<?php echo esc_attr( $opts['form_success_message'] ); ?>" class="large-text"
                                   placeholder="Your inquiry has been sent. We will be in touch shortly.">
                            <p class="description">Shown to the visitor after a successful form submission.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_form_default_message">Default Message Text</label></th>
                        <td>
                            <textarea id="tnt_form_default_message" name="tnt_marine_settings[form_default_message]"
                                      class="large-text" rows="3"
                                      placeholder="I am interested in this vessel and would like more information."><?php echo esc_textarea( $opts['form_default_message'] ); ?></textarea>
                            <p class="description">Pre-filled text in the message field. The listing title is automatically appended.</p>
                        </td>
                    </tr>
                </table>

                <h2 style="border-bottom:2px solid #cc2129;padding-bottom:8px;margin-top:28px;">Field Options</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th>Phone Number Field</th>
                        <td>
                            <label>
                                <input type="checkbox" name="tnt_marine_settings[form_phone_required]" value="1"
                                       <?php checked( $opts['form_phone_required'], '1' ); ?>>
                                Make phone number required
                            </label>
                            <p class="description">By default phone is optional. Check this to require it.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Sold Listings</th>
                        <td>
                            <label>
                                <input type="checkbox" name="tnt_marine_settings[form_show_on_sold]" value="1"
                                       <?php checked( $opts['form_show_on_sold'], '1' ); ?>>
                                Show inquiry form on sold listing pages
                            </label>
                            <p class="description">Useful for capturing leads interested in similar boats you may have available.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ══════════════════════════════════════════ TAB: COMPANY -->
            <?php elseif ( $active_tab === 'company' ) : ?>
            <div style="background:#fff;border:1px solid #ddd;border-top:none;padding:28px 28px 8px;border-radius:0 0 4px 4px;">
                <p style="color:#666;margin-top:0;">Your dealership information. Used in email footers and can be referenced by your theme.</p>

                <h2 style="border-bottom:2px solid #cc2129;padding-bottom:8px;">Inquiry Dashboard</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="tnt_dashboard_logo_url">Dashboard Logo URL</label></th>
                        <td>
                            <input type="url" id="tnt_dashboard_logo_url" name="tnt_marine_settings[dashboard_logo_url]"
                                   value="<?php echo esc_attr( $opts['dashboard_logo_url'] ); ?>" class="large-text"
                                   placeholder="https://yourdomain.com/wp-content/uploads/your-logo.png">
                            <p class="description">URL of the logo to display in the top-left of the <code>[marine_inquiries]</code> sales dashboard. Upload your logo via <strong>Media → Add New</strong>, then paste the image URL here.</p>
                            <?php if ( ! empty( $opts['dashboard_logo_url'] ) ) : ?>
                                <div style="margin-top:10px;">
                                    <img src="<?php echo esc_url( $opts['dashboard_logo_url'] ); ?>" alt="Dashboard Logo Preview" style="max-height:60px;border:1px solid #ddd;border-radius:4px;padding:6px;background:#0f172a;">
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <h2 style="border-bottom:2px solid #cc2129;padding-bottom:8px;margin-top:28px;">Business Information</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="tnt_company_name">Business Name</label></th>
                        <td>
                            <input type="text" id="tnt_company_name" name="tnt_marine_settings[company_name]"
                                   value="<?php echo esc_attr( $opts['company_name'] ); ?>" class="regular-text"
                                   placeholder="TNT Custom Marine">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_company_phone">Phone Number</label></th>
                        <td>
                            <input type="tel" id="tnt_company_phone" name="tnt_marine_settings[company_phone]"
                                   value="<?php echo esc_attr( $opts['company_phone'] ); ?>" class="regular-text"
                                   placeholder="(555) 000-0000">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_company_email">Contact Email</label></th>
                        <td>
                            <input type="email" id="tnt_company_email" name="tnt_marine_settings[company_email]"
                                   value="<?php echo esc_attr( $opts['company_email'] ); ?>" class="regular-text"
                                   placeholder="info@yourdealership.com">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_company_address">Physical Address</label></th>
                        <td>
                            <textarea id="tnt_company_address" name="tnt_marine_settings[company_address]"
                                      class="large-text" rows="3"
                                      placeholder="123 Marina Drive&#10;Fort Lauderdale, FL 33301"><?php echo esc_textarea( $opts['company_address'] ); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_business_hours">Business Hours</label></th>
                        <td>
                            <textarea id="tnt_business_hours" name="tnt_marine_settings[business_hours]"
                                      class="large-text" rows="4"
                                      placeholder="Mon–Fri: 8am – 6pm&#10;Sat: 9am – 4pm&#10;Sun: Closed"><?php echo esc_textarea( $opts['business_hours'] ); ?></textarea>
                            <p class="description">One line per entry. Displayed in any widgets or templates that reference these settings.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_google_maps_url">Google Maps Embed URL</label></th>
                        <td>
                            <input type="url" id="tnt_google_maps_url" name="tnt_marine_settings[google_maps_url]"
                                   value="<?php echo esc_attr( $opts['google_maps_url'] ); ?>" class="large-text"
                                   placeholder="https://www.google.com/maps/embed?pb=...">
                            <p class="description">Paste the embed URL from Google Maps to display a map on your contact page.</p>
                        </td>
                    </tr>
                </table>

                <?php if ( $opts['google_maps_url'] ) : ?>
                <div style="margin:0 0 20px 10px;">
                    <p style="font-size:12px;color:#666;margin-bottom:6px;font-weight:600;">Map Preview:</p>
                    <iframe src="<?php echo esc_url( $opts['google_maps_url'] ); ?>" width="500" height="220" style="border:1px solid #ddd;border-radius:6px;display:block;" allowfullscreen="" loading="lazy"></iframe>
                </div>
                <?php endif; ?>
            </div>

            <!-- ══════════════════════════════════════════ TAB: SOCIAL -->
            <?php elseif ( $active_tab === 'social' ) : ?>
            <div style="background:#fff;border:1px solid #ddd;border-top:none;padding:28px 28px 8px;border-radius:0 0 4px 4px;">
                <p style="color:#666;margin-top:0;">Your social media profile links. Leave blank to hide the platform from any widgets.</p>

                <table class="form-table" role="presentation">
                    <?php
                    $socials = [
                        'facebook_url'  => [ '🔵 Facebook',  'https://facebook.com/yourpage' ],
                        'instagram_url' => [ '📸 Instagram', 'https://instagram.com/yourhandle' ],
                        'youtube_url'   => [ '▶ YouTube',    'https://youtube.com/@yourchannel' ],
                        'twitter_url'   => [ '𝕏 X / Twitter', 'https://x.com/yourhandle' ],
                        'tiktok_url'    => [ '🎵 TikTok',    'https://tiktok.com/@yourhandle' ],
                    ];
                    foreach ( $socials as $key => [ $label, $placeholder ] ) : ?>
                    <tr>
                        <th><label for="tnt_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
                        <td>
                            <input type="url" id="tnt_<?php echo esc_attr( $key ); ?>"
                                   name="tnt_marine_settings[<?php echo esc_attr( $key ); ?>]"
                                   value="<?php echo esc_attr( $opts[ $key ] ); ?>"
                                   class="large-text"
                                   placeholder="<?php echo esc_attr( $placeholder ); ?>">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <!-- ══════════════════════════════════════════ TAB: FOOTER WIDGET -->
            <?php elseif ( $active_tab === 'footer' ) :
                $fw = tnt_marine_get_footer_settings();
                // Build nav menus list for dropdown
                $nav_menus = wp_get_nav_menus();
            ?>
            <div style="background:#fff;border:1px solid #ddd;border-top:none;padding:28px 28px 8px;border-radius:0 0 4px 4px;">
                <p style="color:#666;margin-top:0;">Configure the 4-column footer that can be placed via the <code>[marine_footer]</code> shortcode, the <strong>⚓ TNT Marine Footer</strong> widget (Appearance → Widgets), or auto-appended to <code>[marine_listings]</code>.</p>

                <!-- ── Enable / Style ── -->
                <h2 style="border-bottom:2px solid #cc2129;padding-bottom:8px;">Enable &amp; Style</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th>Show Footer</th>
                        <td>
                            <label>
                                <input type="checkbox" name="tnt_marine_footer_settings[footer_enabled]" value="1"
                                       <?php checked( $fw['footer_enabled'], '1' ); ?>>
                                Enable the footer below <code>[marine_listings]</code>
                            </label>
                            <p class="description">When checked the footer is automatically rendered after the listings grid. You can also place it anywhere with <code>[marine_footer]</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_footer_bg_color">Background Color</label></th>
                        <td>
                            <input type="color" id="tnt_footer_bg_color" name="tnt_marine_footer_settings[footer_bg_color]"
                                   value="<?php echo esc_attr( $fw['footer_bg_color'] ); ?>">
                            <span style="margin-left:8px;font-size:12px;color:#666;"><?php echo esc_html( $fw['footer_bg_color'] ); ?></span>
                            <p class="description">Dark background recommended. Default: <code>#1a1a1a</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_footer_text_color">Text Color</label></th>
                        <td>
                            <input type="color" id="tnt_footer_text_color" name="tnt_marine_footer_settings[footer_text_color]"
                                   value="<?php echo esc_attr( $fw['footer_text_color'] ); ?>">
                            <span style="margin-left:8px;font-size:12px;color:#666;"><?php echo esc_html( $fw['footer_text_color'] ); ?></span>
                            <p class="description">Body / label text. Default: <code>#cccccc</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_footer_accent_color">Accent / Underline Color</label></th>
                        <td>
                            <input type="color" id="tnt_footer_accent_color" name="tnt_marine_footer_settings[footer_accent_color]"
                                   value="<?php echo esc_attr( $fw['footer_accent_color'] ); ?>">
                            <span style="margin-left:8px;font-size:12px;color:#666;"><?php echo esc_html( $fw['footer_accent_color'] ); ?></span>
                            <p class="description">Used for heading underlines and hover links. Default: <code>#cc2129</code></p>
                        </td>
                    </tr>
                </table>

                <!-- ── Column 1: Contact ── -->
                <h2 style="border-bottom:2px solid #cc2129;padding-bottom:8px;margin-top:28px;">Column 1 — Contact</h2>
                <p style="color:#666;margin-top:0;">Values fall back to Company &amp; Branding settings if left blank.</p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="tnt_col1_heading">Column Heading</label></th>
                        <td>
                            <input type="text" id="tnt_col1_heading" name="tnt_marine_footer_settings[col1_heading]"
                                   value="<?php echo esc_attr( $fw['col1_heading'] ); ?>" class="regular-text" placeholder="Contact Us">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_col1_address_label">Address Label</label></th>
                        <td>
                            <input type="text" id="tnt_col1_address_label" name="tnt_marine_footer_settings[col1_address_label]"
                                   value="<?php echo esc_attr( $fw['col1_address_label'] ); ?>" class="regular-text" placeholder="Address">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_col1_address">Address (Override)</label></th>
                        <td>
                            <textarea id="tnt_col1_address" name="tnt_marine_footer_settings[col1_address]"
                                      class="large-text" rows="3"
                                      placeholder="Leave blank to use Company &amp; Branding address"><?php echo esc_textarea( $fw['col1_address'] ); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_col1_phone_label">Phone Label</label></th>
                        <td>
                            <input type="text" id="tnt_col1_phone_label" name="tnt_marine_footer_settings[col1_phone_label]"
                                   value="<?php echo esc_attr( $fw['col1_phone_label'] ); ?>" class="regular-text" placeholder="Phone Number">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_col1_phone">Phone (Override)</label></th>
                        <td>
                            <input type="tel" id="tnt_col1_phone" name="tnt_marine_footer_settings[col1_phone]"
                                   value="<?php echo esc_attr( $fw['col1_phone'] ); ?>" class="regular-text" placeholder="Leave blank to use Company &amp; Branding phone">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_col1_email_label">Email Label</label></th>
                        <td>
                            <input type="text" id="tnt_col1_email_label" name="tnt_marine_footer_settings[col1_email_label]"
                                   value="<?php echo esc_attr( $fw['col1_email_label'] ); ?>" class="regular-text" placeholder="Email">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_col1_email">Email (Override)</label></th>
                        <td>
                            <input type="email" id="tnt_col1_email" name="tnt_marine_footer_settings[col1_email]"
                                   value="<?php echo esc_attr( $fw['col1_email'] ); ?>" class="regular-text" placeholder="Leave blank to use Company &amp; Branding email">
                        </td>
                    </tr>
                </table>

                <!-- ── Column 2: Recent News ── -->
                <h2 style="border-bottom:2px solid #cc2129;padding-bottom:8px;margin-top:28px;">Column 2 — Recent News</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="tnt_col2_heading">Column Heading</label></th>
                        <td>
                            <input type="text" id="tnt_col2_heading" name="tnt_marine_footer_settings[col2_heading]"
                                   value="<?php echo esc_attr( $fw['col2_heading'] ); ?>" class="regular-text" placeholder="Recent News">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_col2_count">Number of Posts</label></th>
                        <td>
                            <input type="number" id="tnt_col2_count" name="tnt_marine_footer_settings[col2_count]"
                                   value="<?php echo intval( $fw['col2_count'] ); ?>" class="small-text" min="1" max="10">
                            <p class="description">How many recent blog posts to show (1–10). Default: 2.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_col2_category">Category Filter</label></th>
                        <td>
                            <input type="text" id="tnt_col2_category" name="tnt_marine_footer_settings[col2_category]"
                                   value="<?php echo esc_attr( $fw['col2_category'] ); ?>" class="regular-text"
                                   placeholder="e.g. marine-news">
                            <p class="description">Enter a category <strong>slug</strong> to filter posts. Leave blank to show all recent posts.</p>
                        </td>
                    </tr>
                </table>

                <!-- ── Column 3: Menu ── -->
                <h2 style="border-bottom:2px solid #cc2129;padding-bottom:8px;margin-top:28px;">Column 3 — Menu</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="tnt_col3_heading">Column Heading</label></th>
                        <td>
                            <input type="text" id="tnt_col3_heading" name="tnt_marine_footer_settings[col3_heading]"
                                   value="<?php echo esc_attr( $fw['col3_heading'] ); ?>" class="regular-text" placeholder="Services">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_col3_menu">Navigation Menu</label></th>
                        <td>
                            <select id="tnt_col3_menu" name="tnt_marine_footer_settings[col3_menu]">
                                <option value="">— Select a menu —</option>
                                <?php foreach ( $nav_menus as $menu ) : ?>
                                    <option value="<?php echo esc_attr( $menu->slug ); ?>"
                                            <?php selected( $fw['col3_menu'], $menu->slug ); ?>>
                                        <?php echo esc_html( $menu->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Choose any navigation menu created under <strong>Appearance → Menus</strong>.</p>
                        </td>
                    </tr>
                </table>

                <!-- ── Column 4: Custom Image ── -->
                <h2 style="border-bottom:2px solid #cc2129;padding-bottom:8px;margin-top:28px;">Column 4 — Custom Image</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="tnt_col4_heading">Column Heading</label></th>
                        <td>
                            <input type="text" id="tnt_col4_heading" name="tnt_marine_footer_settings[col4_heading]"
                                   value="<?php echo esc_attr( $fw['col4_heading'] ); ?>" class="regular-text" placeholder="Brokerage Boat Sales">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_col4_image_url">Image URL</label></th>
                        <td>
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                                <input type="url" id="tnt_col4_image_url" name="tnt_marine_footer_settings[col4_image_url]"
                                       value="<?php echo esc_attr( $fw['col4_image_url'] ); ?>" class="large-text"
                                       placeholder="https://yourdomain.com/wp-content/uploads/your-image.jpg">
                                <button type="button" class="button" id="tnt-footer-img-pick">Choose Image</button>
                            </div>
                            <?php if ( $fw['col4_image_url'] ) : ?>
                                <div style="margin-top:10px;">
                                    <img src="<?php echo esc_url( $fw['col4_image_url'] ); ?>" alt="Footer Image Preview"
                                         style="max-width:240px;border-radius:6px;border:1px solid #ddd;display:block;">
                                </div>
                            <?php endif; ?>
                            <p class="description">Upload via <strong>Media → Add New</strong>, then paste the URL here, or click "Choose Image" to pick from the media library.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_col4_image_link">Image Link URL</label></th>
                        <td>
                            <input type="url" id="tnt_col4_image_link" name="tnt_marine_footer_settings[col4_image_link]"
                                   value="<?php echo esc_attr( $fw['col4_image_link'] ); ?>" class="large-text"
                                   placeholder="https://...  (leave blank for no link)">
                            <p class="description">When visitors click the image, they'll be sent here. Leave blank for a non-clickable image.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_col4_caption">Caption Text</label></th>
                        <td>
                            <input type="text" id="tnt_col4_caption" name="tnt_marine_footer_settings[col4_caption]"
                                   value="<?php echo esc_attr( $fw['col4_caption'] ); ?>" class="large-text"
                                   placeholder="Optional caption displayed below the image">
                        </td>
                    </tr>
                </table>

                <!-- ── Copyright Bar ── -->
                <h2 style="border-bottom:2px solid #cc2129;padding-bottom:8px;margin-top:28px;">Copyright Bar</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="tnt_footer_copyright">Copyright Text</label></th>
                        <td>
                            <input type="text" id="tnt_footer_copyright" name="tnt_marine_footer_settings[footer_copyright]"
                                   value="<?php echo esc_attr( $fw['footer_copyright'] ); ?>" class="large-text"
                                   placeholder="© <?php echo date('Y'); ?> TNT Custom Marine. All rights reserved.">
                            <p class="description">Displayed on the left of the bottom bar. Leave blank to auto-generate from business name.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tnt_footer_credit_text">Right-side Credit</label></th>
                        <td>
                            <input type="text" id="tnt_footer_credit_text" name="tnt_marine_footer_settings[footer_credit_text]"
                                   value="<?php echo esc_attr( $fw['footer_credit_text'] ); ?>" class="large-text"
                                   placeholder="Marine Listings powered by Cox Group">
                            <p class="description">Displayed on the right side of the bottom bar. Leave blank to hide.</p>
                        </td>
                    </tr>
                </table>

            </div>

            <!-- WP Media Picker for footer image -->
            <script>
            (function($){
                $('#tnt-footer-img-pick').on('click', function(e){
                    e.preventDefault();
                    var frame = wp.media({
                        title: 'Choose Footer Image',
                        button: { text: 'Use this image' },
                        multiple: false,
                        library: { type: 'image' }
                    });
                    frame.on('select', function(){
                        var url = frame.state().get('selection').first().toJSON().url;
                        $('#tnt_col4_image_url').val(url);
                    });
                    frame.open();
                });
            })(jQuery);
            </script>

            <?php endif; ?>

            <?php submit_button( 'Save Settings' ); ?>
        </form>
    </div>
    <?php
}

// ── Helpers: get settings with defaults ───────────────────────────────────

function tnt_marine_get_email_settings(): array {
    $saved = get_option( 'tnt_marine_email_settings', [] );
    return wp_parse_args( $saved, [
        'to_email'      => 'dylan@coxgp.com',
        'from_name'     => 'TNT Custom Marine',
        'from_email'    => 'inquiry@tntcustommarine.com',
        'reply_to'      => 'noreply@tntcustommarine.com',
        'cc'            => '',
        'bcc'           => '',
        'email_subject' => 'New Inquiry:',
    ] );
}

function tnt_marine_get_settings(): array {
    $saved = get_option( 'tnt_marine_settings', [] );
    return wp_parse_args( $saved, [
        // Display
        'listings_per_page'    => 12,
        'sold_per_page'        => 12,
        'card_columns'         => 3,
        'show_price'           => '1',
        'show_year'            => '1',
        'show_length'          => '1',
        'show_location'        => '1',
        'sold_badge_text'      => 'SOLD',
        'sold_badge_color'     => '#cc2129',
        'primary_color'        => '#1a2e4a',
        'accent_color'         => '#cc2129',
        'save_inquiries_to_db' => '1',
        // Inquiry form
        'form_heading'           => 'Inquire About This Vessel',
        'form_button_text'       => 'Send Inquiry',
        'form_success_message'   => 'Your inquiry has been sent. We will be in touch shortly.',
        'form_phone_required'    => '',
        'form_default_message'   => 'I am interested in this vessel and would like more information.',
        'form_show_on_sold'      => '',
        // Company
        'dashboard_logo_url' => '',
        'company_name'    => 'TNT Custom Marine',
        'company_phone'   => '',
        'company_email'   => '',
        'company_address' => '',
        'business_hours'  => '',
        'google_maps_url' => '',
        // Social
        'facebook_url'  => '',
        'instagram_url' => '',
        'youtube_url'   => '',
        'twitter_url'   => '',
        'tiktok_url'    => '',
    ] );
}

// ── Internal: table row helper ─────────────────────────────────────────────

function tnt_field_row( $key, $label, $type, $value, $option_name, $desc = '', $placeholder = '' ) {
    $name = $option_name . '[' . $key . ']';
    $id   = 'tnt_' . $key;
    echo '<tr>';
    echo '<th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th>';
    echo '<td>';
    echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="' . esc_attr( $placeholder ) . '">';
    if ( $desc ) echo '<p class="description">' . esc_html( $desc ) . '</p>';
    echo '</td>';
    echo '</tr>';
}
