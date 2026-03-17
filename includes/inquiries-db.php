<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* =========================================================================
   TNT Marine Listings – Inquiry Database
   Stores every form submission for the sales team.
   Table: {prefix}tnt_marine_inquiries
   ========================================================================= */

// ── Create / upgrade the table ────────────────────────────────────────────

function tnt_marine_create_inquiries_table() {
    global $wpdb;
    $table   = $wpdb->prefix . 'tnt_marine_inquiries';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id          bigint(20)   NOT NULL AUTO_INCREMENT,
        name        varchar(100) NOT NULL DEFAULT '',
        email       varchar(100) NOT NULL DEFAULT '',
        phone       varchar(30)  NOT NULL DEFAULT '',
        message     text         NOT NULL,
        listing_id  bigint(20)   NOT NULL DEFAULT 0,
        listing_title   varchar(200) NOT NULL DEFAULT '',
        listing_make    varchar(100) NOT NULL DEFAULT '',
        listing_model   varchar(100) NOT NULL DEFAULT '',
        listing_year    varchar(10)  NOT NULL DEFAULT '',
        listing_price   varchar(20)  NOT NULL DEFAULT '',
        listing_length  varchar(20)  NOT NULL DEFAULT '',
        listing_location varchar(100) NOT NULL DEFAULT '',
        status      varchar(20)  NOT NULL DEFAULT 'new',
        notes       text         NOT NULL,
        ip_address  varchar(45)  NOT NULL DEFAULT '',
        created_at  datetime     NOT NULL,
        PRIMARY KEY  (id),
        KEY email      (email),
        KEY status     (status),
        KEY listing_id (listing_id),
        KEY created_at (created_at)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
    update_option( 'tnt_marine_db_version', '1.0' );
}

// Run on every admin load if the table hasn't been created yet.
add_action( 'admin_init', function () {
    if ( get_option( 'tnt_marine_db_version' ) !== '1.0' ) {
        tnt_marine_create_inquiries_table();
    }
} );

// ── Save a submission ─────────────────────────────────────────────────────

function tnt_marine_save_inquiry( array $data ): int {
    global $wpdb;
    $table = $wpdb->prefix . 'tnt_marine_inquiries';

    $wpdb->insert(
        $table,
        [
            'name'             => $data['name']             ?? '',
            'email'            => $data['email']            ?? '',
            'phone'            => $data['phone']            ?? '',
            'message'          => $data['message']          ?? '',
            'listing_id'       => intval( $data['listing_id'] ?? 0 ),
            'listing_title'    => $data['listing_title']    ?? '',
            'listing_make'     => $data['listing_make']     ?? '',
            'listing_model'    => $data['listing_model']    ?? '',
            'listing_year'     => $data['listing_year']     ?? '',
            'listing_price'    => $data['listing_price']    ?? '',
            'listing_length'   => $data['listing_length']   ?? '',
            'listing_location' => $data['listing_location'] ?? '',
            'status'           => 'new',
            'notes'            => '',
            'ip_address'       => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
            'created_at'       => current_time( 'mysql' ),
        ],
        [ '%s','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' ]
    );

    return (int) $wpdb->insert_id;
}

// ── Admin menu ────────────────────────────────────────────────────────────

function tnt_marine_inquiries_menu() {
    add_submenu_page(
        'edit.php?post_type=marine_listing',
        'Inquiry Database',
        'Inquiries',
        'manage_options',
        'tnt-marine-inquiries',
        'tnt_marine_inquiries_page'
    );
}
add_action( 'admin_menu', 'tnt_marine_inquiries_menu' );

// ── AJAX: update inquiry status / notes ──────────────────────────────────

function tnt_marine_ajax_update_inquiry() {
    check_ajax_referer( 'tnt_marine_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

    global $wpdb;
    $table  = $wpdb->prefix . 'tnt_marine_inquiries';
    $id     = intval( $_POST['inquiry_id'] ?? 0 );
    $status = sanitize_text_field( $_POST['status'] ?? 'new' );
    $notes  = sanitize_textarea_field( $_POST['notes'] ?? '' );

    if ( ! in_array( $status, [ 'new', 'contacted', 'closed' ], true ) ) {
        wp_send_json_error( [ 'message' => 'Invalid status.' ] );
    }

    $wpdb->update(
        $table,
        [ 'status' => $status, 'notes' => $notes ],
        [ 'id' => $id ],
        [ '%s', '%s' ],
        [ '%d' ]
    );

    wp_send_json_success();
}
add_action( 'wp_ajax_tnt_marine_update_inquiry', 'tnt_marine_ajax_update_inquiry' );

// ── CSV Export ────────────────────────────────────────────────────────────

add_action( 'admin_init', function () {
    if (
        isset( $_GET['page'], $_GET['tnt_export_nonce'] )
        && $_GET['page'] === 'tnt-marine-inquiries'
        && wp_verify_nonce( $_GET['tnt_export_nonce'], 'tnt_marine_export' )
        && current_user_can( 'manage_options' )
    ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tnt_marine_inquiries';
        $rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=tnt-marine-inquiries-' . date( 'Y-m-d' ) . '.csv' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'ID','Date','Name','Email','Phone','Message','Listing','Make','Model','Year','Price','Length','Location','Status','Notes','IP' ] );
        foreach ( $rows as $row ) {
            fputcsv( $out, [
                $row['id'], $row['created_at'], $row['name'], $row['email'], $row['phone'],
                $row['message'], $row['listing_title'], $row['listing_make'], $row['listing_model'],
                $row['listing_year'], $row['listing_price'], $row['listing_length'],
                $row['listing_location'], $row['status'], $row['notes'], $row['ip_address'],
            ] );
        }
        fclose( $out );
        exit;
    }
} );

// ── Admin page ────────────────────────────────────────────────────────────

function tnt_marine_inquiries_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    global $wpdb;
    $table = $wpdb->prefix . 'tnt_marine_inquiries';

    // Detail view
    $detail_id = isset( $_GET['detail'] ) ? intval( $_GET['detail'] ) : 0;
    if ( $detail_id ) {
        $inquiry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $detail_id ) );
        tnt_marine_inquiry_detail_html( $inquiry );
        return;
    }

    $filter_status = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';
    $search        = isset( $_GET['tnt_search'] ) ? sanitize_text_field( $_GET['tnt_search'] ) : '';

    // Counts
    $count_all       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
    $count_new       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status='new'" );
    $count_contacted = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status='contacted'" );
    $count_closed    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status='closed'" );

    // Query
    $where_parts = [];
    if ( $filter_status ) {
        $where_parts[] = $wpdb->prepare( 'status = %s', $filter_status );
    }
    if ( $search ) {
        $like = '%' . $wpdb->esc_like( $search ) . '%';
        $where_parts[] = $wpdb->prepare(
            '(name LIKE %s OR email LIKE %s OR phone LIKE %s OR listing_title LIKE %s OR listing_make LIKE %s OR listing_model LIKE %s)',
            $like, $like, $like, $like, $like, $like
        );
    }
    $where = $where_parts ? 'WHERE ' . implode( ' AND ', $where_parts ) : '';
    $rows  = $wpdb->get_results( "SELECT * FROM $table $where ORDER BY created_at DESC" );

    $export_url  = wp_nonce_url( admin_url( 'admin.php?page=tnt-marine-inquiries' ), 'tnt_marine_export', 'tnt_export_nonce' );
    $admin_nonce = wp_create_nonce( 'tnt_marine_admin_nonce' );

    $status_colors = [ 'new' => '#2271b1', 'contacted' => '#f0821d', 'closed' => '#00a32a' ];
    $status_labels = [ 'new' => 'New', 'contacted' => 'Contacted', 'closed' => 'Closed' ];
    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
            <span style="display:flex;align-items:center;gap:10px;flex:1;">
                <span style="display:inline-block;background:#cc2129;color:#fff;font-size:13px;font-weight:700;padding:3px 10px;border-radius:4px;letter-spacing:.04em;">TNT MARINE</span>
                Inquiry Database
            </span>
            <a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary">⬇ Export to CSV</a>
        </h1>

        <!-- Stats Cards -->
        <div style="display:flex;gap:14px;margin-bottom:20px;flex-wrap:wrap;">
            <?php
            $stats = [
                [ 'New',         $count_new,       '#2271b1', 'new' ],
                [ 'Contacted',   $count_contacted,  '#f0821d', 'contacted' ],
                [ 'Closed',      $count_closed,     '#00a32a', 'closed' ],
                [ 'Total',       $count_all,        '#1a2e4a', '' ],
            ];
            foreach ( $stats as [ $label, $count, $color, $fs ] ) {
                $url    = add_query_arg( [ 'page' => 'tnt-marine-inquiries', 'filter_status' => $fs ], admin_url( 'admin.php' ) );
                $active = ( $filter_status === $fs ) ? "box-shadow:0 0 0 3px {$color}40;transform:translateY(-1px);" : '';
                echo '<a href="' . esc_url( $url ) . '" style="text-decoration:none;background:#fff;border-radius:8px;padding:14px 22px;border:2px solid ' . $color . ';min-width:110px;text-align:center;transition:all .15s;' . $active . '">';
                echo '<div style="font-size:26px;font-weight:800;color:' . $color . ';line-height:1;">' . intval( $count ) . '</div>';
                echo '<div style="font-size:12px;color:#666;margin-top:5px;font-weight:600;">' . esc_html( $label ) . '</div>';
                echo '</a>';
            }
            ?>
        </div>

        <!-- Search -->
        <form method="get" style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="page" value="tnt-marine-inquiries">
            <input type="hidden" name="filter_status" value="<?php echo esc_attr( $filter_status ); ?>">
            <input type="search" name="tnt_search" value="<?php echo esc_attr( $search ); ?>"
                   placeholder="Search name, email, phone, listing…"
                   style="padding:6px 12px;border:1px solid #ddd;border-radius:4px;font-size:13px;width:280px;">
            <button type="submit" class="button">Search</button>
            <?php if ( $search || $filter_status ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=tnt-marine-inquiries' ) ); ?>" class="button button-secondary">Clear Filters</a>
            <?php endif; ?>
            <span style="color:#666;font-size:13px;margin-left:auto;"><?php echo count( $rows ); ?> result<?php echo count( $rows ) !== 1 ? 's' : ''; ?></span>
        </form>

        <?php if ( empty( $rows ) ) : ?>
            <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:48px;text-align:center;color:#666;">
                <div style="font-size:48px;margin-bottom:12px;">📭</div>
                <p style="font-size:18px;margin:0 0 8px;font-weight:600;">No inquiries yet.</p>
                <p style="margin:0;color:#999;">When visitors submit inquiry forms on your listings, they'll appear here.</p>
            </div>
        <?php else : ?>
            <div style="background:#fff;border:1px solid #ddd;border-radius:8px;overflow:hidden;">
                <table class="wp-list-table widefat fixed striped" style="margin:0;border:none;">
                    <thead>
                        <tr style="background:#1a2e4a;">
                            <th style="color:#fff;width:100px;">Date</th>
                            <th style="color:#fff;">Name</th>
                            <th style="color:#fff;">Email</th>
                            <th style="color:#fff;width:110px;">Phone</th>
                            <th style="color:#fff;">Listing</th>
                            <th style="color:#fff;width:50px;">Year</th>
                            <th style="color:#fff;">Make / Model</th>
                            <th style="color:#fff;width:90px;">Price</th>
                            <th style="color:#fff;width:90px;">Status</th>
                            <th style="color:#fff;width:170px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $row ) :
                            $detail_url  = add_query_arg( [ 'page' => 'tnt-marine-inquiries', 'detail' => $row->id ], admin_url( 'admin.php' ) );
                            $badge_color = $status_colors[ $row->status ] ?? '#999';
                            $slabel      = $status_labels[ $row->status ] ?? ucfirst( $row->status );
                        ?>
                        <tr id="inq-row-<?php echo intval( $row->id ); ?>">
                            <td style="font-size:12px;white-space:nowrap;">
                                <?php echo esc_html( date( 'M j, Y', strtotime( $row->created_at ) ) ); ?><br>
                                <span style="color:#999;"><?php echo esc_html( date( 'g:i a', strtotime( $row->created_at ) ) ); ?></span>
                            </td>
                            <td><strong><?php echo esc_html( $row->name ); ?></strong></td>
                            <td style="word-break:break-all;"><a href="mailto:<?php echo esc_attr( $row->email ); ?>"><?php echo esc_html( $row->email ); ?></a></td>
                            <td><?php echo $row->phone ? '<a href="tel:' . esc_attr( preg_replace( '/\D/', '', $row->phone ) ) . '">' . esc_html( $row->phone ) . '</a>' : '<span style="color:#ccc;">—</span>'; ?></td>
                            <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?php if ( $row->listing_title ) : ?>
                                    <a href="<?php echo esc_url( $detail_url ); ?>" title="<?php echo esc_attr( $row->listing_title ); ?>"><?php echo esc_html( $row->listing_title ); ?></a>
                                <?php else : ?>—<?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $row->listing_year ?: '—' ); ?></td>
                            <td><?php echo esc_html( trim( $row->listing_make . ' ' . $row->listing_model ) ?: '—' ); ?></td>
                            <td style="white-space:nowrap;font-weight:700;color:#cc2129;">
                                <?php echo $row->listing_price ? '$' . number_format( floatval( $row->listing_price ) ) : '<span style="color:#ccc;">—</span>'; ?>
                            </td>
                            <td>
                                <span style="display:inline-block;background:<?php echo $badge_color; ?>;color:#fff;font-size:11px;font-weight:700;padding:2px 9px;border-radius:20px;">
                                    <?php echo esc_html( $slabel ); ?>
                                </span>
                            </td>
                            <td style="white-space:nowrap;">
                                <a href="<?php echo esc_url( $detail_url ); ?>" class="button button-small">View</a>
                                <?php if ( $row->status !== 'contacted' ) : ?>
                                    <button type="button" class="button button-small tnt-quick-status"
                                            data-id="<?php echo intval( $row->id ); ?>"
                                            data-status="contacted"
                                            data-nonce="<?php echo $admin_nonce; ?>">Contacted</button>
                                <?php endif; ?>
                                <?php if ( $row->status !== 'closed' ) : ?>
                                    <button type="button" class="button button-small tnt-quick-status"
                                            data-id="<?php echo intval( $row->id ); ?>"
                                            data-status="closed"
                                            data-nonce="<?php echo $admin_nonce; ?>">Close</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <script>
            document.querySelectorAll('.tnt-quick-status').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (!confirm('Update status to "' + this.dataset.status + '"?')) return;
                    var id = this.dataset.id, status = this.dataset.status, nonce = this.dataset.nonce;
                    btn.disabled = true;
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=tnt_marine_update_inquiry&nonce=' + encodeURIComponent(nonce) +
                              '&inquiry_id=' + id + '&status=' + encodeURIComponent(status) + '&notes='
                    }).then(r => r.json()).then(data => {
                        if (data.success) location.reload();
                        else { btn.disabled = false; alert('Error updating status.'); }
                    });
                });
            });
            </script>
        <?php endif; ?>
    </div>
    <?php
}

// ── Detail view ───────────────────────────────────────────────────────────

function tnt_marine_inquiry_detail_html( $inquiry ) {
    if ( ! $inquiry ) {
        echo '<div class="wrap"><p>Inquiry not found.</p></div>';
        return;
    }

    $back_url        = admin_url( 'admin.php?page=tnt-marine-inquiries' );
    $admin_nonce     = wp_create_nonce( 'tnt_marine_admin_nonce' );
    $listing_url     = $inquiry->listing_id ? get_permalink( $inquiry->listing_id ) : '';
    $listing_edit    = $inquiry->listing_id ? get_edit_post_link( $inquiry->listing_id ) : '';
    $status_colors   = [ 'new' => '#2271b1', 'contacted' => '#f0821d', 'closed' => '#00a32a' ];
    $badge_color     = $status_colors[ $inquiry->status ] ?? '#999';
    $first_name      = explode( ' ', trim( $inquiry->name ) )[0];
    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:24px;">
            <span style="display:inline-block;background:#cc2129;color:#fff;font-size:13px;font-weight:700;padding:3px 10px;border-radius:4px;">TNT MARINE</span>
            Inquiry #<?php echo intval( $inquiry->id ); ?>
            <a href="<?php echo esc_url( $back_url ); ?>" class="button button-secondary" style="margin-left:auto;">← All Inquiries</a>
        </h1>

        <div style="display:grid;grid-template-columns:minmax(0,2fr) 280px;gap:24px;align-items:start;">

            <!-- Left -->
            <div>
                <!-- Contact -->
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:24px;margin-bottom:20px;">
                    <h2 style="margin-top:0;font-size:16px;border-bottom:2px solid #cc2129;padding-bottom:8px;">Contact Information</h2>
                    <table style="width:100%;border-collapse:collapse;">
                        <?php
                        $fields = [
                            'Name'    => esc_html( $inquiry->name ),
                            'Email'   => '<a href="mailto:' . esc_attr( $inquiry->email ) . '">' . esc_html( $inquiry->email ) . '</a>',
                            'Phone'   => $inquiry->phone ? '<a href="tel:' . esc_attr( preg_replace( '/\D/', '', $inquiry->phone ) ) . '">' . esc_html( $inquiry->phone ) . '</a>' : '<span style="color:#ccc;">Not provided</span>',
                            'Date'    => esc_html( date( 'F j, Y \a\t g:i a', strtotime( $inquiry->created_at ) ) ),
                            'IP'      => '<span style="color:#999;font-size:12px;">' . esc_html( $inquiry->ip_address ) . '</span>',
                        ];
                        foreach ( $fields as $label => $val ) {
                            echo '<tr>';
                            echo '<td style="padding:8px 0;color:#999;width:90px;font-size:11px;text-transform:uppercase;letter-spacing:.06em;font-weight:600;">' . esc_html( $label ) . '</td>';
                            echo '<td style="padding:8px 0;border-bottom:1px solid #f0f0f0;">' . $val . '</td>';
                            echo '</tr>';
                        }
                        ?>
                    </table>
                </div>

                <!-- Message -->
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:24px;margin-bottom:20px;">
                    <h2 style="margin-top:0;font-size:16px;border-bottom:2px solid #cc2129;padding-bottom:8px;">Message</h2>
                    <div style="background:#f9f9f9;border-left:4px solid #cc2129;padding:16px 20px;border-radius:0 6px 6px 0;line-height:1.75;color:#333;">
                        <?php echo nl2br( esc_html( $inquiry->message ) ); ?>
                    </div>
                </div>

                <!-- Listing -->
                <?php if ( $inquiry->listing_title ) : ?>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:24px;">
                    <h2 style="margin-top:0;font-size:16px;border-bottom:2px solid #cc2129;padding-bottom:8px;">Listing Inquired About</h2>
                    <table style="width:100%;border-collapse:collapse;">
                        <?php
                        $lfields = [];
                        $ltitle  = esc_html( $inquiry->listing_title );
                        if ( $listing_url )  $ltitle .= ' <a href="' . esc_url( $listing_url ) . '" target="_blank" style="font-size:11px;">[view]</a>';
                        if ( $listing_edit ) $ltitle .= ' <a href="' . esc_url( $listing_edit ) . '" style="font-size:11px;">[edit]</a>';
                        $lfields['Listing'] = $ltitle;
                        if ( $inquiry->listing_make || $inquiry->listing_model ) $lfields['Make / Model'] = esc_html( trim( $inquiry->listing_make . ' ' . $inquiry->listing_model ) );
                        if ( $inquiry->listing_year     ) $lfields['Year']     = esc_html( $inquiry->listing_year );
                        if ( $inquiry->listing_price    ) $lfields['Price']    = '<strong style="color:#cc2129;font-size:16px;">$' . number_format( floatval( $inquiry->listing_price ) ) . '</strong>';
                        if ( $inquiry->listing_length   ) $lfields['Length']   = esc_html( $inquiry->listing_length ) . 'ft';
                        if ( $inquiry->listing_location ) $lfields['Location'] = esc_html( $inquiry->listing_location );
                        foreach ( $lfields as $label => $val ) {
                            echo '<tr>';
                            echo '<td style="padding:8px 0;color:#999;width:100px;font-size:11px;text-transform:uppercase;letter-spacing:.06em;font-weight:600;">' . esc_html( $label ) . '</td>';
                            echo '<td style="padding:8px 0;border-bottom:1px solid #f0f0f0;">' . $val . '</td>';
                            echo '</tr>';
                        }
                        ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right: Status & Notes -->
            <div style="position:sticky;top:32px;">
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:22px;">
                    <h2 style="margin-top:0;font-size:15px;border-bottom:2px solid #cc2129;padding-bottom:8px;">Status</h2>

                    <p style="margin:0 0 4px;font-size:11px;color:#999;text-transform:uppercase;letter-spacing:.06em;font-weight:600;">Current</p>
                    <p style="margin:0 0 18px;">
                        <span style="display:inline-block;background:<?php echo $badge_color; ?>;color:#fff;font-size:13px;font-weight:700;padding:4px 14px;border-radius:20px;">
                            <?php echo esc_html( ucfirst( $inquiry->status ) ); ?>
                        </span>
                    </p>

                    <label style="display:block;font-size:11px;color:#999;text-transform:uppercase;letter-spacing:.06em;font-weight:600;margin-bottom:4px;">Update Status</label>
                    <select id="tnt-detail-status" style="width:100%;margin-bottom:14px;padding:6px 8px;border:1px solid #ddd;border-radius:4px;">
                        <option value="new"       <?php selected( $inquiry->status, 'new' ); ?>>New</option>
                        <option value="contacted" <?php selected( $inquiry->status, 'contacted' ); ?>>Contacted</option>
                        <option value="closed"    <?php selected( $inquiry->status, 'closed' ); ?>>Closed</option>
                    </select>

                    <label style="display:block;font-size:11px;color:#999;text-transform:uppercase;letter-spacing:.06em;font-weight:600;margin-bottom:4px;">Internal Notes</label>
                    <textarea id="tnt-detail-notes" rows="5" style="width:100%;resize:vertical;margin-bottom:12px;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:13px;box-sizing:border-box;"><?php echo esc_textarea( $inquiry->notes ); ?></textarea>

                    <button type="button" id="tnt-detail-save" class="button button-primary" style="width:100%;"
                            data-id="<?php echo intval( $inquiry->id ); ?>"
                            data-nonce="<?php echo $admin_nonce; ?>">
                        Save Changes
                    </button>
                    <div id="tnt-save-msg" style="display:none;text-align:center;margin-top:8px;color:#00a32a;font-weight:600;">✓ Saved</div>

                    <hr style="margin:18px 0;">

                    <a href="mailto:<?php echo esc_attr( $inquiry->email ); ?>" class="button button-secondary" style="width:100%;text-align:center;display:block;margin-bottom:8px;box-sizing:border-box;">
                        ✉ Email <?php echo esc_html( $first_name ); ?>
                    </a>
                    <?php if ( $inquiry->phone ) : ?>
                        <a href="tel:<?php echo esc_attr( preg_replace( '/\D/', '', $inquiry->phone ) ); ?>" class="button button-secondary" style="width:100%;text-align:center;display:block;box-sizing:border-box;">
                            📞 Call <?php echo esc_html( $first_name ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <script>
    document.getElementById('tnt-detail-save').addEventListener('click', function() {
        var btn    = this;
        var id     = btn.dataset.id;
        var nonce  = btn.dataset.nonce;
        var status = document.getElementById('tnt-detail-status').value;
        var notes  = document.getElementById('tnt-detail-notes').value;
        var msg    = document.getElementById('tnt-save-msg');

        btn.disabled    = true;
        btn.textContent = 'Saving…';

        fetch(ajaxurl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=tnt_marine_update_inquiry'
                + '&nonce='       + encodeURIComponent(nonce)
                + '&inquiry_id='  + encodeURIComponent(id)
                + '&status='      + encodeURIComponent(status)
                + '&notes='       + encodeURIComponent(notes)
        }).then(r => r.json()).then(function(data) {
            btn.disabled    = false;
            btn.textContent = 'Save Changes';
            if (data.success) {
                msg.style.display = 'block';
                setTimeout(function() { msg.style.display = 'none'; }, 3000);
            } else {
                alert('Save failed. Please try again.');
            }
        });
    });
    </script>
    <?php
}
