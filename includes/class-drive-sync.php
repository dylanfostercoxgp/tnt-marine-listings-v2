<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * TNT Marine Listings – Google Drive Auto-Sync
 *
 * Architecture:
 *   • One master Google Sheet lives directly inside the parent Drive folder.
 *     Row 1 = optional section-header band, Row 2 = column headers, Row 3+ = one listing per row.
 *   • Image subfolders sit alongside the sheet, each named to match the
 *     "Title" value in its corresponding sheet row (case-insensitive).
 *
 * Parent folder layout:
 *   📁 TNT Marine Listings          ← share this with the service account
 *      📄 Listings (Google Sheet)   ← master data sheet
 *      📁 2023 Sea Ray 390          ← images for that listing
 *      📁 2024 Grady-White 336      ← images for that listing
 *
 * Authentication uses a Google Cloud service-account JSON key.
 * All API calls use WordPress's built-in HTTP API (no Composer required).
 */
class TNT_Drive_Sync {

	/** @var array Saved drive settings */
	private $settings;

	/**
	 * Maps lowercase Google Sheet column headers → WordPress meta keys.
	 * Headers with trailing " *" (required markers) are stripped before lookup.
	 */
	private const COL_MAP = [
		// ── Post fields
		'title'               => 'post_title',
		'status'              => 'post_status',
		// ── Overview
		'year'                => '_tnt_year',
		'price'               => '_tnt_price',
		'length'              => '_tnt_length',
		'location'            => '_tnt_location',
		'class'               => '_tnt_class',
		'type'                => '_tnt_class',
		'capacity'            => '_tnt_capacity',
		'model'               => '_tnt_model',
		'make'                => '_tnt_make',
		'hours'               => '_tnt_hours',
		'engine hours'        => '_tnt_hours',
		// ── Specifications
		'length overall'      => '_tnt_length_overall',
		'overall length'      => '_tnt_length_overall',
		'beam'                => '_tnt_beam',
		'dry weight'          => '_tnt_dry_weight',
		'fuel tanks'          => '_tnt_fuel_tanks',
		'fuel capacity'       => '_tnt_fuel_tanks',
		// ── Propulsion – generic shorthands (map to engine 1)
		'engine count'        => '_tnt_engine_count',
		'number of engines'   => '_tnt_engine_count',
		'engines'             => '_tnt_engine_count',
		'engine make'         => '_tnt_engine_make_1',
		'engine model'        => '_tnt_engine_model_1',
		'engine power'        => '_tnt_engine_power_1',
		'horsepower'          => '_tnt_engine_power_1',
		'hp'                  => '_tnt_engine_power_1',
		'fuel type'           => '_tnt_engine_fuel_1',
		// ── Propulsion – numbered engines 1–5
		'engine 1 make'       => '_tnt_engine_make_1',
		'engine 1 model'      => '_tnt_engine_model_1',
		'engine 1 power'      => '_tnt_engine_power_1',
		'engine 1 hours'      => '_tnt_engine_hours_1',
		'engine 1 fuel'       => '_tnt_engine_fuel_1',
		'engine 2 make'       => '_tnt_engine_make_2',
		'engine 2 model'      => '_tnt_engine_model_2',
		'engine 2 power'      => '_tnt_engine_power_2',
		'engine 2 hours'      => '_tnt_engine_hours_2',
		'engine 2 fuel'       => '_tnt_engine_fuel_2',
		'engine 3 make'       => '_tnt_engine_make_3',
		'engine 3 model'      => '_tnt_engine_model_3',
		'engine 3 power'      => '_tnt_engine_power_3',
		'engine 3 hours'      => '_tnt_engine_hours_3',
		'engine 3 fuel'       => '_tnt_engine_fuel_3',
		'engine 4 make'       => '_tnt_engine_make_4',
		'engine 4 model'      => '_tnt_engine_model_4',
		'engine 4 power'      => '_tnt_engine_power_4',
		'engine 4 hours'      => '_tnt_engine_hours_4',
		'engine 4 fuel'       => '_tnt_engine_fuel_4',
		'engine 5 make'       => '_tnt_engine_make_5',
		'engine 5 model'      => '_tnt_engine_model_5',
		'engine 5 power'      => '_tnt_engine_power_5',
		'engine 5 hours'      => '_tnt_engine_hours_5',
		'engine 5 fuel'       => '_tnt_engine_fuel_5',
		// ── Features & Description
		'description'         => '_tnt_description',
		'key features'        => '_tnt_power_features',
		'features'            => '_tnt_power_features',
		'notes'               => '_tnt_bonus',
		'disclaimers'         => '_tnt_bonus',
		'notes/disclaimers'   => '_tnt_bonus',
		// ── Listing status
		'sold'                => '_tnt_sold',
	];

	public function __construct() {
		$this->settings = get_option( 'tnt_marine_drive_settings', [] );

		add_filter( 'cron_schedules',                [ $this, 'add_cron_intervals' ] );
		add_action( 'init',                          [ $this, 'schedule_sync_cron' ] );
		add_action( 'tnt_marine_drive_sync',         [ $this, 'run_sync' ] );
		add_action( 'wp_ajax_tnt_drive_manual_sync', [ $this, 'ajax_manual_sync' ] );
	}

	/* =========================================================== CRON == */

	public function add_cron_intervals( $schedules ) {
		$schedules['every_2_hours'] = [ 'interval' => 7200,  'display' => 'Every 2 Hours' ];
		$schedules['every_3_hours'] = [ 'interval' => 10800, 'display' => 'Every 3 Hours' ];
		$schedules['every_4_hours'] = [ 'interval' => 14400, 'display' => 'Every 4 Hours' ];
		$schedules['every_6_hours'] = [ 'interval' => 21600, 'display' => 'Every 6 Hours' ];
		return $schedules;
	}

	public function schedule_sync_cron() {
		if ( ! $this->is_configured() ) return;
		if ( ! wp_next_scheduled( 'tnt_marine_drive_sync' ) ) {
			$interval = $this->settings['sync_interval'] ?? 'hourly';
			wp_schedule_event( time() + 60, $interval, 'tnt_marine_drive_sync' );
		}
	}

	public static function clear_cron() {
		$ts = wp_next_scheduled( 'tnt_marine_drive_sync' );
		if ( $ts ) wp_unschedule_event( $ts, 'tnt_marine_drive_sync' );
	}

	private function is_configured() {
		return ! empty( $this->settings['service_account_json'] )
			&& ! empty( $this->settings['drive_folder_id'] );
	}

	/* ======================================================= JWT AUTH == */

	/**
	 * Exchange a service-account JSON key for a short-lived Bearer token.
	 * Cached in a transient for 58 minutes.
	 */
	private function get_access_token() {
		$cached = get_transient( 'tnt_drive_access_token' );
		if ( $cached ) return $cached;

		// Decode the stored JSON. Do NOT run stripslashes() on it first —
		// that would strip backslashes from the \n sequences in the PEM key.
		$json_stored = $this->settings['service_account_json'] ?? '';
		$creds       = json_decode( $json_stored, true );

		// Fallback: if decode failed, the value may have been double-slashed on
		// save by an older version — try again after removing one level of slashes.
		if ( empty( $creds ) ) {
			$creds = json_decode( wp_unslash( $json_stored ), true );
		}

		if ( empty( $creds['private_key'] ) || empty( $creds['client_email'] ) ) {
			$this->log( 'ERROR: Service account JSON is missing private_key or client_email. Re-save the Drive settings and try again.' );
			return false;
		}

		// ── Normalise the PEM private key ───────────────────────────────────
		// json_decode() converts JSON \n escapes → real newlines (0x0A).
		// We also guard against every other encoding WordPress might introduce.
		$private_key = $creds['private_key'];

		// 1. Strip UTF-8 BOM if present (some editors add it to downloaded JSON).
		$private_key = ltrim( $private_key, "\xEF\xBB\xBF" );

		// 2. Normalise Windows-style line endings → Unix.
		$private_key = str_replace( "\r\n", "\n", $private_key );
		$private_key = str_replace( "\r",   "\n", $private_key );

		// 3. If no real newlines survived, the JSON \n escapes were stored as
		//    literal two-char sequences (backslash + n).  Convert them now.
		if ( strpos( $private_key, "\n" ) === false ) {
			$private_key = str_replace( '\n', "\n", $private_key );
		}

		// 4. Log key diagnostics so we can see the exact bytes (hex) if it fails.
		$first_32_hex = bin2hex( substr( $private_key, 0, 32 ) );
		$newline_count = substr_count( $private_key, "\n" );
		$key_len       = strlen( $private_key );

		$now     = time();
		$header  = $this->b64u( json_encode( [ 'alg' => 'RS256', 'typ' => 'JWT' ] ) );
		$payload = $this->b64u( json_encode( [
			'iss'   => $creds['client_email'],
			'scope' => 'https://www.googleapis.com/auth/drive.readonly https://www.googleapis.com/auth/spreadsheets.readonly',
			'aud'   => 'https://oauth2.googleapis.com/token',
			'iat'   => $now,
			'exp'   => $now + 3600,
		] ) );

		$to_sign = $header . '.' . $payload;
		$key     = openssl_pkey_get_private( $private_key );
		if ( ! $key ) {
			$ssl_err = openssl_error_string() ?: 'no detail';
			$this->log( "ERROR: OpenSSL rejected private key. Error: {$ssl_err} | Key length: {$key_len} bytes | Newlines in key: {$newline_count} | First 32 bytes (hex): {$first_32_hex}" );
			return false;
		}
		openssl_sign( $to_sign, $sig, $key, OPENSSL_ALGO_SHA256 );

		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
			'body'    => [
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $to_sign . '.' . $this->b64u( $sig ),
			],
			'timeout' => 20,
		] );

		if ( is_wp_error( $response ) ) {
			$this->log( 'ERROR: Token request failed – ' . $response->get_error_message() );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$this->log( 'ERROR: No access_token in response – ' . wp_remote_retrieve_body( $response ) );
			return false;
		}

		set_transient( 'tnt_drive_access_token', $body['access_token'], 3480 );
		return $body['access_token'];
	}

	private function b64u( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/* ===================================================== API HELPERS == */

	private function drive_get( $endpoint, $params = [] ) {
		$token = $this->get_access_token();
		if ( ! $token ) return false;

		$url = 'https://www.googleapis.com/drive/v3/' . ltrim( $endpoint, '/' );
		if ( $params ) $url .= '?' . http_build_query( $params );

		$response = wp_remote_get( $url, [
			'headers' => [ 'Authorization' => 'Bearer ' . $token ],
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			$this->log( 'ERROR: Drive API – ' . $response->get_error_message() );
			return false;
		}
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/** Download raw binary content of a Drive file. */
	private function drive_download( $file_id ) {
		$token = $this->get_access_token();
		if ( ! $token ) return false;

		$response = wp_remote_get(
			"https://www.googleapis.com/drive/v3/files/{$file_id}?alt=media",
			[
				'headers' => [ 'Authorization' => 'Bearer ' . $token ],
				'timeout' => 60,
			]
		);

		if ( is_wp_error( $response ) ) return false;
		if ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return false;
		return wp_remote_retrieve_body( $response );
	}

	/** Read all values from a Google Sheet. */
	private function sheets_read( $spreadsheet_id ) {
		$token = $this->get_access_token();
		if ( ! $token ) return false;

		$response = wp_remote_get(
			"https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/A1:ZZ1000",
			[
				'headers' => [ 'Authorization' => 'Bearer ' . $token ],
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) return false;
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['values'] ?? [];
	}

	/* ======================================================= SYNC CORE == */

	/**
	 * Main sync entry point.
	 *
	 * 1. Finds the master Google Sheet in the parent Drive folder.
	 * 2. Reads all listing rows (auto-detects which row contains the headers).
	 * 3. Lists image subfolders (matched to listings by Title).
	 * 4. Creates / updates one marine_listing post per data row.
	 * 5. Uploads any new images from the matching subfolder.
	 * 6. Drafts listings whose row has been removed from the sheet.
	 */
	public function run_sync() {
		if ( ! $this->is_configured() ) {
			$this->log( 'Sync skipped: Google Drive not configured.' );
			return;
		}

		$this->log( '── Drive sync started ──' );
		$folder_id = sanitize_text_field( $this->settings['drive_folder_id'] );

		// ── 1. Find master sheet ─────────────────────────────────────────────
		$sheet = $this->find_master_sheet( $folder_id );
		if ( ! $sheet ) {
			$this->log( 'ERROR: No Google Sheet found in the parent folder. '
				. 'Make sure your master listing sheet is in the root of the shared folder.' );
			update_option( 'tnt_marine_drive_last_sync', current_time( 'mysql' ) );
			return;
		}
		$this->log( "Master sheet: \"{$sheet['name']}\"" );

		// ── 2. Read sheet rows ───────────────────────────────────────────────
		$rows = $this->sheets_read( $sheet['id'] );
		if ( empty( $rows ) ) {
			$this->log( 'ERROR: Sheet returned no data.' );
			update_option( 'tnt_marine_drive_last_sync', current_time( 'mysql' ) );
			return;
		}

		// Auto-detect which row contains the column headers (looks for "Title")
		$header_idx = $this->find_header_row( $rows );
		$headers    = $rows[ $header_idx ];
		$data_start = $header_idx + 1;

		if ( $data_start >= count( $rows ) ) {
			$this->log( 'Sheet has headers but no listing rows.' );
			update_option( 'tnt_marine_drive_last_sync', current_time( 'mysql' ) );
			return;
		}

		// ── 3. List image subfolders ─────────────────────────────────────────
		$subfolders = $this->list_subfolders( $folder_id );
		$this->log( count( $subfolders ) . ' image subfolder(s) found.' );

		// ── 4 & 5. Process each data row ─────────────────────────────────────
		$active_keys = [];
		$processed   = 0;

		for ( $i = $data_start; $i < count( $rows ); $i++ ) {
			$row = $rows[ $i ];

			// Skip empty rows
			if ( empty( array_filter( array_map( 'strval', $row ) ) ) ) continue;

			$data = $this->parse_row( $headers, $row );
			if ( ! $data || empty( $data['post_title'] ) ) continue;

			$key           = strtolower( trim( $data['post_title'] ) );
			$active_keys[] = $key;

			$post_id = $this->create_or_update_post( $key, $data );
			if ( $post_id ) {
				$sheet_row = $i + 1; // 1-based for human-readable log
				$this->log( "  Row {$sheet_row}: ID {$post_id} → \"{$data['post_title']}\"" );
				$this->sync_images_from_subfolders( $post_id, $data['post_title'], $subfolders );
				$processed++;
			}
		}

		// ── 6. Unpublish removed listings ────────────────────────────────────
		$this->unpublish_removed_listings( $active_keys );

		update_option( 'tnt_marine_drive_last_sync', current_time( 'mysql' ) );
		$this->log( "── Sync complete. {$processed} listing(s) processed. ──" );
	}

	/* ================================================= DRIVE HELPERS == */

	/** Find the one Google Sheet that lives directly in the parent folder. */
	private function find_master_sheet( $folder_id ) {
		$result = $this->drive_get( 'files', [
			'q'        => "'{$folder_id}' in parents"
				. " and mimeType='application/vnd.google-apps.spreadsheet'"
				. " and trashed=false",
			'fields'   => 'files(id,name)',
			'pageSize' => 5,
		] );
		return $result['files'][0] ?? null;
	}

	/** List all subfolders (image folders) directly inside the parent folder. */
	private function list_subfolders( $folder_id ) {
		$result = $this->drive_get( 'files', [
			'q'        => "'{$folder_id}' in parents"
				. " and mimeType='application/vnd.google-apps.folder'"
				. " and trashed=false",
			'fields'   => 'files(id,name)',
			'pageSize' => 500,
		] );
		return $result['files'] ?? [];
	}

	/* ================================================== SHEET PARSING == */

	/**
	 * Walk rows to find which one contains the "Title" column header.
	 * Handles sheets that have a decorative section-band above the real headers.
	 */
	private function find_header_row( $rows ) {
		foreach ( $rows as $i => $row ) {
			foreach ( $row as $cell ) {
				$normalized = strtolower( trim( preg_replace( '/\s*\*\s*$/', '', (string) $cell ) ) );
				if ( $normalized === 'title' ) return $i;
			}
		}
		return 0; // fallback to first row
	}

	/**
	 * Parse a single data row using the header row for key mapping.
	 * Strips trailing " *" from header names (required-field markers).
	 *
	 * @param  array $headers  Values from the header row.
	 * @param  array $row      Values from a data row.
	 * @return array|false     Associative array of post/meta fields, or false if unusable.
	 */
	private function parse_row( $headers, $row ) {
		$data = [];

		foreach ( $headers as $i => $header ) {
			if ( empty( $header ) ) continue;

			// Strip " *" required marker and normalise to lowercase
			$key   = strtolower( trim( preg_replace( '/\s*\*\s*$/', '', (string) $header ) ) );
			$value = isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';

			$meta_key = self::COL_MAP[ $key ] ?? null;
			if ( $meta_key ) {
				$data[ $meta_key ] = $value;
			}
		}

		if ( empty( $data['post_title'] ) ) return false;

		// Normalise post_status
		$raw                  = strtolower( $data['post_status'] ?? '' );
		$data['post_status']  = ( $raw === 'draft' ) ? 'draft' : 'publish';

		return $data;
	}

	/* ================================================ POST CREATE/UPDATE == */

	/**
	 * Create a new marine_listing post or update the existing one.
	 * The lookup key is the lowercase listing title stored in the folder map.
	 */
	private function create_or_update_post( $key, $data ) {
		$map     = $this->get_folder_map();
		$post_id = isset( $map[ $key ] ) ? (int) $map[ $key ] : 0;

		$post_title  = $data['post_title'];
		$post_status = $data['post_status'];
		unset( $data['post_title'], $data['post_status'] );

		if ( $post_id && get_post( $post_id ) ) {
			wp_update_post( [
				'ID'          => $post_id,
				'post_title'  => $post_title,
				'post_status' => $post_status,
				'post_name'   => sanitize_title( $post_title ),
			] );
		} else {
			$post_id = wp_insert_post( [
				'post_title'  => $post_title,
				'post_status' => $post_status,
				'post_type'   => 'marine_listing',
				'post_name'   => sanitize_title( $post_title ),
			] );

			if ( is_wp_error( $post_id ) ) {
				$this->log( '  ERROR creating post: ' . $post_id->get_error_message() );
				return false;
			}

			$map[ $key ] = $post_id;
			$this->save_folder_map( $map );
		}

		// Save meta fields
		foreach ( $data as $meta_key => $value ) {
			if ( strpos( $meta_key, '_tnt_' ) === 0 ) {
				update_post_meta( $post_id, $meta_key, sanitize_textarea_field( $value ) );
			}
		}

		// Record the Drive key on the post (useful for debugging / map rebuilding)
		update_post_meta( $post_id, '_tnt_drive_key', $key );

		return $post_id;
	}

	/* ====================================================== IMAGE SYNC == */

	/**
	 * Find the image subfolder whose name matches the listing title and
	 * upload any images that haven't been uploaded yet.
	 */
	private function sync_images_from_subfolders( $post_id, $title, $subfolders ) {
		$title_lower = strtolower( trim( $title ) );
		$matched     = null;

		foreach ( $subfolders as $folder ) {
			if ( strtolower( trim( $folder['name'] ) ) === $title_lower ) {
				$matched = $folder;
				break;
			}
		}

		if ( ! $matched ) {
			// No image folder found – listing can exist without images
			return;
		}

		$result = $this->drive_get( 'files', [
			'q'        => "'{$matched['id']}' in parents and trashed=false",
			'fields'   => 'files(id,name,mimeType)',
			'pageSize' => 100,
		] );

		$image_files = array_values( array_filter(
			$result['files'] ?? [],
			fn( $f ) => strpos( $f['mimeType'], 'image/' ) === 0
		) );

		if ( ! empty( $image_files ) ) {
			$this->log( '  Images: ' . count( $image_files ) . " file(s) in \"{$matched['name']}\"" );
			$this->sync_images( $post_id, $image_files );
		}
	}

	/**
	 * Upload any Drive images not yet in the WP media library.
	 * Tracks uploaded Drive file IDs per post to avoid re-uploading.
	 */
	private function sync_images( $post_id, $image_files ) {
		$uploaded_map = get_post_meta( $post_id, '_tnt_drive_image_map', true );
		$uploaded_map = $uploaded_map ? (array) json_decode( $uploaded_map, true ) : [];

		$gallery_raw = get_post_meta( $post_id, '_tnt_gallery_ids', true );
		$gallery_ids = $gallery_raw
			? array_filter( array_map( 'intval', explode( ',', $gallery_raw ) ) )
			: [];

		$any_new = false;

		foreach ( $image_files as $file ) {
			if ( isset( $uploaded_map[ $file['id'] ] ) ) continue; // already uploaded

			$this->log( "    ↑ Uploading: {$file['name']}" );
			$att_id = $this->upload_image_to_wp( $post_id, $file['id'], $file['name'] );

			if ( $att_id ) {
				$uploaded_map[ $file['id'] ] = $att_id;
				$gallery_ids[]               = $att_id;
				$any_new                     = true;
				$this->log( "      ✓ Attachment ID {$att_id}" );
			} else {
				$this->log( "      ✗ Upload failed for {$file['name']}" );
			}
		}

		if ( $any_new ) {
			$gallery_ids = array_unique( array_filter( $gallery_ids ) );
			update_post_meta( $post_id, '_tnt_gallery_ids',     implode( ',', $gallery_ids ) );
			update_post_meta( $post_id, '_tnt_drive_image_map', json_encode( $uploaded_map ) );

			if ( ! has_post_thumbnail( $post_id ) && ! empty( $gallery_ids ) ) {
				set_post_thumbnail( $post_id, reset( $gallery_ids ) );
			}
		}
	}

	/** Download a Drive image and insert it into the WP media library. */
	private function upload_image_to_wp( $post_id, $file_id, $filename ) {
		$image_data = $this->drive_download( $file_id );
		if ( ! $image_data ) return false;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;

		$upload = wp_upload_dir();
		if ( $upload['error'] ) {
			$this->log( '  ERROR: ' . $upload['error'] );
			return false;
		}

		$filename = sanitize_file_name( $filename );
		$filepath = trailingslashit( $upload['path'] ) . $filename;
		$info     = pathinfo( $filename );
		$i        = 1;
		while ( file_exists( $filepath ) ) {
			$filename = $info['filename'] . '-' . $i . '.' . ( $info['extension'] ?? 'jpg' );
			$filepath = trailingslashit( $upload['path'] ) . $filename;
			$i++;
		}

		$wp_filesystem->put_contents( $filepath, $image_data, FS_CHMOD_FILE );

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$filetype = wp_check_filetype( $filename );
		$att_id   = wp_insert_attachment( [
			'post_mime_type' => $filetype['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_parent'    => $post_id,
		], $filepath, $post_id );

		if ( is_wp_error( $att_id ) ) return false;

		wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $filepath ) );
		return $att_id;
	}

	/* =============================================== REMOVAL DETECTION == */

	/**
	 * Draft any WP listings whose title key is no longer in the sheet.
	 * $active_keys is a list of lowercase trimmed titles found during this sync.
	 */
	private function unpublish_removed_listings( $active_keys ) {
		$map = $this->get_folder_map();
		foreach ( $map as $key => $post_id ) {
			if ( in_array( $key, $active_keys, true ) ) continue;
			$post = get_post( (int) $post_id );
			if ( $post && $post->post_status === 'publish' ) {
				wp_update_post( [ 'ID' => (int) $post_id, 'post_status' => 'draft' ] );
				$this->log( "  Unpublished post ID {$post_id} (\"{$key}\" removed from sheet)." );
			}
		}
	}

	/* ======================================================= AJAX SYNC == */

	public function ajax_manual_sync() {
		check_ajax_referer( 'tnt_drive_sync_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

		$this->run_sync();

		wp_send_json_success( [
			'message'   => 'Sync complete.',
			'last_sync' => get_option( 'tnt_marine_drive_last_sync', '—' ),
			'logs'      => get_option( 'tnt_marine_drive_log', [] ),
		] );
	}

	/* ======================================================= HELPERS == */

	/** @return array  title_key => post_id */
	private function get_folder_map() {
		return (array) get_option( 'tnt_marine_drive_folder_map', [] );
	}

	private function save_folder_map( $map ) {
		update_option( 'tnt_marine_drive_folder_map', $map );
	}

	private function log( $message ) {
		$logs = (array) get_option( 'tnt_marine_drive_log', [] );
		array_unshift( $logs, '[' . current_time( 'mysql' ) . '] ' . $message );
		update_option( 'tnt_marine_drive_log', array_slice( $logs, 0, 60 ) );
	}
}
