<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* =========================================================================
   TNT Marine Listings – Front-End Inquiry Dashboard
   Shortcode: [marine_inquiries]
   Renders a full CRM-style dashboard for the sales team on any WP page.
   Password-protect the page in WP to restrict access.
   ========================================================================= */

// ── AJAX: update status and/or notes ─────────────────────────────────────

function tnt_marine_fe_update_inquiry() {
    if ( ! check_ajax_referer( 'tnt_fe_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed.' ] );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'tnt_marine_inquiries';
    $id    = intval( $_POST['id'] ?? 0 );

    if ( ! $id ) wp_send_json_error( [ 'message' => 'Invalid ID.' ] );

    $update  = [];
    $formats = [];

    if ( isset( $_POST['status'] ) ) {
        $status = sanitize_text_field( $_POST['status'] );
        if ( ! in_array( $status, [ 'new', 'contacted', 'closed' ], true ) ) {
            wp_send_json_error( [ 'message' => 'Invalid status.' ] );
        }
        $update['status'] = $status;
        $formats[]        = '%s';
    }

    if ( isset( $_POST['notes'] ) ) {
        $update['notes'] = sanitize_textarea_field( wp_unslash( $_POST['notes'] ) );
        $formats[]       = '%s';
    }

    if ( ! empty( $update ) ) {
        $wpdb->update( $table, $update, [ 'id' => $id ], $formats, [ '%d' ] );
    }

    wp_send_json_success();
}
add_action( 'wp_ajax_tnt_marine_fe_update',        'tnt_marine_fe_update_inquiry' );
add_action( 'wp_ajax_nopriv_tnt_marine_fe_update', 'tnt_marine_fe_update_inquiry' );

// ── Export (CSV / Excel SpreadsheetML) ────────────────────────────────────

add_action( 'init', function () {
    if ( ! isset( $_GET['tnt_inq_export'], $_GET['tnt_inq_nonce'] ) ) return;
    if ( ! wp_verify_nonce( sanitize_text_field( $_GET['tnt_inq_nonce'] ), 'tnt_fe_export' ) ) return;

    global $wpdb;
    $table = $wpdb->prefix . 'tnt_marine_inquiries';
    $rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A );

    $fmt = sanitize_key( $_GET['tnt_inq_export'] );

    if ( $fmt === 'excel' ) {
        tnt_marine_fe_export_excel( $rows );
    } else {
        tnt_marine_fe_export_csv( $rows );
    }
    exit;
} );

function tnt_marine_fe_export_csv( array $rows ) {
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=tnt-inquiries-' . date( 'Y-m-d' ) . '.csv' );
    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, [ 'Date', 'Name', 'Email', 'Phone', 'Message', 'Listing', 'Make', 'Model', 'Year', 'Price', 'Length (ft)', 'Location', 'Status', 'Notes' ] );
    foreach ( $rows as $r ) {
        fputcsv( $out, [
            $r['created_at'], $r['name'], $r['email'], $r['phone'], $r['message'],
            $r['listing_title'], $r['listing_make'], $r['listing_model'], $r['listing_year'],
            $r['listing_price'] ? '$' . number_format( (float) $r['listing_price'] ) : '',
            $r['listing_length'], $r['listing_location'], $r['status'], $r['notes'],
        ] );
    }
    fclose( $out );
}

function tnt_marine_fe_export_excel( array $rows ) {
    $x = function( $v ) { return htmlspecialchars( (string) $v, ENT_XML1, 'UTF-8' ); };

    $cols = [
        'Date', 'Name', 'Email', 'Phone', 'Message',
        'Listing', 'Make', 'Model', 'Year', 'Price', 'Length (ft)', 'Location', 'Status', 'Notes',
    ];

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
    $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
    $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"';
    $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel">' . "\n";
    $xml .= '<Styles>';
    $xml .= '<Style ss:ID="H"><Font ss:Bold="1" ss:Size="11"/><Interior ss:Color="#1a2e4a" ss:Pattern="Solid"/><Font ss:Color="#FFFFFF" ss:Bold="1"/></Style>';
    $xml .= '<Style ss:ID="N"><Alignment ss:WrapText="1"/></Style>';
    $xml .= '</Styles>';
    $xml .= '<Worksheet ss:Name="Inquiries"><Table ss:DefaultColumnWidth="80">';

    $xml .= '<Row>';
    foreach ( $cols as $c ) {
        $xml .= '<Cell ss:StyleID="H"><Data ss:Type="String">' . $x( $c ) . '</Data></Cell>';
    }
    $xml .= '</Row>';

    foreach ( $rows as $r ) {
        $vals = [
            $r['created_at'], $r['name'], $r['email'], $r['phone'], $r['message'],
            $r['listing_title'], $r['listing_make'], $r['listing_model'], $r['listing_year'],
            $r['listing_price'] ? '$' . number_format( (float) $r['listing_price'] ) : '',
            $r['listing_length'], $r['listing_location'], ucfirst( $r['status'] ), $r['notes'],
        ];
        $xml .= '<Row>';
        foreach ( $vals as $v ) {
            $xml .= '<Cell ss:StyleID="N"><Data ss:Type="String">' . $x( $v ) . '</Data></Cell>';
        }
        $xml .= '</Row>';
    }

    $xml .= '</Table></Worksheet></Workbook>';

    header( 'Content-Type: application/vnd.ms-excel; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=tnt-inquiries-' . date( 'Y-m-d' ) . '.xls' );
    echo $xml;
}

// ── Shortcode ─────────────────────────────────────────────────────────────

function tnt_marine_inquiries_dashboard_shortcode( $atts ) {
    global $wpdb;
    $table = $wpdb->prefix . 'tnt_marine_inquiries';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
        return '<p style="padding:24px;color:#666;">Inquiry database not yet initialised — visit WP Admin once to set it up.</p>';
    }

    $rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A );

    // Attach listing permalink to each row for front-end hyperlinking
    foreach ( $rows as &$row ) {
        $lid = intval( $row['listing_id'] ?? 0 );
        $row['listing_url'] = $lid ? get_permalink( $lid ) : '';
    }
    unset( $row );

    $settings     = tnt_marine_get_settings();
    $logo_url     = ! empty( $settings['dashboard_logo_url'] ) ? esc_url( $settings['dashboard_logo_url'] ) : '';
    $company_name = ! empty( $settings['company_name'] ) ? esc_html( $settings['company_name'] ) : 'TNT Marine';

    $nonce        = wp_create_nonce( 'tnt_fe_nonce' );
    $export_nonce = wp_create_nonce( 'tnt_fe_export' );
    $ajax_url     = admin_url( 'admin-ajax.php' );
    $csv_url      = esc_url( add_query_arg( [ 'tnt_inq_export' => 'csv',   'tnt_inq_nonce' => $export_nonce ], home_url( '/' ) ) );
    $xls_url      = esc_url( add_query_arg( [ 'tnt_inq_export' => 'excel', 'tnt_inq_nonce' => $export_nonce ], home_url( '/' ) ) );

    $total     = count( $rows );
    $cnt_new   = count( array_filter( $rows, fn( $r ) => $r['status'] === 'new' ) );
    $cnt_cont  = count( array_filter( $rows, fn( $r ) => $r['status'] === 'contacted' ) );
    $cnt_close = count( array_filter( $rows, fn( $r ) => $r['status'] === 'closed' ) );

    $json = str_replace( '</', '<\/', wp_json_encode( $rows, JSON_HEX_TAG ) );

    ob_start();
    ?>
<div id="tnt-fe-dash">
<style>
/* ─── Reset ─────────────────────────────────────────────────────────── */
#tnt-fe-dash *,#tnt-fe-dash *::before,#tnt-fe-dash *::after{box-sizing:border-box;margin:0;padding:0;}
#tnt-fe-dash{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,sans-serif;font-size:14px;color:#1e293b;background:#eef2f7;min-height:100vh;line-height:1.5;}

/* ─── Outer page wrapper ────────────────────────────────────────────── */
.tnt-fe-page{max-width:1440px;margin:0 auto;padding:0 0 48px;}

/* ─── Header ────────────────────────────────────────────────────────── */
.tnt-fe-header{background:linear-gradient(135deg,#0c1623 0%,#1a3a5c 100%);padding:20px 32px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;box-shadow:0 2px 12px rgba(0,0,0,.25);}
.tnt-fe-header-brand{display:flex;align-items:center;gap:14px;}
.tnt-fe-header-logo{height:44px;width:auto;display:block;object-fit:contain;object-position:left;}
.tnt-fe-header-wordmark{display:flex;flex-direction:column;}
.tnt-fe-header-wordmark h1{color:#fff;font-size:18px;font-weight:700;letter-spacing:-.02em;line-height:1.2;}
.tnt-fe-header-wordmark p{color:rgba(255,255,255,.5);font-size:11px;margin-top:2px;letter-spacing:.02em;}
.tnt-fe-exports{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
.tnt-fe-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .15s;white-space:nowrap;line-height:1;}
.tnt-fe-btn svg{flex-shrink:0;}
.tnt-fe-btn-outline{background:rgba(255,255,255,.08);color:#fff;border:1px solid rgba(255,255,255,.2);}
.tnt-fe-btn-outline:hover{background:rgba(255,255,255,.16);color:#fff;text-decoration:none;border-color:rgba(255,255,255,.35);}
.tnt-fe-btn-red{background:#cc2129;color:#fff;border:1px solid transparent;}
.tnt-fe-btn-red:hover{background:#b01d23;color:#fff;text-decoration:none;}

/* ─── Stats strip ───────────────────────────────────────────────────── */
.tnt-fe-stats{display:grid;grid-template-columns:repeat(4,1fr);background:#fff;border-bottom:1px solid #dde3ed;}
.tnt-fe-stat{padding:20px 32px;cursor:pointer;transition:background .15s;position:relative;border-right:1px solid #dde3ed;}
.tnt-fe-stat:last-child{border-right:none;}
.tnt-fe-stat::after{content:'';position:absolute;bottom:0;left:0;right:0;height:3px;background:var(--sc);opacity:0;transition:opacity .15s;}
.tnt-fe-stat:hover{background:#f8fafc;}
.tnt-fe-stat.active::after{opacity:1;}
.tnt-fe-stat.active{background:#f8fafc;}
.tnt-fe-stat-num{font-size:32px;font-weight:800;color:var(--sc);line-height:1.1;}
.tnt-fe-stat-label{font-size:11px;color:#64748b;margin-top:5px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;}

/* ─── Content area ──────────────────────────────────────────────────── */
.tnt-fe-content{padding:24px 32px 0;}

/* ─── Toolbar ───────────────────────────────────────────────────────── */
.tnt-fe-toolbar{background:#fff;border:1px solid #dde3ed;border-radius:10px 10px 0 0;padding:14px 20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.tnt-fe-search-wrap{position:relative;flex:1;min-width:220px;}
.tnt-fe-search-wrap svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;}
#tnt-fe-search{width:100%;padding:9px 12px 9px 38px;border:1px solid #dde3ed;border-radius:7px;font-size:13px;outline:none;transition:border-color .2s;color:#1e293b;background:#f8fafc;}
#tnt-fe-search:focus{border-color:#1a3a5c;background:#fff;}
#tnt-fe-status-filter{padding:9px 14px;border:1px solid #dde3ed;border-radius:7px;font-size:13px;background:#f8fafc;cursor:pointer;outline:none;color:#1e293b;min-height:38px;}
#tnt-fe-status-filter:focus{border-color:#1a3a5c;}
.tnt-fe-count{margin-left:auto;font-size:12px;color:#94a3b8;white-space:nowrap;font-weight:500;}

/* ─── Table wrapper ─────────────────────────────────────────────────── */
.tnt-fe-table-wrap{overflow-x:auto;background:#fff;border:1px solid #dde3ed;border-top:none;border-radius:0 0 10px 10px;box-shadow:0 2px 8px rgba(0,0,0,.04);}
#tnt-fe-table{width:100%;border-collapse:collapse;font-size:13px;min-width:820px;}

/* ─── Table head ────────────────────────────────────────────────────── */
#tnt-fe-table thead tr{background:#0f172a;}
#tnt-fe-table th{padding:12px 16px;color:rgba(255,255,255,.65);font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;white-space:nowrap;user-select:none;}
#tnt-fe-table th.sortable{cursor:pointer;}
#tnt-fe-table th.sortable:hover{color:#fff;}
#tnt-fe-table th.sortable::after{content:'↕';margin-left:5px;opacity:.35;font-size:9px;}
#tnt-fe-table th.sort-asc::after{content:'↑';opacity:1;}
#tnt-fe-table th.sort-desc::after{content:'↓';opacity:1;}

/* ─── Table body ────────────────────────────────────────────────────── */
#tnt-fe-table tbody tr{border-bottom:1px solid #f1f5f9;transition:background .1s;}
#tnt-fe-table tbody tr:hover{background:#f8fafc;}
#tnt-fe-table tbody tr:last-child{border-bottom:none;}
#tnt-fe-table td{padding:13px 16px;vertical-align:middle;}
.tnt-fe-muted{color:#94a3b8;}
.tnt-fe-name{font-weight:600;color:#0f172a;}
.tnt-fe-price{font-weight:700;color:#cc2129;white-space:nowrap;}
.tnt-fe-date{font-size:12px;white-space:nowrap;color:#374151;}
.tnt-fe-date span{display:block;color:#94a3b8;margin-top:2px;font-size:11px;}
.tnt-fe-contact a{color:#1a3a5c;text-decoration:none;display:block;font-size:13px;}
.tnt-fe-contact a:hover{color:#cc2129;text-decoration:underline;}
.tnt-fe-contact span.tnt-fe-muted{display:block;}
.tnt-listing-link{color:#1a3a5c;font-weight:600;text-decoration:none;}
.tnt-listing-link:hover{color:#cc2129;text-decoration:underline;}

/* ─── Status pill ───────────────────────────────────────────────────── */
.tnt-fe-status-sel{padding:5px 10px;border-radius:20px;font-size:11px;font-weight:700;border:none;cursor:pointer;outline:none;appearance:none;-webkit-appearance:none;text-align:center;min-width:90px;}
.tnt-fe-status-sel.s-new{background:#dbeafe;color:#1d4ed8;}
.tnt-fe-status-sel.s-contacted{background:#fef3c7;color:#92400e;}
.tnt-fe-status-sel.s-closed{background:#d1fae5;color:#065f46;}

/* ─── Notes cell ────────────────────────────────────────────────────── */
.tnt-fe-notes-cell{min-width:160px;max-width:200px;}
.tnt-fe-notes-display{max-height:44px;overflow:hidden;cursor:pointer;line-height:1.5;color:#475569;font-size:12px;white-space:pre-wrap;word-break:break-word;}
.tnt-fe-notes-display:empty::before,.tnt-fe-notes-add{color:#94a3b8;font-style:italic;cursor:pointer;font-size:12px;}
.tnt-fe-notes-display:hover{color:#1a3a5c;}
.tnt-fe-notes-input{width:100%;min-height:64px;padding:7px 9px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;resize:vertical;font-family:inherit;outline:none;display:none;}
.tnt-fe-notes-input:focus{border-color:#1a3a5c;}
.tnt-fe-notes-actions{display:none;gap:5px;margin-top:5px;}
.tnt-fe-notes-save,.tnt-fe-notes-cancel{padding:5px 11px;border-radius:5px;font-size:11px;font-weight:600;cursor:pointer;border:none;}
.tnt-fe-notes-save{background:#1a3a5c;color:#fff;}
.tnt-fe-notes-save:hover{background:#cc2129;}
.tnt-fe-notes-cancel{background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;}

/* ─── View button ───────────────────────────────────────────────────── */
.tnt-fe-view-btn{padding:7px 14px;background:#f1f5f9;border:1px solid #dde3ed;border-radius:6px;font-size:12px;font-weight:600;color:#1a3a5c;cursor:pointer;white-space:nowrap;transition:all .15s;min-height:34px;}
.tnt-fe-view-btn:hover{background:#1a3a5c;color:#fff;border-color:#1a3a5c;}

/* ─── Empty state ───────────────────────────────────────────────────── */
.tnt-fe-empty{text-align:center;padding:64px 24px;color:#94a3b8;}
.tnt-fe-empty-icon{font-size:42px;margin-bottom:14px;opacity:.6;}
.tnt-fe-empty p{font-size:15px;font-weight:500;}

/* ─── Modal ─────────────────────────────────────────────────────────── */
#tnt-fe-modal{position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.6);display:none;align-items:flex-start;justify-content:flex-end;padding:0;backdrop-filter:blur(2px);}
.tnt-fe-modal-panel{background:#fff;width:100%;max-width:580px;height:100dvh;overflow-y:auto;box-shadow:-10px 0 50px rgba(0,0,0,.2);display:flex;flex-direction:column;}
.tnt-fe-modal-header{background:linear-gradient(135deg,#0c1623,#1a3a5c);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;gap:12px;}
.tnt-fe-modal-header h2{color:#fff;font-size:16px;font-weight:700;line-height:1.2;}
.tnt-fe-modal-close{background:rgba(255,255,255,.12);border:none;color:#fff;width:34px;height:34px;min-width:34px;border-radius:7px;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;transition:background .15s;flex-shrink:0;}
.tnt-fe-modal-close:hover{background:rgba(255,255,255,.25);}
.tnt-fe-modal-body{padding:24px;flex:1;display:flex;flex-direction:column;gap:18px;}
.tnt-fe-modal-section{border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;}
.tnt-fe-modal-section-head{background:#f8fafc;padding:10px 18px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#64748b;border-bottom:1px solid #e2e8f0;}
.tnt-fe-modal-section-body{padding:16px 18px;}
.tnt-fe-modal-row{display:flex;padding:8px 0;border-bottom:1px solid #f1f5f9;align-items:baseline;gap:14px;}
.tnt-fe-modal-row:last-child{border-bottom:none;}
.tnt-fe-modal-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;width:80px;flex-shrink:0;}
.tnt-fe-modal-val{font-size:13px;color:#1e293b;flex:1;word-break:break-word;}
.tnt-fe-modal-val a{color:#1a3a5c;text-decoration:none;}
.tnt-fe-modal-val a:hover{color:#cc2129;text-decoration:underline;}
.tnt-fe-modal-price{font-size:22px;font-weight:800;color:#cc2129;}
.tnt-fe-message-box{background:#f8fafc;border-left:3px solid #cc2129;padding:12px 16px;border-radius:0 8px 8px 0;font-size:13px;line-height:1.7;color:#374151;white-space:pre-wrap;word-break:break-word;}
.tnt-fe-modal-status-sel{width:100%;padding:10px 14px;border:1px solid #dde3ed;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;outline:none;margin-bottom:14px;background:#fff;}
.tnt-fe-modal-notes{width:100%;padding:10px 14px;border:1px solid #dde3ed;border-radius:7px;font-size:13px;min-height:110px;resize:vertical;font-family:inherit;outline:none;margin-bottom:12px;background:#fff;line-height:1.6;}
.tnt-fe-modal-notes:focus,.tnt-fe-modal-status-sel:focus{border-color:#1a3a5c;}
.tnt-fe-modal-save-btn{width:100%;padding:12px;background:#cc2129;color:#fff;border:none;border-radius:7px;font-size:14px;font-weight:700;cursor:pointer;transition:background .15s;}
.tnt-fe-modal-save-btn:hover{background:#a81920;}
.tnt-fe-modal-saved{text-align:center;font-size:12px;color:#059669;font-weight:600;margin-top:8px;display:none;}
.tnt-fe-quick-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.tnt-fe-quick-link{display:flex;align-items:center;gap:9px;padding:11px 16px;border:1px solid #e2e8f0;border-radius:8px;text-decoration:none;color:#1a3a5c;font-size:13px;font-weight:600;transition:all .15s;background:#fff;}
.tnt-fe-quick-link:hover{background:#1a3a5c;color:#fff;text-decoration:none;border-color:#1a3a5c;}
.tnt-fe-quick-link .ql-icon{width:32px;height:32px;border-radius:6px;background:#eef2f7;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;transition:background .15s;}
.tnt-fe-quick-link:hover .ql-icon{background:rgba(255,255,255,.15);}

/* ─── Mobile ────────────────────────────────────────────────────────── */
@media(max-width:900px){
    .tnt-fe-content{padding:16px 16px 0;}
    .tnt-fe-header{padding:16px 20px;}
    .tnt-fe-header-logo{height:36px;}
    .tnt-fe-stats{grid-template-columns:1fr 1fr;}
    .tnt-fe-stat{padding:16px 20px;border-bottom:1px solid #dde3ed;}
    .tnt-fe-stat:nth-child(2){border-right:none;}
    .tnt-fe-stat:nth-child(3){border-right:1px solid #dde3ed;border-bottom:none;}
    .tnt-fe-stat:nth-child(4){border-right:none;border-bottom:none;}
    .tnt-fe-stat-num{font-size:26px;}
}
@media(max-width:640px){
    .tnt-fe-header{padding:14px 16px;gap:12px;}
    .tnt-fe-header-wordmark h1{font-size:16px;}
    .tnt-fe-exports{width:100%;}
    .tnt-fe-btn{flex:1;justify-content:center;}
    .tnt-fe-toolbar{padding:12px 14px;gap:10px;}
    #tnt-fe-search,#tnt-fe-status-filter{font-size:16px;}/* prevent iOS zoom */
    .tnt-fe-count{display:none;}
    .tnt-fe-modal-panel{max-width:100%;border-radius:0;}
    .tnt-fe-modal-body{padding:16px;}
    .tnt-fe-quick-actions{grid-template-columns:1fr;}
    .tnt-fe-modal-row{flex-wrap:wrap;gap:4px;}
    .tnt-fe-modal-label{width:100%;}
}
</style>

<div class="tnt-fe-page">

<!-- HEADER -->
<div class="tnt-fe-header">
    <div class="tnt-fe-header-brand">
        <?php if ( $logo_url ) : ?>
            <img src="<?php echo $logo_url; ?>" alt="<?php echo $company_name; ?>" class="tnt-fe-header-logo">
        <?php else : ?>
            <div style="background:#cc2129;color:#fff;font-size:12px;font-weight:800;padding:5px 10px;border-radius:4px;letter-spacing:.06em;"><?php echo $company_name; ?></div>
        <?php endif; ?>
        <div class="tnt-fe-header-wordmark">
            <h1>Inquiry Dashboard</h1>
            <p>Sales Team View &mdash; <span id="tnt-fe-header-count"><?php echo intval( $total ); ?></span> total inquiries</p>
        </div>
    </div>
    <div class="tnt-fe-exports">
        <a href="<?php echo $csv_url; ?>" class="tnt-fe-btn tnt-fe-btn-outline">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export CSV
        </a>
        <a href="<?php echo $xls_url; ?>" class="tnt-fe-btn tnt-fe-btn-red">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export Excel
        </a>
    </div>
</div>

<!-- STATS -->
<div class="tnt-fe-stats">
    <div class="tnt-fe-stat" style="--sc:#0f172a;" onclick="TNTDash.filterStatus('')" id="tnt-stat-all">
        <div class="tnt-fe-stat-num" id="tnt-cnt-all"><?php echo intval( $total ); ?></div>
        <div class="tnt-fe-stat-label">Total</div>
    </div>
    <div class="tnt-fe-stat" style="--sc:#2563eb;" onclick="TNTDash.filterStatus('new')" id="tnt-stat-new">
        <div class="tnt-fe-stat-num" id="tnt-cnt-new"><?php echo intval( $cnt_new ); ?></div>
        <div class="tnt-fe-stat-label">New</div>
    </div>
    <div class="tnt-fe-stat" style="--sc:#d97706;" onclick="TNTDash.filterStatus('contacted')" id="tnt-stat-contacted">
        <div class="tnt-fe-stat-num" id="tnt-cnt-contacted"><?php echo intval( $cnt_cont ); ?></div>
        <div class="tnt-fe-stat-label">Contacted</div>
    </div>
    <div class="tnt-fe-stat" style="--sc:#059669;" onclick="TNTDash.filterStatus('closed')" id="tnt-stat-closed">
        <div class="tnt-fe-stat-num" id="tnt-cnt-closed"><?php echo intval( $cnt_close ); ?></div>
        <div class="tnt-fe-stat-label">Closed</div>
    </div>
</div>

<!-- CONTENT -->
<div class="tnt-fe-content">

    <!-- TOOLBAR -->
    <div class="tnt-fe-toolbar">
        <div class="tnt-fe-search-wrap">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="search" id="tnt-fe-search" placeholder="Search name, email, phone, listing, notes&hellip;" autocomplete="off">
        </div>
        <select id="tnt-fe-status-filter" aria-label="Filter by status">
            <option value="">All Statuses</option>
            <option value="new">New</option>
            <option value="contacted">Contacted</option>
            <option value="closed">Closed</option>
        </select>
        <span class="tnt-fe-count" id="tnt-fe-count"></span>
    </div>

    <!-- TABLE -->
    <div class="tnt-fe-table-wrap">
        <table id="tnt-fe-table" role="grid">
            <thead>
                <tr>
                    <th class="sortable" data-col="created_at">Date</th>
                    <th class="sortable" data-col="name">Name</th>
                    <th>Contact</th>
                    <th class="sortable" data-col="listing_title">Listing</th>
                    <th class="sortable" data-col="listing_year">Year</th>
                    <th class="sortable" data-col="listing_make">Make / Model</th>
                    <th class="sortable" data-col="listing_price">Price</th>
                    <th>Status</th>
                    <th>Notes</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tnt-fe-tbody">
                <tr><td colspan="10" style="text-align:center;padding:48px;color:#94a3b8;font-size:14px;">Loading&hellip;</td></tr>
            </tbody>
        </table>
    </div>

</div><!-- .tnt-fe-content -->

</div><!-- .tnt-fe-page -->

<!-- DETAIL MODAL -->
<div id="tnt-fe-modal" role="dialog" aria-modal="true" aria-label="Inquiry Detail">
    <div class="tnt-fe-modal-panel">
        <div class="tnt-fe-modal-header">
            <h2>Inquiry Detail</h2>
            <button class="tnt-fe-modal-close" onclick="TNTDash.closeModal()" aria-label="Close">&#x2715;</button>
        </div>
        <div class="tnt-fe-modal-body" id="tnt-fe-modal-body"><!-- JS rendered --></div>
    </div>
</div>

</div><!-- #tnt-fe-dash -->

<script>
(function(){
'use strict';

const RAW      = <?php echo $json; ?>;
const AJAX_URL = <?php echo wp_json_encode( $ajax_url ); ?>;
const NONCE    = <?php echo wp_json_encode( $nonce ); ?>;

let state   = { search: '', status: '', sort: { col: 'created_at', dir: 'desc' } };
let current = [];

/* ── helpers ── */
function esc(s){ const d=document.createElement('div');d.textContent=String(s||'');return d.innerHTML; }
function fmtDate(d){
    if(!d) return '<span class="tnt-fe-muted">—</span>';
    const dt = new Date(d.replace(' ','T'));
    return dt.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})
         + '<span>' + dt.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'}) + '</span>';
}
function fmtPrice(p){ return p ? '$'+Number(p).toLocaleString() : '—'; }
function statusClass(s){ return {new:'s-new',contacted:'s-contacted',closed:'s-closed'}[s]||'s-new'; }
function statusLabel(s){ return {new:'New',contacted:'Contacted',closed:'Closed'}[s]||s; }

/* ── stats ── */
function refreshStats(){
    const byS={new:0,contacted:0,closed:0};
    RAW.forEach(r=>{ if(byS[r.status]!==undefined) byS[r.status]++; });
    document.getElementById('tnt-cnt-all').textContent = RAW.length;
    document.getElementById('tnt-cnt-new').textContent = byS.new;
    document.getElementById('tnt-cnt-contacted').textContent = byS.contacted;
    document.getElementById('tnt-cnt-closed').textContent = byS.closed;
    document.getElementById('tnt-fe-header-count').textContent = RAW.length;
}

/* ── filter + sort ── */
function applyFilters(){
    const q = state.search.toLowerCase();
    current = RAW.filter(r => {
        if(state.status && r.status !== state.status) return false;
        if(q){
            const fields=[r.name,r.email,r.phone,r.listing_title,r.listing_make,r.listing_model,r.notes,r.message,r.listing_year,r.listing_location];
            if(!fields.some(f=>(f||'').toLowerCase().includes(q))) return false;
        }
        return true;
    });
    const { col, dir } = state.sort;
    current.sort((a,b)=>{
        let va = a[col]||'', vb = b[col]||'';
        if(['listing_price','listing_year','listing_length'].includes(col)){ va=parseFloat(va)||0; vb=parseFloat(vb)||0; }
        const cmp = va < vb ? -1 : va > vb ? 1 : 0;
        return dir==='asc' ? cmp : -cmp;
    });
}

/* ── render table ── */
function render(){
    applyFilters();
    refreshStats();
    const tbody   = document.getElementById('tnt-fe-tbody');
    const countEl = document.getElementById('tnt-fe-count');
    countEl.textContent = current.length + ' result' + (current.length!==1?'s':'');

    if(!current.length){
        tbody.innerHTML = '<tr><td colspan="10"><div class="tnt-fe-empty"><div class="tnt-fe-empty-icon">📭</div><p>No inquiries match your filters.</p></div></td></tr>';
        return;
    }

    tbody.innerHTML = current.map(r => {
        // Build listing cell – hyperlinked if we have a URL
        const listingText = esc(r.listing_title) || '<span class="tnt-fe-muted">—</span>';
        const listingCell = r.listing_url
            ? `<a href="${esc(r.listing_url)}" class="tnt-listing-link" target="_blank" rel="noopener">${listingText} <sup style="font-size:9px;opacity:.6">↗</sup></a>`
            : listingText;

        return `
        <tr data-id="${r.id}">
            <td class="tnt-fe-date">${fmtDate(r.created_at)}</td>
            <td class="tnt-fe-name">${esc(r.name)}</td>
            <td class="tnt-fe-contact">
                <a href="mailto:${esc(r.email)}">${esc(r.email)}</a>
                ${r.phone ? `<a href="tel:${esc(r.phone.replace(/\D/g,''))}">${esc(r.phone)}</a>` : '<span class="tnt-fe-muted">—</span>'}
            </td>
            <td>${listingCell}</td>
            <td>${esc(r.listing_year)||'<span class="tnt-fe-muted">—</span>'}</td>
            <td>${esc((r.listing_make+' '+r.listing_model).trim())||'<span class="tnt-fe-muted">—</span>'}</td>
            <td class="tnt-fe-price">${fmtPrice(r.listing_price)}</td>
            <td>
                <select class="tnt-fe-status-sel ${statusClass(r.status)}" data-id="${r.id}"
                        onchange="TNTDash.updateStatus(${r.id},this.value,this)"
                        aria-label="Status for ${esc(r.name)}">
                    <option value="new"       ${r.status==='new'       ?'selected':''}>New</option>
                    <option value="contacted" ${r.status==='contacted' ?'selected':''}>Contacted</option>
                    <option value="closed"    ${r.status==='closed'    ?'selected':''}>Closed</option>
                </select>
            </td>
            <td>
                <div class="tnt-fe-notes-cell" data-id="${r.id}">
                    ${r.notes
                        ? `<div class="tnt-fe-notes-display" title="Click to edit">${esc(r.notes)}</div>`
                        : `<span class="tnt-fe-notes-add" title="Click to add note">Add note&hellip;</span>`
                    }
                    <textarea class="tnt-fe-notes-input" aria-label="Notes">${esc(r.notes)}</textarea>
                    <div class="tnt-fe-notes-actions">
                        <button class="tnt-fe-notes-save"   onclick="TNTDash.saveNotes(${r.id})">Save</button>
                        <button class="tnt-fe-notes-cancel" onclick="TNTDash.cancelNotes(${r.id})">Cancel</button>
                    </div>
                </div>
            </td>
            <td><button class="tnt-fe-view-btn" onclick="TNTDash.openModal(${r.id})">View &rarr;</button></td>
        </tr>
        `;
    }).join('');

    /* attach notes click targets */
    document.querySelectorAll('.tnt-fe-notes-display, .tnt-fe-notes-add').forEach(el => {
        el.addEventListener('click', function(){
            const cell = this.closest('.tnt-fe-notes-cell');
            this.style.display = 'none';
            const ta = cell.querySelector('.tnt-fe-notes-input');
            ta.style.display = 'block';
            ta.focus();
            cell.querySelector('.tnt-fe-notes-actions').style.display = 'flex';
        });
    });
}

/* ── sort ── */
function sortBy(col){
    state.sort.dir = state.sort.col === col ? (state.sort.dir==='asc'?'desc':'asc') : 'asc';
    state.sort.col = col;
    document.querySelectorAll('#tnt-fe-table th.sortable').forEach(th => {
        th.classList.remove('sort-asc','sort-desc');
        if(th.dataset.col === col) th.classList.add('sort-'+state.sort.dir);
    });
    render();
}

/* ── filter by status card ── */
function filterStatus(s){
    state.status = s;
    document.getElementById('tnt-fe-status-filter').value = s;
    document.querySelectorAll('.tnt-fe-stat').forEach(el => el.classList.remove('active'));
    const active = document.getElementById('tnt-stat-'+(s||'all'));
    if(active) active.classList.add('active');
    render();
}

/* ── update status ── */
function updateStatus(id, status, sel){
    const r = RAW.find(x => String(x.id)===String(id));
    if(r) r.status = status;
    sel.className = 'tnt-fe-status-sel '+statusClass(status);
    refreshStats();
    fetch(AJAX_URL, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=tnt_marine_fe_update&nonce=${NONCE}&id=${id}&status=${encodeURIComponent(status)}`
    });
}

/* ── notes ── */
function saveNotes(id){
    const cell  = document.querySelector(`.tnt-fe-notes-cell[data-id="${id}"]`);
    const ta    = cell.querySelector('.tnt-fe-notes-input');
    const notes = ta.value;
    const r     = RAW.find(x => String(x.id)===String(id));
    if(r) r.notes = notes;

    let display = cell.querySelector('.tnt-fe-notes-display');
    let addBtn  = cell.querySelector('.tnt-fe-notes-add');

    if(notes){
        if(!display){
            display = document.createElement('div');
            display.className = 'tnt-fe-notes-display';
            cell.insertBefore(display, ta);
        }
        display.textContent = notes;
        display.style.display = '';
        if(addBtn) addBtn.style.display = 'none';
    } else {
        if(display){ display.style.display='none'; }
        if(!addBtn){
            addBtn = document.createElement('span');
            addBtn.className = 'tnt-fe-notes-add';
            addBtn.textContent = 'Add note\u2026';
            cell.insertBefore(addBtn, ta);
        }
        addBtn.style.display = '';
    }

    ta.style.display = 'none';
    cell.querySelector('.tnt-fe-notes-actions').style.display = 'none';

    /* re-attach click listener to new/existing display element */
    const clickTarget = display || addBtn;
    if(clickTarget){
        clickTarget.onclick = function(){
            this.style.display = 'none';
            ta.style.display = 'block';
            ta.focus();
            cell.querySelector('.tnt-fe-notes-actions').style.display = 'flex';
        };
    }

    fetch(AJAX_URL, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=tnt_marine_fe_update&nonce=${NONCE}&id=${id}&notes=${encodeURIComponent(notes)}&status=${encodeURIComponent(r?r.status:'new')}`
    });
}

function cancelNotes(id){
    const cell = document.querySelector(`.tnt-fe-notes-cell[data-id="${id}"]`);
    const r    = RAW.find(x => String(x.id)===String(id));
    const ta   = cell.querySelector('.tnt-fe-notes-input');
    ta.value = r ? (r.notes||'') : '';
    ta.style.display = 'none';
    cell.querySelector('.tnt-fe-notes-actions').style.display = 'none';
    const display = cell.querySelector('.tnt-fe-notes-display');
    const addBtn  = cell.querySelector('.tnt-fe-notes-add');
    if(display) display.style.display = '';
    else if(addBtn) addBtn.style.display = '';
}

/* ── modal ── */
function openModal(id){
    const r = RAW.find(x => String(x.id)===String(id));
    if(!r) return;
    const body = document.getElementById('tnt-fe-modal-body');
    const row  = (label, val) => `<div class="tnt-fe-modal-row"><span class="tnt-fe-modal-label">${label}</span><span class="tnt-fe-modal-val">${val}</span></div>`;

    const listingTitleHtml = r.listing_url
        ? `<a href="${esc(r.listing_url)}" target="_blank" rel="noopener"><strong>${esc(r.listing_title)}</strong> <sup style="font-size:9px;opacity:.6">↗</sup></a>`
        : `<strong>${esc(r.listing_title)}</strong>`;

    body.innerHTML = `
        <div class="tnt-fe-modal-section">
            <div class="tnt-fe-modal-section-head">Contact Information</div>
            <div class="tnt-fe-modal-section-body">
                ${row('Name', '<strong>'+esc(r.name)+'</strong>')}
                ${row('Email', '<a href="mailto:'+esc(r.email)+'">'+esc(r.email)+'</a>')}
                ${row('Phone', r.phone ? '<a href="tel:'+esc(r.phone.replace(/\D/g,''))+'">'+esc(r.phone)+'</a>' : '<span class="tnt-fe-muted">Not provided</span>')}
                ${row('Date', new Date(r.created_at.replace(' ','T')).toLocaleString('en-US',{dateStyle:'long',timeStyle:'short'}))}
            </div>
        </div>

        <div class="tnt-fe-modal-section">
            <div class="tnt-fe-modal-section-head">Message</div>
            <div class="tnt-fe-modal-section-body">
                <div class="tnt-fe-message-box">${esc(r.message)||'<span class="tnt-fe-muted">No message provided.</span>'}</div>
            </div>
        </div>

        ${r.listing_title ? `
        <div class="tnt-fe-modal-section">
            <div class="tnt-fe-modal-section-head">Listing Inquired About</div>
            <div class="tnt-fe-modal-section-body">
                ${row('Listing', listingTitleHtml)}
                ${r.listing_make||r.listing_model ? row('Make / Model', esc((r.listing_make+' '+r.listing_model).trim())) : ''}
                ${r.listing_year    ? row('Year',     esc(r.listing_year)) : ''}
                ${r.listing_price   ? row('Price',    '<span class="tnt-fe-modal-price">'+fmtPrice(r.listing_price)+'</span>') : ''}
                ${r.listing_length  ? row('Length',   esc(r.listing_length)+' ft') : ''}
                ${r.listing_location? row('Location', esc(r.listing_location)) : ''}
            </div>
        </div>` : ''}

        <div class="tnt-fe-modal-section">
            <div class="tnt-fe-modal-section-head">Status &amp; Notes</div>
            <div class="tnt-fe-modal-section-body">
                <select id="tnt-modal-status" class="tnt-fe-modal-status-sel">
                    <option value="new"       ${r.status==='new'       ?'selected':''}>New</option>
                    <option value="contacted" ${r.status==='contacted' ?'selected':''}>Contacted</option>
                    <option value="closed"    ${r.status==='closed'    ?'selected':''}>Closed</option>
                </select>
                <textarea id="tnt-modal-notes" class="tnt-fe-modal-notes" placeholder="Add internal notes shared with the whole team&hellip;">${esc(r.notes)}</textarea>
                <button class="tnt-fe-modal-save-btn" onclick="TNTDash.saveModal(${r.id})">Save Changes</button>
                <div class="tnt-fe-modal-saved" id="tnt-modal-saved">&#x2713; Saved successfully</div>
            </div>
        </div>

        <div class="tnt-fe-quick-actions">
            <a href="mailto:${esc(r.email)}" class="tnt-fe-quick-link">
                <span class="ql-icon">&#x2709;</span>
                <span>Email ${esc(r.name.split(' ')[0])}</span>
            </a>
            ${r.phone ? `<a href="tel:${esc(r.phone.replace(/\D/g,''))}" class="tnt-fe-quick-link"><span class="ql-icon">&#x260E;</span><span>Call ${esc(r.name.split(' ')[0])}</span></a>` : ''}
            ${r.listing_url ? `<a href="${esc(r.listing_url)}" class="tnt-fe-quick-link" target="_blank" rel="noopener"><span class="ql-icon">&#x1F6A2;</span><span>View Listing</span></a>` : ''}
        </div>
    `;

    document.getElementById('tnt-fe-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal(){
    document.getElementById('tnt-fe-modal').style.display = 'none';
    document.body.style.overflow = '';
}

function saveModal(id){
    const status = document.getElementById('tnt-modal-status').value;
    const notes  = document.getElementById('tnt-modal-notes').value;
    const r      = RAW.find(x => String(x.id)===String(id));
    if(r){ r.status=status; r.notes=notes; }

    const tblSel = document.querySelector(`.tnt-fe-status-sel[data-id="${id}"]`);
    if(tblSel){ tblSel.value=status; tblSel.className='tnt-fe-status-sel '+statusClass(status); }

    const noteDisp = document.querySelector(`.tnt-fe-notes-cell[data-id="${id}"] .tnt-fe-notes-display`);
    if(noteDisp) noteDisp.textContent = notes;

    refreshStats();

    const savedMsg = document.getElementById('tnt-modal-saved');
    savedMsg.style.display = 'block';
    setTimeout(()=>{ savedMsg.style.display='none'; }, 3000);

    fetch(AJAX_URL, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=tnt_marine_fe_update&nonce=${NONCE}&id=${id}&status=${encodeURIComponent(status)}&notes=${encodeURIComponent(notes)}`
    });
}

/* ── CSV (exports currently filtered view) ── */
function exportCSV(){
    const headers = ['Date','Name','Email','Phone','Message','Listing','Make','Model','Year','Price','Length (ft)','Location','Status','Notes'];
    const rows = [headers, ...current.map(r => [
        r.created_at, r.name, r.email, r.phone, r.message,
        r.listing_title, r.listing_make, r.listing_model, r.listing_year,
        r.listing_price ? '$'+Number(r.listing_price).toLocaleString() : '',
        r.listing_length, r.listing_location, statusLabel(r.status), r.notes
    ])];
    const csv = rows.map(row => row.map(c => '"'+String(c||'').replace(/"/g,'""')+'"').join(',')).join('\n');
    const blob = new Blob([csv], {type:'text/csv'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a'); a.href=url; a.download='tnt-inquiries-'+new Date().toISOString().slice(0,10)+'.csv'; a.click();
    URL.revokeObjectURL(url);
}

/* ── init ── */
function init(){
    render();
    document.getElementById('tnt-stat-all').classList.add('active');

    /* set default sort indicator */
    const dateTh = document.querySelector('#tnt-fe-table th[data-col="created_at"]');
    if(dateTh) dateTh.classList.add('sort-desc');

    document.getElementById('tnt-fe-search').addEventListener('input', function(){
        state.search = this.value; render();
    });
    document.getElementById('tnt-fe-status-filter').addEventListener('change', function(){
        filterStatus(this.value);
    });
    document.querySelectorAll('#tnt-fe-table th.sortable').forEach(th => {
        th.addEventListener('click', () => sortBy(th.dataset.col));
    });
    document.getElementById('tnt-fe-modal').addEventListener('click', function(e){
        if(e.target === this) closeModal();
    });
    document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal(); });
}

window.TNTDash = { openModal, closeModal, saveModal, updateStatus, saveNotes, cancelNotes, filterStatus, exportCSV };
document.addEventListener('DOMContentLoaded', init);
})();
</script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'marine_inquiries', 'tnt_marine_inquiries_dashboard_shortcode' );
