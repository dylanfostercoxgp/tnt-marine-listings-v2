<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * TNT Marine Listings – Google Drive Auto-Sync
 *
 * Watches a shared Google Drive folder for listing subfolders.
 * Each subfolder should be named after the listing and contain:
 *   • One Google Sheet  (row 1 = headers, row 2 = data)
 *   • Image files       (jpg / png / webp)
 *
 * Authentication is via a Google Cloud service-account JSON key.
 * All API calls use WordPress's built-in HTTP API (no Composer needed).
 */
class TNT_Drive_Sync {

	/** @var array Saved drive settings */
	private $settings;

	/**
	 * Maps lowercase Google Sheet column headers → WordPress meta keys.
	 * Add custom column names here if you use different headings in your sheet.
	 */
	private const COL_MAP = [
		// Post fields
		'title'               => 'post_title',
		'status'              => 'post_status',
		// Overview
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
		// Specifications
		'length overall'      => '_tnt_length_overall',
		'overall length'      => '_tnt_length_overall',
		'beam'                => '_tnt_beam',
		'dry weight'          => '_tnt_dry_weight',
		'fuel tanks'          => '_tnt_fuel_tanks',
		'fuel capacity'       => '_tnt_fuel_tanks',
		// Propulsion – single-engine shorthands
		'engine count'        => '_tnt_engine_count',
		'number of engines'   => '_tnt_engine_count',
		'engines'             => '_tnt_engine_count',
		'engine make'         => '_tnt_engine_make_1',
		'engine model'        => '_tnt_engine_model_1',
		'engine power'        => '_tnt_engine_power_1',
		'horsepower'          => '_tnt_engine_power_1',
		'hp'                  => '_tnt_engine_power_1',
		'fuel type'           => '_tnt_engine_fuel_1',
		// Propulsion – numbered (Engine 1-6)
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
		// Features & Description
		'description'         => '_tnt_description',
		'key features'        => '_tnt_power_features',
		'features'            => '_tnt_power_features',
		'notes'               => '_tnt_bonus',
		'disclaimers'         => '_tnt_bonus',
		'notes/disclaimers'   => '_tnt_bonus',
		// Status
		'sold'                => '_tnt_sold',
	];

	public function __construct() {
		$this->settings = get_option( 'tnt_marine_drive_settings', [] );

		add_filter( 'cron_schedules',            [ $this, 'add_cron_intervals' ] );
		add_action( 'init',                      [ $this, 'schedule_sync_cron' ] );
		add_action( 'tnt_marine_drive_sync',     [ $this, 'run_sync' ] );
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

	/** Call on deactivation or interval change to reset the cron job. */
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
	 * Result is cached in a transient for 58 minutes.
	 */
	private function get_access_token() {
		$cached = get_transient( 'tnt_drive_access_token' );
		if ( $cached ) return $cached;

		$json_str = $this->settings['service_account_json'] ?? '';
		if ( ! $json_str ) return false;

		$creds = json_decode( $json_str, true );
		if ( empty( $creds['private_key'] ) || empty( $creds['client_email'] ) ) {
			$this->log( 'ERROR: Service account JSON is missing private_key or client_email.' );
			return false;
		}

		$now    = time();
		$header  = $this->b64u( json_encode( [ 'alg' => 'RS256', 'typ' => 'JWT' ] ) );
		$payload = $this->b64u( json_encode( [
			'iss'   => $creds['client_email'],
			'scope' => implode( ' ', [
				'https://www.googleapis.com/auth/drive.readonly',
				'https://www.googleapis.com/auth/spreadsheets.readonly',
			] ),
			'aud'   => 'https://oauth2.googleapis.com/token',
			'iat'   => $now,
			'exp'   => $now + 3600,
		] ) );

		$to_sign = $header . '.' . $payload;
		$key     = openssl_pkey_get_private( $creds['private_key'] );
		if ( ! $key ) {
			$this->log( 'ERROR: Could not load private key from service account JSON.' );
			return false;
		}
		openssl_sign( $to_sign, $sig, $key, OPENSSL_ALGO_SHA256 );

		$jwt      = $to_sign . '.' . $this->b64u( $sig );
		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
			'body'    => [
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $jwt,
			],
			'timeout' => 20,
		] );

		if ( is_wp_error( $response ) ) {
			$this->log( 'ERROR: Token request failed – ' . $response->get_error_message() );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$this->log( 'ERROR: No access_token returned. Response: ' . wp_remote_retrieve_body( $response ) );
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

	/** Download raw binary content of a Drive file (images, etc.). */
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

	/** Read all values from a Google Sheet (row 1 = headers, row 2+ = data). */
	private function sheets_read( $spreadsheet_id ) {
		$token = $this->get_access_token();
		if ( ! $token ) return false;

		$url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/A1:ZZ1000";
		$response = wp_remote_get( $url, [
			'headers' => [ 'Authorization' => 'Bearer ' . $token ],
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) return false;
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['values'] ?? [];
	}

	/* ======================================================= SYNC CORE == */

	/** Main sync entry point – called by WP-Cron or manual trigger. */
	public function run_sync() {
		if ( ! $this->is_configured() ) {
			$this->log( 'Sync skipped: Google Drive not configured.' );
			return;
		}

		$this->log( '── Drive sync started ──' );
		$folder_id = sanitize_text_field( $this->settings['drive_folder_id'] );

		// List all listing subfolders inside the parent folder
		$result = $this->drive_get( 'files', [
			'q'       => "'{$folder_id}' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false",
			'fields'  => 'files(id,name)',
			'pageSize'=> 200,
		] );

		if ( ! $result ) {
			$this->log( 'ERROR: Could not list folders. Check that the parent folder is shared with the service account.' );
			update_option( 'tnt_marine_drive_last_sync', current_time( 'mysql' ) );
			return;
		}

		$folders = $result['files'] ?? [];
		if ( empty( $folders ) ) {
			$this->log( 'No listing folders found in the Drive folder.' );
			update_option( 'tnt_marine_drive_last_sync', current_time( 'mysql' ) );
			return;
		}

		$active_folder_ids = [];
		foreach ( $folders as $folder ) {
			$active_folder_ids[] = $folder['id'];
			$this->sync_listing_folder( $folder );
		}

		// Unpublish any WP listings whose folder no longer exists in Drive
		$this->unpublish_removed_listings( $active_folder_ids );

		update_option( 'tnt_marine_drive_last_sync', current_time( 'mysql' ) );
		$this->log( '── Sync complete. ' . count( $folders ) . ' folder(s) processed. ──' );
	}

	/** Process a single listing subfolder. */
	private function sync_listing_folder( $folder ) {
		$folder_id   = $folder['id'];
		$folder_name = $folder['name'];
		$this->log( "Folder: {$folder_name}" );

		// List all files in this subfolder
		$result = $this->drive_get( 'files', [
			'q'       => "'{$folder_id}' in parents and trashed=false",
			'fields'  => 'files(id,name,mimeType)',
			'pageSize'=> 100,
		] );

		$files = $result['files'] ?? [];
		if ( empty( $files ) ) {
			$this->log( "  → No files found. Skipping." );
			return;
		}

		// Separate the Google Sheet from images
		$sheet_file  = null;
		$image_files = [];
		foreach ( $files as $file ) {
			if ( $file['mimeType'] === 'application/vnd.google-apps.spreadsheet' ) {
				$sheet_file = $file;
			} elseif ( strpos( $file['mimeType'], 'image/' ) === 0 ) {
				$image_files[] = $file;
			}
		}

		if ( ! $sheet_file ) {
			$this->log( "  → No Google Sheet found. Skipping." );
			return;
		}

		// Read the sheet
		$rows = $this->sheets_read( $sheet_file['id'] );
		if ( empty( $rows ) ) {
			$this->log( "  → Sheet is empty. Skipping." );
			return;
		}

		// Parse into meta data
		$data = $this->parse_sheet_data( $rows, $folder_name );
		if ( ! $data ) {
			$this->log( "  → Sheet has headers but no data row. Skipping." );
			return;
		}

		// Create or update the WP listing post
		$post_id = $this->create_or_update_post( $folder_id, $data );
		if ( ! $post_id ) {
			$this->log( "  → Failed to save post." );
			return;
		}
		$this->log( "  → Post ID {$post_id} saved." );

		// Upload any new images
		if ( ! empty( $image_files ) ) {
			$this->sync_images( $post_id, $image_files );
		}
	}

	/**
	 * Parse Google Sheet rows into a data array.
	 * Row 0 = column headers, Row 1 = values for this listing.
	 *
	 * @param  array  $rows        Raw values from Sheets API.
	 * @param  string $folder_name Used as fallback Title.
	 * @return array|false
	 */
	private function parse_sheet_data( $rows, $folder_name ) {
		if ( count( $rows ) < 2 ) return false;

		$headers = array_map( fn( $h ) => strtolower( trim( $h ) ), $rows[0] );
		$values  = $rows[1];

		$data = [];
		foreach ( $headers as $i => $header ) {
			if ( $header === '' ) continue;
			$value = isset( $values[ $i ] ) ? trim( $values[ $i ] ) : '';
			$meta_key = self::COL_MAP[ $header ] ?? null;
			if ( $meta_key ) {
				$data[ $meta_key ] = $value;
			}
		}

		// Fallback title = folder name
		if ( empty( $data['post_title'] ) ) {
			$data['post_title'] = $folder_name;
		}

		// Normalise post_status
		$raw_status = strtolower( $data['post_status'] ?? '' );
		$data['post_status'] = ( $raw_status === 'draft' ) ? 'draft' : 'publish';

		return $data;
	}

	/** Create a new post or update the existing one for this Drive folder. */
	private function create_or_update_post( $folder_id, $data ) {
		$map     = $this->get_folder_map();
		$post_id = isset( $map[ $folder_id ] ) ? (int) $map[ $folder_id ] : 0;

		// Extract post-level fields
		$post_title  = $data['post_title'];
		$post_status = $data['post_status'];
		unset( $data['post_title'], $data['post_status'] );

		if ( $post_id && get_post( $post_id ) ) {
			// Update existing post
			wp_update_post( [
				'ID'          => $post_id,
				'post_title'  => $post_title,
				'post_status' => $post_status,
				'post_name'   => sanitize_title( $post_title ),
			] );
		} else {
			// Create new post
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

			$map[ $folder_id ] = $post_id;
			$this->save_folder_map( $map );
		}

		// Save all meta fields
		foreach ( $data as $meta_key => $value ) {
			if ( strpos( $meta_key, '_tnt_' ) === 0 ) {
				update_post_meta( $post_id, $meta_key, sanitize_textarea_field( $value ) );
			}
		}

		// Record Drive folder ID on the post (used for map rebuilding if needed)
		update_post_meta( $post_id, '_tnt_drive_folder_id', $folder_id );

		return $post_id;
	}

	/* ====================================================== IMAGE SYNC == */

	/**
	 * Upload any new images from Drive to the WP media library and
	 * attach them to the listing's gallery. Already-uploaded images
	 * (tracked by Drive file ID) are skipped.
	 */
	private function sync_images( $post_id, $image_files ) {
		// Drive file IDs we've already uploaded for this post
		$uploaded_map = get_post_meta( $post_id, '_tnt_drive_image_map', true );
		$uploaded_map = $uploaded_map ? (array) json_decode( $uploaded_map, true ) : [];

		// Current gallery
		$gallery_raw  = get_post_meta( $post_id, '_tnt_gallery_ids', true );
		$gallery_ids  = $gallery_raw ? array_filter( array_map( 'intval', explode( ',', $gallery_raw ) ) ) : [];

		$any_new = false;
		foreach ( $image_files as $file ) {
			if ( isset( $uploaded_map[ $file['id'] ] ) ) {
				continue; // Already uploaded
			}

			$this->log( "  Uploading image: {$file['name']}" );
			$att_id = $this->upload_image_to_wp( $post_id, $file['id'], $file['name'] );

			if ( $att_id ) {
				$uploaded_map[ $file['id'] ] = $att_id;
				$gallery_ids[]               = $att_id;
				$any_new                     = true;
				$this->log( "    ✓ Attachment ID {$att_id}" );
			} else {
				$this->log( "    ✗ Upload failed for {$file['name']}" );
			}
		}

		if ( $any_new ) {
			$gallery_ids = array_unique( array_filter( $gallery_ids ) );
			update_post_meta( $post_id, '_tnt_gallery_ids',    implode( ',', $gallery_ids ) );
			update_post_meta( $post_id, '_tnt_drive_image_map', json_encode( $uploaded_map ) );

			// Set featured image to the first gallery image if not already set
			if ( ! has_post_thumbnail( $post_id ) && ! empty( $gallery_ids ) ) {
				set_post_thumbnail( $post_id, reset( $gallery_ids ) );
			}
		}
	}

	/** Download a Drive image and insert it into the WP media library. */
	private function upload_image_to_wp( $post_id, $file_id, $filename ) {
		$image_data = $this->drive_download( $file_id );
		if ( ! $image_data ) return false;

		// Ensure WP filesystem is available
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

		// Sanitise and de-duplicate filename
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

		$filetype = wp_check_filetype( $filename );

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$att_id = wp_insert_attachment( [
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

	/** Draft any WP listings whose Drive folder no longer exists. */
	private function unpublish_removed_listings( $active_folder_ids ) {
		$map = $this->get_folder_map();
		foreach ( $map as $folder_id => $post_id ) {
			if ( in_array( $folder_id, $active_folder_ids, true ) ) continue;
			$post = get_post( (int) $post_id );
			if ( $post && $post->post_status === 'publish' ) {
				wp_update_post( [ 'ID' => (int) $post_id, 'post_status' => 'draft' ] );
				$this->log( "Unpublished post ID {$post_id} – folder removed from Drive." );
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

	/** @return array folder_id => post_id */
	private function get_folder_map() {
		return (array) get_option( 'tnt_marine_drive_folder_map', [] );
	}

	private function save_folder_map( $map ) {
		update_option( 'tnt_marine_drive_folder_map', $map );
	}

	private function log( $message ) {
		$logs = (array) get_option( 'tnt_marine_drive_log', [] );
		array_unshift( $logs, '[' . current_time( 'mysql' ) . '] ' . $message );
		$logs = array_slice( $logs, 0, 60 ); // Keep last 60 entries
		update_option( 'tnt_marine_drive_log', $logs );
	}
}
