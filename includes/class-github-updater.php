<?php
/**
 * TNT Marine Listings – GitHub Auto-Updater
 *
 * Hooks into WordPress's native plugin-update system to check GitHub
 * tags for a newer version and applies updates automatically in the
 * background — no manual work required.
 *
 * How it works:
 *   1. A WordPress cron job fires twice daily, clears the update cache,
 *      queries GitHub for the latest tag, and runs WordPress's automatic
 *      updater.  If a newer version exists it is installed silently.
 *   2. The auto_update_plugin filter tells WordPress to always approve
 *      automatic updates for this plugin.
 *   3. The pre_set_site_transient_update_plugins filter keeps the standard
 *      "Update Available" badge visible in the Plugins screen as a fallback.
 *
 * Updating the plugin (for developers):
 *   - Bump the version in tnt-marine-listings.php + TNT_MARINE_VERSION.
 *   - Push to GitHub and tag the commit  v{new-version}  (e.g. v1.0.9).
 *   - Live sites will auto-update within the next cron cycle (~12 hrs max).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TNT_GitHub_Updater {

    /** @var string  plugin/plugin-file.php  (slug used by WP internally) */
    private string $slug;

    /** @var string  Absolute path to the main plugin file */
    private string $plugin_file;

    /** GitHub repository owner */
    private string $github_user = 'dylanfostercoxgp';

    /** GitHub repository name */
    private string $github_repo = 'tnt-marine-listings-v2';

    /** WP cron hook name */
    private string $cron_hook = 'tnt_marine_auto_update_check';

    /** Cached plugin header data */
    private ?array $plugin_data = null;

    /** Cached latest version string (e.g. "1.0.9") */
    private ?string $latest_version = null;

    // ------------------------------------------------------------------ //
    //  Boot                                                                //
    // ------------------------------------------------------------------ //

    public function __construct( string $plugin_file ) {
        $this->plugin_file = $plugin_file;
        $this->slug        = plugin_basename( $plugin_file );

        // Standard WP update-transient integration (shows badge in Plugins screen).
        add_filter( 'pre_set_site_transient_update_plugins',
                    [ $this, 'inject_update_info' ] );

        add_filter( 'plugins_api',
                    [ $this, 'plugin_details_popup' ], 10, 3 );

        add_filter( 'upgrader_source_selection',
                    [ $this, 'fix_extracted_folder' ], 10, 4 );

        // Always approve automatic updates for this plugin.
        add_filter( 'auto_update_plugin',
                    [ $this, 'enable_auto_update' ], 10, 2 );

        // Schedule (or re-schedule) our background update cron.
        add_action( 'init', [ $this, 'schedule_update_cron' ] );

        // Cron callback: force-check GitHub and apply updates.
        add_action( $this->cron_hook, [ $this, 'run_background_update' ] );
    }

    // ------------------------------------------------------------------ //
    //  Internal helpers                                                    //
    // ------------------------------------------------------------------ //

    /**
     * Return (and cache) this plugin's header data.
     */
    private function get_plugin_data(): array {
        if ( ! $this->plugin_data ) {
            if ( ! function_exists( 'get_plugin_data' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $this->plugin_data = get_plugin_data( $this->plugin_file );
        }
        return $this->plugin_data;
    }

    /**
     * Fetch the latest version string from the GitHub Tags API.
     * Tags should follow the format  v1.0.9  or  1.0.9.
     * Returns null on error or if no tags exist.
     */
    private function get_latest_version(): ?string {
        if ( $this->latest_version !== null ) {
            return $this->latest_version;
        }

        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/tags',
            $this->github_user,
            $this->github_repo
        );

        $response = wp_remote_get( $api_url, [
            'headers' => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
            ],
            'timeout' => 10,
        ] );

        if ( is_wp_error( $response ) ) {
            return null;
        }
        if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return null;
        }

        $tags = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $tags ) || ! is_array( $tags ) ) {
            return null;
        }

        // Tags are returned newest-first; pick the first semver-looking one.
        foreach ( $tags as $tag ) {
            $name = ltrim( $tag['name'] ?? '', 'v' );
            if ( preg_match( '/^\d+\.\d+(\.\d+)?$/', $name ) ) {
                $this->latest_version = $name;
                return $name;
            }
        }

        return null;
    }

    /**
     * Build the GitHub archive download URL for a given version.
     * GitHub always provides a zip for any tag — no formal release needed.
     */
    private function download_url( string $version ): string {
        return sprintf(
            'https://github.com/%s/%s/archive/refs/tags/v%s.zip',
            $this->github_user,
            $this->github_repo,
            $version
        );
    }

    // ------------------------------------------------------------------ //
    //  Auto-update: filter + cron                                          //
    // ------------------------------------------------------------------ //

    /**
     * Hook: auto_update_plugin
     * Tell WordPress to always auto-install updates for this plugin.
     *
     * @param bool|null $update
     * @param object    $item
     */
    public function enable_auto_update( $update, object $item ): bool {
        if ( ! empty( $item->slug ) && $item->slug === dirname( $this->slug ) ) {
            return true;
        }
        return (bool) $update;
    }

    /**
     * Hook: init
     * Register a twice-daily cron event that forces a fresh GitHub check
     * and applies any available update automatically.
     */
    public function schedule_update_cron(): void {
        if ( ! wp_next_scheduled( $this->cron_hook ) ) {
            wp_schedule_event( time(), 'twicedaily', $this->cron_hook );
        }
    }

    /**
     * Cron callback: tnt_marine_auto_update_check
     *
     * 1. Clears WordPress's cached update transient so the next check
     *    hits the GitHub API fresh.
     * 2. Asks WordPress to re-check all plugins for updates.
     * 3. Runs the WordPress automatic updater — which will install our
     *    plugin update because enable_auto_update() returns true for it.
     */
    public function run_background_update(): void {
        // Force a fresh API check on the next update_plugins call.
        delete_site_transient( 'update_plugins' );

        // Re-populate the transient (calls our inject_update_info filter).
        wp_update_plugins();

        // Load upgrader classes if not already available.
        if ( ! class_exists( 'WP_Automatic_Updater' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }

        // Run WordPress's built-in automatic updater.
        // It respects the auto_update_plugin filter, so our plugin
        // will be updated if a newer version was found above.
        $auto_updater = new WP_Automatic_Updater();
        $auto_updater->run();
    }

    // ------------------------------------------------------------------ //
    //  WordPress update-screen integration                                 //
    // ------------------------------------------------------------------ //

    /**
     * Hook: pre_set_site_transient_update_plugins
     *
     * Injects update info so the "Update Available" badge still appears
     * in the Plugins screen (useful if cron hasn't fired yet).
     */
    public function inject_update_info( object $transient ): object {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $latest_version = $this->get_latest_version();
        if ( ! $latest_version ) {
            return $transient;
        }

        $data = $this->get_plugin_data();

        if ( version_compare( $latest_version, $data['Version'], '>' ) ) {
            $transient->response[ $this->slug ] = (object) [
                'id'           => "github.com/{$this->github_user}/{$this->github_repo}",
                'slug'         => dirname( $this->slug ),
                'plugin'       => $this->slug,
                'new_version'  => $latest_version,
                'url'          => $data['PluginURI'],
                'package'      => $this->download_url( $latest_version ),
                'icons'        => [],
                'banners'      => [],
                'tested'       => '',
                'requires_php' => '',
                'compatibility'=> new stdClass(),
            ];
        }

        return $transient;
    }

    /**
     * Hook: plugins_api
     * Populates the "View version details" lightbox in the Plugins screen.
     *
     * @param false|object|array $result
     * @param string             $action
     * @param object             $args
     * @return false|object
     */
    public function plugin_details_popup( $result, string $action, object $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }
        if ( dirname( $this->slug ) !== $args->slug ) {
            return $result;
        }

        $latest_version = $this->get_latest_version();
        if ( ! $latest_version ) {
            return $result;
        }

        $data = $this->get_plugin_data();

        return (object) [
            'name'          => $data['Name'],
            'slug'          => dirname( $this->slug ),
            'version'       => $latest_version,
            'author'        => '<a href="' . esc_url( $data['AuthorURI'] ) . '">'
                               . esc_html( $data['Author'] ) . '</a>',
            'homepage'      => $data['PluginURI'],
            'requires'      => '5.8',
            'tested'        => '6.7',
            'sections'      => [
                'description' => '<p>' . esc_html( $data['Description'] ) . '</p>',
                'changelog'   => '<p>See <a href="https://github.com/'
                                 . esc_attr( $this->github_user ) . '/'
                                 . esc_attr( $this->github_repo )
                                 . '/tags" target="_blank">GitHub Tags</a> for version history.</p>',
            ],
            'download_link' => $this->download_url( $latest_version ),
        ];
    }

    /**
     * Hook: upgrader_source_selection
     *
     * GitHub's archive zip extracts to "tnt-marine-listings-v2-1.0.9/".
     * WordPress expects the folder to match the installed plugin directory
     * ("tnt-marine-listings-v2/"). This hook renames it before WP moves
     * it into place.
     */
    public function fix_extracted_folder(
        string $source,
        string $remote_source,
               $upgrader,
        array  $hook_extra
    ): string {
        if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->slug ) {
            return $source;
        }

        global $wp_filesystem;

        $expected = trailingslashit( $remote_source ) . dirname( $this->slug ) . '/';

        if ( trailingslashit( $source ) === $expected ) {
            return $source;
        }

        if ( $wp_filesystem && $wp_filesystem->exists( $source ) ) {
            if ( $wp_filesystem->move( $source, $expected, true ) ) {
                return $expected;
            }
        }

        return $source;
    }
}
