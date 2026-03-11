<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* =========================================================================
   TNT Marine Listings – Settings Page
   Adds a "TNT Marine" menu item in WP Admin → Settings.
   All email configuration (To, From Name, From Email, Reply-To, CC, BCC)
   is stored in a single option and used by the inquiry form.
   ========================================================================= */

// ── Handle misc admin actions (clear log, etc.) ────────────────────────────

add_action( 'admin_init', function() {
    if (
        isset( $_GET['tnt_clear_log'] ) &&
        current_user_can( 'manage_options' ) &&
        isset( $_GET['page'] ) && $_GET['page'] === 'tnt-marine-settings'
    ) {
        delete_option( 'tnt_marine_drive_log' );
        wp_safe_redirect( admin_url( 'options-general.php?page=tnt-marine-settings' ) );
        exit;
    }
} );

// ── Plugin action links (adds "Settings" link on the Plugins screen) ──────

function tnt_marine_plugin_action_links( $links ) {
    $settings_link = '<a href="' . admin_url( 'options-general.php?page=tnt-marine-settings' ) . '">Settings</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_tnt-marine-listings-v2/tnt-marine-listings.php', 'tnt_marine_plugin_action_links' );

// ── Register settings ──────────────────────────────────────────────────────

function tnt_marine_register_settings() {
    register_setting(
        'tnt_marine_settings_group',
        'tnt_marine_email_settings',
        [ 'sanitize_callback' => 'tnt_marine_sanitize_email_settings' ]
    );
    register_setting(
        'tnt_marine_drive_group',
        'tnt_marine_drive_settings',
        [ 'sanitize_callback' => 'tnt_marine_sanitize_drive_settings' ]
    );
}
add_action( 'admin_init', 'tnt_marine_register_settings' );

/**
 * Sanitise and save Drive settings.
 * Reschedules the sync cron if the interval has changed.
 */
function tnt_marine_sanitize_drive_settings( $input ): array {
    $old_interval = get_option( 'tnt_marine_drive_settings', [] )['sync_interval'] ?? 'hourly';
    $new_interval = sanitize_text_field( $input['sync_interval'] ?? 'hourly' );

    // Accept either a bare folder ID or a full Drive URL; extract just the ID.
    $raw_folder = sanitize_text_field( $input['drive_folder_id'] ?? '' );
    if ( preg_match( '#/folders/([a-zA-Z0-9_-]+)#', $raw_folder, $m ) ) {
        $raw_folder = $m[1];
    }

    $sanitized = [
        'service_account_json' => wp_unslash( $input['service_account_json'] ?? '' ), // JSON – do not strip
        'drive_folder_id'      => $raw_folder,
        'sync_interval'        => $new_interval,
    ];

    // If the interval changed, clear and reschedule the cron
    if ( $old_interval !== $new_interval ) {
        TNT_Drive_Sync::clear_cron();
        if ( ! empty( $sanitized['service_account_json'] ) && ! empty( $sanitized['drive_folder_id'] ) ) {
            wp_schedule_event( time() + 60, $new_interval, 'tnt_marine_drive_sync' );
        }
    }

    return $sanitized;
}

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

        <!-- ── GOOGLE DRIVE SYNC ─────────────────────────────────────────── -->
        <form method="post" action="options.php">
            <?php settings_fields( 'tnt_marine_drive_group' ); ?>

            <?php
            $drive = get_option( 'tnt_marine_drive_settings', [] );
            $last_sync = get_option( 'tnt_marine_drive_last_sync', null );
            ?>

            <h2 style="border-bottom:2px solid #cc2129;padding-bottom:8px;margin-top:40px;">
                🔗 Google Drive Auto-Sync
            </h2>
            <p style="color:#666;margin-bottom:4px;">
                Connect a Google Drive folder to automatically create and update boat listings.
                Place one <strong>master Google Sheet</strong> in the root of your shared folder
                (one row per listing), then create a <strong>subfolder per listing</strong>
                — named exactly after the Title in that row — and drop the boat's
                <strong>images</strong> inside it.
            </p>
            <p style="color:#666;margin-bottom:20px;">
                Need help setting this up? Download the
                <a href="<?php echo esc_url( admin_url( 'options-general.php?page=tnt-marine-settings&tnt_action=download_guide' ) ); ?>">
                    setup guide →
                </a>
            </p>

            <table class="form-table" role="presentation">

                <tr>
                    <th scope="row"><label for="tnt_service_account_json">Service Account JSON <span style="color:#c00;">*</span></label></th>
                    <td>
                        <textarea id="tnt_service_account_json"
                                  name="tnt_marine_drive_settings[service_account_json]"
                                  rows="8"
                                  class="large-text code"
                                  placeholder='Paste the full contents of your Google Cloud service account .json key file here…'
                                  style="font-size:11px;"><?php echo esc_textarea( $drive['service_account_json'] ?? '' ); ?></textarea>
                        <p class="description">
                            Create a service account in Google Cloud Console, download the JSON key,
                            and paste the entire file contents above.
                            Then share your Drive folder with the service account's email address.
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="tnt_drive_folder_id">Drive Parent Folder ID <span style="color:#c00;">*</span></label></th>
                    <td>
                        <input type="text" id="tnt_drive_folder_id"
                               name="tnt_marine_drive_settings[drive_folder_id]"
                               value="<?php echo esc_attr( $drive['drive_folder_id'] ?? '' ); ?>"
                               class="regular-text"
                               placeholder="1A2B3C4D5E6F7G8H9I0J…">
                        <p class="description">
                            Open your Drive folder in a browser. The ID is the last part of the URL:<br>
                            <code>https://drive.google.com/drive/folders/<strong>THIS_IS_THE_ID</strong></code>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="tnt_sync_interval">Sync Frequency</label></th>
                    <td>
                        <select id="tnt_sync_interval" name="tnt_marine_drive_settings[sync_interval]" class="regular-text">
                            <?php
                            $intervals = [
                                'hourly'        => 'Every Hour',
                                'every_2_hours' => 'Every 2 Hours',
                                'every_3_hours' => 'Every 3 Hours',
                                'every_4_hours' => 'Every 4 Hours',
                                'every_6_hours' => 'Every 6 Hours',
                            ];
                            $current = $drive['sync_interval'] ?? 'hourly';
                            foreach ( $intervals as $key => $label ) {
                                echo '<option value="' . esc_attr( $key ) . '" ' . selected( $current, $key, false ) . '>'
                                   . esc_html( $label ) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description">How often WordPress should check your Drive folder for new or changed listings.</p>
                    </td>
                </tr>

            </table>

            <?php submit_button( 'Save Drive Settings' ); ?>
        </form>

        <!-- ── MANUAL SYNC ───────────────────────────────────────────────── -->
        <div style="margin-top:30px;">
            <h3 style="margin-bottom:8px;">⚡ Manual Sync</h3>
            <p style="color:#666;margin-bottom:12px;">
                Run the Drive sync right now (does not wait for the next scheduled run).
                Last sync: <strong id="tnt-last-sync"><?php echo esc_html( $last_sync ?? 'Never' ); ?></strong>
            </p>
            <button id="tnt-run-sync" class="button button-primary">
                Sync Now
            </button>
            <span id="tnt-sync-spinner" class="spinner" style="float:none;margin:4px 8px;display:none;"></span>
            <span id="tnt-sync-result" style="color:#198754;font-weight:600;display:none;">✓ Done!</span>
        </div>

        <!-- ── SYNC LOG ──────────────────────────────────────────────────── -->
        <div style="margin-top:30px;">
            <h3 style="margin-bottom:8px;">📋 Sync Log</h3>
            <?php
            $logs = get_option( 'tnt_marine_drive_log', [] );
            if ( empty( $logs ) ) {
                echo '<p style="color:#666;">No sync activity yet.</p>';
            } else {
                echo '<div style="background:#1d2327;color:#b4bbc8;font-family:monospace;font-size:12px;padding:14px 16px;border-radius:4px;max-height:260px;overflow-y:auto;">';
                foreach ( $logs as $line ) {
                    $color = strpos( $line, 'ERROR' ) !== false ? '#ff7070' : '#b4bbc8';
                    // Use nl2br so any embedded newlines (e.g. from key diagnostics) render visibly.
                    echo '<div style="color:' . esc_attr( $color ) . ';padding:1px 0;word-break:break-all;">' . nl2br( esc_html( $line ) ) . '</div>';
                }
                echo '</div>';
                echo '<p style="margin-top:8px;"><a href="' . esc_url( add_query_arg( 'tnt_clear_log', '1' ) ) . '">Clear log</a></p>';
            }
            ?>
        </div>

    </div>

    <script>
    (function($){
        $('#tnt-run-sync').on('click', function(){
            var $btn     = $(this);
            var $spinner = $('#tnt-sync-spinner');
            var $result  = $('#tnt-sync-result');
            $btn.prop('disabled', true);
            $spinner.show();
            $result.hide();

            $.post(ajaxurl, {
                action : 'tnt_drive_manual_sync',
                nonce  : '<?php echo esc_js( wp_create_nonce( 'tnt_drive_sync_nonce' ) ); ?>'
            }, function(response){
                $spinner.hide();
                $btn.prop('disabled', false);
                if (response.success) {
                    $result.show();
                    $('#tnt-last-sync').text(response.data.last_sync);
                    // Reload to refresh the log
                    setTimeout(function(){ location.reload(); }, 1200);
                } else {
                    alert('Sync failed. Check the log for details.');
                }
            }).fail(function(){
                $spinner.hide();
                $btn.prop('disabled', false);
                alert('Request failed. Please try again.');
            });
        });
    })(jQuery);
    </script>
    <?php
    // Note: closing </div class="wrap"> is handled inside the script block above
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
