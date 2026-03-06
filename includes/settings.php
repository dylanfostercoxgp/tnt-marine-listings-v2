<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* =========================================================================
   TNT Marine Listings – Settings Page
   Adds a "TNT Marine" menu item in WP Admin → Settings.
   All email configuration (To, From Name, From Email, Reply-To, CC, BCC)
   is stored in a single option and used by the inquiry form.
   ========================================================================= */

// ── Register settings ──────────────────────────────────────────────────────

function tnt_marine_register_settings() {
    register_setting(
        'tnt_marine_settings_group',
        'tnt_marine_email_settings',
        [ 'sanitize_callback' => 'tnt_marine_sanitize_email_settings' ]
    );
}
add_action( 'admin_init', 'tnt_marine_register_settings' );

/**
 * Sanitise the submitted settings array.
 */
function tnt_marine_sanitize_email_settings( $input ): array {
    return [
        'to_email'      => sanitize_email( $input['to_email']      ?? '' ),
        'from_name'     => sanitize_text_field( $input['from_name'] ?? '' ),
        'from_email'    => sanitize_email( $input['from_email']     ?? '' ),
        'reply_to'      => sanitize_email( $input['reply_to']       ?? '' ),
        'cc'            => sanitize_text_field( $input['cc']         ?? '' ),
        'bcc'           => sanitize_text_field( $input['bcc']        ?? '' ),
        'email_subject' => sanitize_text_field( $input['email_subject'] ?? '' ),
    ];
}

// ── Add menu ───────────────────────────────────────────────────────────────

function tnt_marine_add_settings_page() {
    add_options_page(
        'TNT Marine Settings',   // Page title
        'TNT Marine',            // Menu label
        'manage_options',        // Capability
        'tnt-marine-settings',   // Slug
        'tnt_marine_settings_page_html'
    );
}
add_action( 'admin_menu', 'tnt_marine_add_settings_page' );

// ── Render page ────────────────────────────────────────────────────────────

function tnt_marine_settings_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $opts = tnt_marine_get_email_settings();

    // Show save notice
    $saved = isset( $_GET['settings-updated'] ) && $_GET['settings-updated'];
    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:10px;">
            <span style="display:inline-block;background:#cc2129;color:#fff;font-size:13px;font-weight:700;padding:3px 10px;border-radius:4px;letter-spacing:.04em;">TNT MARINE</span>
            Settings
        </h1>

        <?php if ( $saved ) : ?>
            <div class="notice notice-success is-dismissible"><p><strong>Settings saved.</strong></p></div>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php settings_fields( 'tnt_marine_settings_group' ); ?>

            <!-- ── EMAIL SETTINGS ────────────────────────────────────────── -->
            <h2 style="border-bottom:2px solid #cc2129;padding-bottom:8px;margin-top:28px;">
                📧 Inquiry Email Settings
            </h2>
            <p style="color:#666;margin-bottom:20px;">
                Configure where inquiry emails are sent and how they appear to recipients.
                Leave a field blank to use the default WordPress value.
            </p>

            <table class="form-table" role="presentation">

                <tr>
                    <th scope="row"><label for="tnt_to_email">Send Inquiries To <span style="color:#c00;">*</span></label></th>
                    <td>
                        <input type="email" id="tnt_to_email"
                               name="tnt_marine_email_settings[to_email]"
                               value="<?php echo esc_attr( $opts['to_email'] ); ?>"
                               class="regular-text"
                               placeholder="you@yourdomain.com">
                        <p class="description">The email address that receives all inquiry notifications.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="tnt_from_name">From Name</label></th>
                    <td>
                        <input type="text" id="tnt_from_name"
                               name="tnt_marine_email_settings[from_name]"
                               value="<?php echo esc_attr( $opts['from_name'] ); ?>"
                               class="regular-text"
                               placeholder="TNT Custom Marine">
                        <p class="description">The name that appears in the "From" field of the email.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="tnt_from_email">From Email</label></th>
                    <td>
                        <input type="email" id="tnt_from_email"
                               name="tnt_marine_email_settings[from_email]"
                               value="<?php echo esc_attr( $opts['from_email'] ); ?>"
                               class="regular-text"
                               placeholder="inquiry@yourdomain.com">
                        <p class="description">The email address the inquiry is sent from.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="tnt_reply_to">Reply-To</label></th>
                    <td>
                        <input type="email" id="tnt_reply_to"
                               name="tnt_marine_email_settings[reply_to]"
                               value="<?php echo esc_attr( $opts['reply_to'] ); ?>"
                               class="regular-text"
                               placeholder="noreply@yourdomain.com">
                        <p class="description">When you click Reply in your inbox, this address will be used.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="tnt_cc">CC</label></th>
                    <td>
                        <input type="text" id="tnt_cc"
                               name="tnt_marine_email_settings[cc]"
                               value="<?php echo esc_attr( $opts['cc'] ); ?>"
                               class="regular-text"
                               placeholder="person@example.com, another@example.com">
                        <p class="description">Optional. Separate multiple addresses with commas.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="tnt_bcc">BCC</label></th>
                    <td>
                        <input type="text" id="tnt_bcc"
                               name="tnt_marine_email_settings[bcc]"
                               value="<?php echo esc_attr( $opts['bcc'] ); ?>"
                               class="regular-text"
                               placeholder="archive@example.com">
                        <p class="description">Optional. Separate multiple addresses with commas.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="tnt_email_subject">Email Subject Prefix</label></th>
                    <td>
                        <input type="text" id="tnt_email_subject"
                               name="tnt_marine_email_settings[email_subject]"
                               value="<?php echo esc_attr( $opts['email_subject'] ); ?>"
                               class="regular-text"
                               placeholder="New Inquiry:">
                        <p class="description">
                            Appears before the listing title in the subject line.<br>
                            e.g. "New Inquiry:" → subject becomes <em>New Inquiry: 2023 Sea Ray 390</em>
                        </p>
                    </td>
                </tr>

            </table>

            <?php submit_button( 'Save Settings' ); ?>
        </form>
    </div>
    <?php
}

// ── Helper: get settings with defaults ────────────────────────────────────

/**
 * Returns the email settings array, merged with sensible defaults.
 * Use this function anywhere in the plugin to read email config.
 */
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
