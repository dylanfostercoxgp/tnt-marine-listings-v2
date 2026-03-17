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
    // SpreadsheetML – opens natively in Excel, no library needed
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

    // Header row
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

    $rows         = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A );
    $nonce        = wp_create_nonce( 'tnt_fe_nonce' );
    $export_nonce = wp_create_nonce( 'tnt_fe_export' );
    $ajax_url     = admin_url( 'admin-ajax.php' );
    $csv_url      = esc_url( add_query_arg( [ 'tnt_inq_export' => 'csv',   'tnt_inq_nonce' => $export_nonce ], home_url( '/' ) ) );
    $xls_url      = esc_url( add_query_arg( [ 'tnt_inq_export' => 'excel', 'tnt_inq_nonce' => $export_nonce ], home_url( '/' ) ) );

    $total     = count( $rows );
    $cnt_new   = count( array_filter( $rows, fn( $r ) => $r['status'] === 'new' ) );
    $cnt_cont  = count( array_filter( $rows, fn( $r ) => $r['status'] === 'contacted' ) );
    $cnt_close = count( array_filter( $rows, fn( $r ) => $r['status'] === 'closed' ) );

    // Encode data for JS — escape </script> sequences
    $json = str_replace( '</', '<\/', wp_json_encode( $rows, JSON_HEX_TAG ) );

    ob_start();
    ?>
<!– ── TNT Marine Inquiry Dashboard ──────────────────────────────────────── –>
<div id="tnt-fe-dash">

<style>
/* ─── Reset / Base ─────────────────────────────────────────────────── */
#tnt-fe-dash *,#tnt-fe-dash *::before,#tnt-fe-dash *::after{box-sizing:border-box;margin:0;padding:0;}
#tnt-fe-dash{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:14px;color:#1e293b;background:#f1f5f9;min-height:100vh;padding:0;}

/* ─── Header ───────────────────────────────────────────────────────── */
.tnt-fe-header{background:linear-gradient(135deg,#0f172a 0%,#1a3a5c 100%);padding:20px 28px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;}
.tnt-fe-header-left h1{color:#fff;font-size:20px;font-weight:700;letter-spacing:-.01em;}
.tnt-fe-header-left p{color:rgba(255,255,255,.5);font-size:12px;margin-top:3px;}
.tnt-fe-badge{display:inline-block;background:#cc2129;color:#fff;font-size:11px;font-weight:800;padding:2px 9px;border-radius:3px;letter-spacing:.06em;margin-right:8px;vertical-align:middle;}
.tnt-fe-exports{display:flex;gap:10px;flex-wrap:wrap;}
.tnt-fe-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .15s;}
.tnt-fe-btn-outline{background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.25);}
.tnt-fe-btn-outline:hover{background:rgba(255,255,255,.2);color:#fff;text-decoration:none;}
.tnt-fe-btn-red{background:#cc2129;color:#fff;}
.tnt-fe-btn-red:hover{background:#a81920;color:#fff;}

/* ─── Stats ────────────────────────────────────────────────────────── */
.tnt-fe-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:0;border-bottom:1px solid #e2e8f0;}
.tnt-fe-stat{background:#fff;padding:18px 24px;border-right:1px solid #e2e8f0;cursor:pointer;transition:background .15s;}
.tnt-fe-stat:last-child{border-right:none;}
.tnt-fe-stat:hover,.tnt-fe-stat.active{background:#f8fafc;}
.tnt-fe-stat.active{box-shadow:inset 0 -3px 0 var(--sc);}
.tnt-fe-stat-num{font-size:30px;font-weight:800;color:var(--sc);line-height:1;}
.tnt-fe-stat-label{font-size:12px;color:#64748b;margin-top:4px;font-weight:500;text-transform:uppercase;letter-spacing:.05em;}
@media(max-width:600px){.tnt-fe-stats{grid-template-columns:1fr 1fr;}.tnt-fe-stat{border-bottom:1px solid #e2e8f0;}}

/* ─── Toolbar ──────────────────────────────────────────────────────── */
.tnt-fe-toolbar{background:#fff;border-bottom:1px solid #e2e8f0;padding:14px 20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.tnt-fe-search-wrap{position:relative;flex:1;min-width:200px;}
.tnt-fe-search-wrap svg{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;}
#tnt-fe-search{width:100%;padding:8px 12px 8px 36px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;outline:none;transition:border-color .2s;}
#tnt-fe-search:focus{border-color:#1a2e4a;}
#tnt-fe-status-filter{padding:8px 14px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;background:#fff;cursor:pointer;outline:none;}
.tnt-fe-count{margin-left:auto;font-size:12px;color:#94a3b8;white-space:nowrap;}

/* ─── Table wrapper ────────────────────────────────────────────────── */
.tnt-fe-table-wrap{overflow-x:auto;background:#fff;}
#tnt-fe-table{width:100%;border-collapse:collapse;font-size:13px;}

/* ─── Table head ───────────────────────────────────────────────────── */
#tnt-fe-table thead tr{background:#0f172a;}
#tnt-fe-table th{padding:11px 14px;color:rgba(255,255,255,.7);font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;white-space:nowrap;user-select:none;}
#tnt-fe-table th.sortable{cursor:pointer;}
#tnt-fe-table th.sortable:hover{color:#fff;}
#tnt-fe-table th.sortable::after{content:'↕';margin-left:5px;opacity:.4;font-size:10px;}
#tnt-fe-table th.sort-asc::after{content:'↑';opacity:1;}
#tnt-fe-table th.sort-desc::after{content:'↓';opacity:1;}

/* ─── Table body ───────────────────────────────────────────────────── */
#tnt-fe-table tbody tr{border-bottom:1px solid #f1f5f9;transition:background .1s;}
#tnt-fe-table tbody tr:hover{background:#f8fafc;}
#tnt-fe-table tbody tr:last-child{border-bottom:none;}
#tnt-fe-table td{padding:11px 14px;vertical-align:middle;}
#tnt-fe-table td a{color:#1a2e4a;text-decoration:none;}
#tnt-fe-table td a:hover{text-decoration:underline;color:#cc2129;}
.tnt-fe-muted{color:#94a3b8;}
.tnt-fe-name{font-weight:600;}
.tnt-fe-price{font-weight:700;color:#cc2129;white-space:nowrap;}
.tnt-fe-date{font-size:12px;white-space:nowrap;}
.tnt-fe-date span{display:block;color:#94a3b8;margin-top:1px;}

/* ─── Status badge / select ────────────────────────────────────────── */
.tnt-fe-status-sel{padding:4px 8px;border-radius:20px;font-size:11px;font-weight:700;border:none;cursor:pointer;outline:none;appearance:none;-webkit-appearance:none;text-align:center;}
.tnt-fe-status-sel.s-new{background:#dbeafe;color:#1d4ed8;}
.tnt-fe-status-sel.s-contacted{background:#fef3c7;color:#b45309;}
.tnt-fe-status-sel.s-closed{background:#d1fae5;color:#065f46;}

/* ─── Notes cell ───────────────────────────────────────────────────── */
.tnt-fe-notes-display{max-width:180px;max-height:48px;overflow:hidden;cursor:pointer;line-height:1.4;color:#475569;font-size:12px;white-space:pre-wrap;word-break:break-word;}
.tnt-fe-notes-display:hover{color:#1a2e4a;}
.tnt-fe-notes-input{width:200px;min-height:64px;padding:6px 8px;border:1px solid #cbd5e1;border-radius:5px;font-size:12px;resize:vertical;font-family:inherit;outline:none;}
.tnt-fe-notes-input:focus{border-color:#1a2e4a;}
.tnt-fe-notes-actions{display:flex;gap:5px;margin-top:5px;}
.tnt-fe-notes-save{padding:4px 10px;background:#1a2e4a;color:#fff;border:none;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;}
.tnt-fe-notes-save:hover{background:#cc2129;}
.tnt-fe-notes-cancel{padding:4px 10px;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;border-radius:4px;font-size:11px;cursor:pointer;}
.tnt-fe-saving{opacity:.5;pointer-events:none;}

/* ─── View button ──────────────────────────────────────────────────── */
.tnt-fe-view-btn{padding:5px 12px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;font-size:12px;font-weight:600;color:#1a2e4a;cursor:pointer;white-space:nowrap;transition:all .15s;}
.tnt-fe-view-btn:hover{background:#1a2e4a;color:#fff;border-color:#1a2e4a;}

/* ─── Empty state ──────────────────────────────────────────────────── */
.tnt-fe-empty{text-align:center;padding:60px 20px;color:#94a3b8;}
.tnt-fe-empty-icon{font-size:40px;margin-bottom:12px;}
.tnt-fe-empty p{font-size:15px;font-weight:500;}

/* ─── Modal overlay ────────────────────────────────────────────────── */
#tnt-fe-modal{position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.55);display:none;align-items:flex-start;justify-content:flex-end;padding:0;}
.tnt-fe-modal-panel{background:#fff;width:100%;max-width:560px;height:100vh;overflow-y:auto;box-shadow:-8px 0 40px rgba(0,0,0,.18);display:flex;flex-direction:column;}
.tnt-fe-modal-header{background:#0f172a;padding:20px 24px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
.tnt-fe-modal-header h2{color:#fff;font-size:16px;font-weight:700;}
.tnt-fe-modal-close{background:rgba(255,255,255,.15);border:none;color:#fff;width:32px;height:32px;border-radius:6px;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;transition:background .15s;}
.tnt-fe-modal-close:hover{background:rgba(255,255,255,.25);}
.tnt-fe-modal-body{padding:24px;flex:1;display:flex;flex-direction:column;gap:20px;}
.tnt-fe-modal-section{border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;}
.tnt-fe-modal-section-head{background:#f8fafc;padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#64748b;border-bottom:1px solid #e2e8f0;}
.tnt-fe-modal-section-body{padding:16px;}
.tnt-fe-modal-row{display:flex;padding:7px 0;border-bottom:1px solid #f1f5f9;align-items:baseline;gap:12px;}
.tnt-fe-modal-row:last-child{border-bottom:none;}
.tnt-fe-modal-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;width:90px;flex-shrink:0;}
.tnt-fe-modal-val{font-size:13px;color:#1e293b;flex:1;word-break:break-word;}
.tnt-fe-modal-price{font-size:20px;font-weight:800;color:#cc2129;}
.tnt-fe-message-box{background:#f8fafc;border-left:3px solid #cc2129;padding:12px 16px;border-radius:0 6px 6px 0;font-size:13px;line-height:1.7;color:#374151;white-space:pre-wrap;word-break:break-word;}
.tnt-fe-modal-status-sel{width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;outline:none;margin-bottom:14px;}
.tnt-fe-modal-notes{width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;min-height:100px;resize:vertical;font-family:inherit;outline:none;margin-bottom:10px;}
.tnt-fe-modal-notes:focus,.tnt-fe-modal-status-sel:focus{border-color:#1a2e4a;}
.tnt-fe-modal-save-btn{width:100%;padding:11px;background:#cc2129;color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:700;cursor:pointer;transition:background .15s;}
.tnt-fe-modal-save-btn:hover{background:#a81920;}
.tnt-fe-modal-saved{text-align:center;font-size:12px;color:#10b981;font-weight:600;margin-top:6px;display:none;}
.tnt-fe-quick-links{display:flex;flex-direction:column;gap:8px;margin-top:16px;}
.tnt-fe-quick-link{display:flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid #e2e8f0;border-radius:6px;text-decoration:none;color:#1a2e4a;font-size:13px;font-weight:600;transition:all .15s;}
.tnt-fe-quick-link:hover{background:#1a2e4a;color:#fff;text-decoration:none;border-color:#1a2e4a;}
@media(max-width:600px){.tnt-fe-modal-panel{max-width:100%;height:100%;}.tnt-fe-modal-row{flex-direction:column;gap:2px;}.tnt-fe-modal-label{width:auto;}}
</style>

<!-- HEADER -->
<div class="tnt-fe-header">
    <div class="tnt-fe-header-left">
        <h1><span class="tnt-fe-badge">TNT MARINE</span> Inquiry Dashboard</h1>
        <p>Sales Team View &mdash; <span id="tnt-fe-header-count"><?php echo intval( $total ); ?></span> total inquiries</p>
    </div>
    <div class="tnt-fe-exports">
        <a href="<?php echo $csv_url; ?>" class="tnt-fe-btn tnt-fe-btn-outline">⬇ Export CSV</a>
        <a href="<?php echo $xls_url; ?>" class="tnt-fe-btn tnt-fe-btn-red">⬇ Export Excel</a>
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

<!-- TOOLBAR -->
<div class="tnt-fe-toolbar">
    <div class="tnt-fe-search-wrap">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="search" id="tnt-fe-search" placeholder="Search name, email, phone, listing, notes…">
    </div>
    <select id="tnt-fe-status-filter">
        <option value="">All Statuses</option>
        <option value="new">New</option>
        <option value="contacted">Contacted</option>
        <option value="closed">Closed</option>
    </select>
    <span class="tnt-fe-count" id="tnt-fe-count"></span>
</div>

<!-- TABLE -->
<div class="tnt-fe-table-wrap">
    <table id="tnt-fe-table">
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
            <tr><td colspan="10" style="text-align:center;padding:40px;color:#94a3b8;">Loading…</td></tr>
        </tbody>
    </table>
</div>

<!-- DETAIL MODAL -->
<div id="tnt-fe-modal">
    <div class="tnt-fe-modal-panel">
        <div class="tnt-fe-modal-header">
            <h2>Inquiry Detail</h2>
            <button class="tnt-fe-modal-close" onclick="TNTDash.closeModal()">✕</button>
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

let state = { search: '', status: '', sort: { col: 'created_at', dir: 'desc' } };
let current = [];

/* ── helpers ── */
function esc(s){ const d=document.createElement('div');d.textContent=String(s||'');return d.innerHTML; }
function fmtDate(d){
    if(!d)return'—';
    const dt=new Date(d.replace(' ','T'));
    return dt.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})+'<span>'+dt.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'})+'</span>';
}
function fmtPrice(p){ return p?'$'+Number(p).toLocaleString():'—'; }
function truncate(s,n){ s=String(s||''); return s.length>n?s.slice(0,n)+'…':s; }
function statusClass(s){ return{new:'s-new',contacted:'s-contacted',closed:'s-closed'}[s]||'s-new'; }
function statusLabel(s){ return{new:'New',contacted:'Contacted',closed:'Closed'}[s]||s; }

/* ── stats ── */
function refreshStats(){
    const all=RAW.length;
    const byS={new:0,contacted:0,closed:0};
    RAW.forEach(r=>{ if(byS[r.status]!==undefined) byS[r.status]++; });
    document.getElementById('tnt-cnt-all').textContent=all;
    document.getElementById('tnt-cnt-new').textContent=byS.new;
    document.getElementById('tnt-cnt-contacted').textContent=byS.contacted;
    document.getElementById('tnt-cnt-closed').textContent=byS.closed;
    document.getElementById('tnt-fe-header-count').textContent=all;
}

/* ── filter + sort ── */
function applyFilters(){
    const q=state.search.toLowerCase();
    current=RAW.filter(r=>{
        if(state.status && r.status!==state.status) return false;
        if(q){
            const fields=[r.name,r.email,r.phone,r.listing_title,r.listing_make,r.listing_model,r.notes,r.message,r.listing_year,r.listing_location];
            if(!fields.some(f=>(f||'').toLowerCase().includes(q))) return false;
        }
        return true;
    });
    const col=state.sort.col, dir=state.sort.dir;
    current.sort((a,b)=>{
        let va=a[col]||'', vb=b[col]||'';
        if(['listing_price','listing_year','listing_length'].includes(col)){ va=parseFloat(va)||0; vb=parseFloat(vb)||0; }
        const cmp=va<vb?-1:va>vb?1:0;
        return dir==='asc'?cmp:-cmp;
    });
}

/* ── render table ── */
function render(){
    applyFilters();
    refreshStats();
    const tbody=document.getElementById('tnt-fe-tbody');
    const countEl=document.getElementById('tnt-fe-count');
    countEl.textContent=current.length+' result'+(current.length!==1?'s':'');

    if(!current.length){
        tbody.innerHTML='<tr><td colspan="10"><div class="tnt-fe-empty"><div class="tnt-fe-empty-icon">📭</div><p>No inquiries match your filters.</p></div></td></tr>';
        return;
    }

    tbody.innerHTML=current.map(r=>`
        <tr data-id="${r.id}">
            <td class="tnt-fe-date">${fmtDate(r.created_at)}</td>
            <td class="tnt-fe-name">${esc(r.name)}</td>
            <td>
                <a href="mailto:${esc(r.email)}">${esc(r.email)}</a><br>
                ${r.phone?`<a href="tel:${esc(r.phone.replace(/\D/g,''))}">${esc(r.phone)}</a>`:'<span class="tnt-fe-muted">—</span>'}
            </td>
            <td>${esc(r.listing_title)||'<span class="tnt-fe-muted">—</span>'}</td>
            <td>${esc(r.listing_year)||'<span class="tnt-fe-muted">—</span>'}</td>
            <td>${esc((r.listing_make+' '+r.listing_model).trim())||'<span class="tnt-fe-muted">—</span>'}</td>
            <td class="tnt-fe-price">${fmtPrice(r.listing_price)}</td>
            <td>
                <select class="tnt-fe-status-sel ${statusClass(r.status)}" data-id="${r.id}" onchange="TNTDash.updateStatus(${r.id},this.value,this)">
                    <option value="new"       ${r.status==='new'?'selected':''}>New</option>
                    <option value="contacted" ${r.status==='contacted'?'selected':''}>Contacted</option>
                    <option value="closed"    ${r.status==='closed'?'selected':''}>Closed</option>
                </select>
            </td>
            <td>
                <div class="tnt-fe-notes-cell" data-id="${r.id}">
                    <div class="tnt-fe-notes-display" title="Click to edit">${esc(r.notes)||'<span class="tnt-fe-muted">Add note…</span>'}</div>
                    <textarea class="tnt-fe-notes-input" style="display:none">${esc(r.notes)}</textarea>
                    <div class="tnt-fe-notes-actions" style="display:none">
                        <button class="tnt-fe-notes-save" onclick="TNTDash.saveNotes(${r.id})">Save</button>
                        <button class="tnt-fe-notes-cancel" onclick="TNTDash.cancelNotes(${r.id})">Cancel</button>
                    </div>
                </div>
            </td>
            <td><button class="tnt-fe-view-btn" onclick="TNTDash.openModal(${r.id})">View →</button></td>
        </tr>
    `).join('');

    /* attach notes click */
    document.querySelectorAll('.tnt-fe-notes-display').forEach(el=>{
        el.addEventListener('click',function(){
            const cell=this.closest('.tnt-fe-notes-cell');
            this.style.display='none';
            cell.querySelector('.tnt-fe-notes-input').style.display='block';
            cell.querySelector('.tnt-fe-notes-input').focus();
            cell.querySelector('.tnt-fe-notes-actions').style.display='flex';
        });
    });
}

/* ── sort ── */
window.TNTDash = window.TNTDash||{};
function sortBy(col){
    state.sort.dir = state.sort.col===col ? (state.sort.dir==='asc'?'desc':'asc') : 'asc';
    state.sort.col = col;
    document.querySelectorAll('#tnt-fe-table th.sortable').forEach(th=>{
        th.classList.remove('sort-asc','sort-desc');
        if(th.dataset.col===col) th.classList.add('sort-'+state.sort.dir);
    });
    render();
}

/* ── filter by status (stats click) ── */
function filterStatus(s){
    state.status=s;
    document.getElementById('tnt-fe-status-filter').value=s;
    document.querySelectorAll('.tnt-fe-stat').forEach(el=>el.classList.remove('active'));
    const active=document.getElementById('tnt-stat-'+(s||'all'));
    if(active) active.classList.add('active');
    render();
}

/* ── update status ── */
function updateStatus(id, status, sel){
    const r=RAW.find(x=>String(x.id)===String(id));
    if(r) r.status=status;
    sel.className='tnt-fe-status-sel '+statusClass(status);
    refreshStats();
    fetch(AJAX_URL,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=tnt_marine_fe_update&nonce=${NONCE}&id=${id}&status=${encodeURIComponent(status)}`});
}

/* ── notes ── */
function saveNotes(id){
    const cell=document.querySelector(`.tnt-fe-notes-cell[data-id="${id}"]`);
    const ta=cell.querySelector('.tnt-fe-notes-input');
    const notes=ta.value;
    const r=RAW.find(x=>String(x.id)===String(id));
    if(r) r.notes=notes;

    cell.classList.add('tnt-fe-saving');
    const display=cell.querySelector('.tnt-fe-notes-display');
    display.innerHTML=notes?esc(notes):'<span class="tnt-fe-muted">Add note…</span>';
    display.style.display='block';
    ta.style.display='none';
    cell.querySelector('.tnt-fe-notes-actions').style.display='none';
    cell.classList.remove('tnt-fe-saving');

    fetch(AJAX_URL,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=tnt_marine_fe_update&nonce=${NONCE}&id=${id}&notes=${encodeURIComponent(notes)}&status=${encodeURIComponent(r?r.status:'new')}`});
}

function cancelNotes(id){
    const cell=document.querySelector(`.tnt-fe-notes-cell[data-id="${id}"]`);
    const r=RAW.find(x=>String(x.id)===String(id));
    cell.querySelector('.tnt-fe-notes-input').value=r?(r.notes||''):'';
    cell.querySelector('.tnt-fe-notes-display').style.display='block';
    cell.querySelector('.tnt-fe-notes-input').style.display='none';
    cell.querySelector('.tnt-fe-notes-actions').style.display='none';
}

/* ── modal ── */
function openModal(id){
    const r=RAW.find(x=>String(x.id)===String(id));
    if(!r) return;
    const body=document.getElementById('tnt-fe-modal-body');

    const row=(label,val)=>`<div class="tnt-fe-modal-row"><span class="tnt-fe-modal-label">${label}</span><span class="tnt-fe-modal-val">${val}</span></div>`;

    body.innerHTML=`
        <!-- Contact -->
        <div class="tnt-fe-modal-section">
            <div class="tnt-fe-modal-section-head">Contact Information</div>
            <div class="tnt-fe-modal-section-body">
                ${row('Name','<strong>'+esc(r.name)+'</strong>')}
                ${row('Email','<a href="mailto:'+esc(r.email)+'">'+esc(r.email)+'</a>')}
                ${row('Phone',r.phone?'<a href="tel:'+esc(r.phone.replace(/\D/g,''))+'">'+esc(r.phone)+'</a>':'<span class="tnt-fe-muted">Not provided</span>')}
                ${row('Date',new Date(r.created_at.replace(' ','T')).toLocaleString('en-US',{dateStyle:'long',timeStyle:'short'}))}
            </div>
        </div>

        <!-- Message -->
        <div class="tnt-fe-modal-section">
            <div class="tnt-fe-modal-section-head">Message</div>
            <div class="tnt-fe-modal-section-body">
                <div class="tnt-fe-message-box">${esc(r.message)||'<span class="tnt-fe-muted">No message</span>'}</div>
            </div>
        </div>

        <!-- Listing -->
        ${r.listing_title?`
        <div class="tnt-fe-modal-section">
            <div class="tnt-fe-modal-section-head">Listing Inquired About</div>
            <div class="tnt-fe-modal-section-body">
                ${row('Listing','<strong>'+esc(r.listing_title)+'</strong>')}
                ${r.listing_make||r.listing_model?row('Make / Model',esc((r.listing_make+' '+r.listing_model).trim())):''}
                ${r.listing_year?row('Year',esc(r.listing_year)):''}
                ${r.listing_price?row('Price','<span class="tnt-fe-modal-price">'+fmtPrice(r.listing_price)+'</span>'):''}
                ${r.listing_length?row('Length',esc(r.listing_length)+'ft'):''}
                ${r.listing_location?row('Location',esc(r.listing_location)):''}
            </div>
        </div>`:''}

        <!-- Status & Notes -->
        <div class="tnt-fe-modal-section">
            <div class="tnt-fe-modal-section-head">Status &amp; Notes</div>
            <div class="tnt-fe-modal-section-body">
                <select id="tnt-modal-status" class="tnt-fe-modal-status-sel">
                    <option value="new"       ${r.status==='new'?'selected':''}>New</option>
                    <option value="contacted" ${r.status==='contacted'?'selected':''}>Contacted</option>
                    <option value="closed"    ${r.status==='closed'?'selected':''}>Closed</option>
                </select>
                <textarea id="tnt-modal-notes" class="tnt-fe-modal-notes" placeholder="Add internal notes here…">${esc(r.notes)}</textarea>
                <button class="tnt-fe-modal-save-btn" onclick="TNTDash.saveModal(${r.id})">Save Changes</button>
                <div class="tnt-fe-modal-saved" id="tnt-modal-saved">✓ Saved</div>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="tnt-fe-quick-links">
            <a href="mailto:${esc(r.email)}" class="tnt-fe-quick-link">✉ Email ${esc(r.name.split(' ')[0])}</a>
            ${r.phone?'<a href="tel:'+esc(r.phone.replace(/\D/g,''))+'" class="tnt-fe-quick-link">📞 Call '+esc(r.name.split(' ')[0])+'</a>':''}
        </div>
    `;

    document.getElementById('tnt-fe-modal').style.display='flex';
    document.body.style.overflow='hidden';
}

function closeModal(){
    document.getElementById('tnt-fe-modal').style.display='none';
    document.body.style.overflow='';
}

function saveModal(id){
    const status=document.getElementById('tnt-modal-status').value;
    const notes=document.getElementById('tnt-modal-notes').value;
    const r=RAW.find(x=>String(x.id)===String(id));
    if(r){ r.status=status; r.notes=notes; }

    /* Update status dropdown in table if visible */
    const tblSel=document.querySelector(`.tnt-fe-status-sel[data-id="${id}"]`);
    if(tblSel){ tblSel.value=status; tblSel.className='tnt-fe-status-sel '+statusClass(status); }

    /* Update notes display in table */
    const noteDisp=document.querySelector(`.tnt-fe-notes-cell[data-id="${id}"] .tnt-fe-notes-display`);
    if(noteDisp) noteDisp.innerHTML=notes?esc(notes):'<span class="tnt-fe-muted">Add note…</span>';

    refreshStats();

    const savedMsg=document.getElementById('tnt-modal-saved');
    savedMsg.style.display='block';
    setTimeout(()=>savedMsg.style.display='none',3000);

    fetch(AJAX_URL,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=tnt_marine_fe_update&nonce=${NONCE}&id=${id}&status=${encodeURIComponent(status)}&notes=${encodeURIComponent(notes)}`});
}

/* ── CSV export (client-side, exports current filtered view) ── */
function exportCSV(){
    const headers=['Date','Name','Email','Phone','Message','Listing','Make','Model','Year','Price','Length (ft)','Location','Status','Notes'];
    const csvData=[headers,...current.map(r=>[
        r.created_at,r.name,r.email,r.phone,r.message,
        r.listing_title,r.listing_make,r.listing_model,r.listing_year,
        r.listing_price?'$'+Number(r.listing_price).toLocaleString():'',
        r.listing_length,r.listing_location,statusLabel(r.status),r.notes
    ])];
    const csv=csvData.map(row=>row.map(c=>'"'+String(c||'').replace(/"/g,'""')+'"').join(',')).join('\n');
    dl(csv,'tnt-inquiries-'+new Date().toISOString().slice(0,10)+'.csv','text/csv');
}

function dl(content,filename,type){
    const blob=new Blob([content],{type});
    const url=URL.createObjectURL(blob);
    const a=document.createElement('a');a.href=url;a.download=filename;a.click();
    URL.revokeObjectURL(url);
}

/* ── init ── */
function init(){
    render();
    document.getElementById('tnt-stat-all').classList.add('active');

    document.getElementById('tnt-fe-search').addEventListener('input',function(){
        state.search=this.value; render();
    });
    document.getElementById('tnt-fe-status-filter').addEventListener('change',function(){
        filterStatus(this.value);
    });
    document.querySelectorAll('#tnt-fe-table th.sortable').forEach(th=>{
        th.addEventListener('click',()=>sortBy(th.dataset.col));
    });
    /* sort by date desc by default */
    const dateTh=document.querySelector('#tnt-fe-table th[data-col="created_at"]');
    if(dateTh) dateTh.classList.add('sort-desc');

    /* close modal on backdrop click */
    document.getElementById('tnt-fe-modal').addEventListener('click',function(e){
        if(e.target===this) closeModal();
    });
    /* close modal on Escape */
    document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeModal(); });
}

window.TNTDash={openModal,closeModal,saveModal,updateStatus,saveNotes,cancelNotes,filterStatus,exportCSV};
document.addEventListener('DOMContentLoaded',init);
})();
</script>

    <?php
    return ob_get_clean();
}
add_shortcode( 'marine_inquiries', 'tnt_marine_inquiries_dashboard_shortcode' );
