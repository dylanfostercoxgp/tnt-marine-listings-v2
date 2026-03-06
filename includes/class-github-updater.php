<?php
/**
 * TNT Marine Listings – GitHub Auto-Updater
 *
 * Hooks into WordPress's native plugin-update system to check GitHub
 * Releases for a newer version and enables one-click updates from the
 * WordPress Plugins admin screen — no third-party plugins required.
 *
 * How it works:
 *   1. On every WordPress update check, the class calls the GitHub
 *      Releases API to find the latest release tag.
 *   2. If the tag version is higher than the installed version, WordPress
 *      shows "Update Available" in the Plugins list.
 *   3. Clicking "Update Now" downloads the release zip from GitHub and
 *      installs it exactly like a WordPress.org update.
 *
 * Updating the plugin (for developers):
 *   - Bump the version in tnt-marine-listings.php + TNT_MARINE_VERSION.
 *   - Push the updated code to GitHub.
 *   - Create a GitHub Release tagged  v{new-version}  (e.g. v1.0.8).
 *   - Attach the plugin zip as a release asset named
 *     tnt-marine-listings-v2.zip  (optional but recommended – avoids the
 *     folder-rename step).
 *   - WordPress sites will detect the update on the next check.
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

    /** Cached plugin header data */
    private ?array $plugin_data = null;

    /** Cached GitHub API response */
    private ?object $github_release = null;

    // ------------------------------------------------------------------ //
    //  Boot                                                                //
    // ------------------------------------------------------------------ //

    public function __construct( string $plugin_file ) {
        $this->plugin_file = $plugin_file;
        $this->slug        = plugin_basename( $plugin_file );

        add_filter( 'pre_set_site_transient_update_plugins',
                    [ $this, 'inject_update_info' ] );

        add_filter( 'plugins_api',
                    [ $this, 'plugin_details_popup' ], 10, 3 );

        add_filter( 'upgrader_source_selection',
                    [ $this, 'fix_extracted_folder' ], 10, 4 );
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
     * Fetch (and cache) the latest GitHub release object.
     * Returns false on error.
     *
     * @return object|false
     */
    private function get_latest_release() {
        if ( $this->github_release ) {
            return $this->github_release;
        }

        $api_url  = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
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
            return false;
        }
        if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ) );
        if ( empty( $body->tag_name ) ) {
            return false;
        }

        $this->github_release = $body;
        return $this->github_release;
    }

    /**
     * Return the best download URL for the release.
     * Prefers a .zip attached as a release asset; falls back to GitHub's
     * auto-generated zipball.
     */
    private function get_download_url( object $release ): string {
        if ( ! empty( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                if ( str_ends_with( strtolower( $asset->name ), '.zip' ) ) {
                    return $asset->browser_download_url;
                }
            }
        }
        return $release->zipball_url;
    }

    // ------------------------------------------------------------------ //
    //  WordPress hooks                                                     //
    // ------------------------------------------------------------------ //

    /**
     * Hook: pre_set_site_transient_update_plugins
     *
     * Adds this plugin to the update transient when a newer version is
     * available on GitHub, triggering the standard WP "Update Available"
     * notice.
     */
    public function inject_update_info( object $transient ): object {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $transient;
        }

        $data           = $this->get_plugin_data();
        $latest_version = ltrim( $release->tag_name, 'v' );

        if ( version_compare( $latest_version, $data['Version'], '>' ) ) {
            $transient->response[ $this->slug ] = (object) [
                'id'          => "github.com/{$this->github_user}/{$this->github_repo}",
                'slug'        => dirname( $this->slug ),
                'plugin'      => $this->slug,
                'new_version' => $latest_version,
                'url'         => $data['PluginURI'],
                'package'     => $this->get_download_url( $release ),
                'icons'       => [],
                'banners'     => [],
                'tested'      => '',
                'requires_php'=> '',
                'compatibility'=> new stdClass(),
            ];
        }

        return $transient;
    }

    /**
     * Hook: plugins_api
     *
     * Populates the "View version details" lightbox in the Plugins screen
     * with release information pulled from GitHub.
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

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $result;
        }

        $data = $this->get_plugin_data();

        return (object) [
            'name'          => $data['Name'],
            'slug'          => dirname( $this->slug ),
            'version'       => ltrim( $release->tag_name, 'v' ),
            'author'        => '<a href="' . esc_url( $data['AuthorURI'] ) . '">'
                               . esc_html( $data['Author'] ) . '</a>',
            'homepage'      => $data['PluginURI'],
            'requires'      => '5.8',
            'tested'        => '6.7',
            'last_updated'  => ! empty( $release->published_at )
                               ? date( 'Y-m-d', strtotime( $release->published_at ) )
                               : '',
            'sections'      => [
                'description' => '<p>' . esc_html( $data['Description'] ) . '</p>',
                'changelog'   => ! empty( $release->body )
                                 ? '<pre>' . esc_html( $release->body ) . '</pre>'
                                 : '<p>See <a href="https://github.com/'
                                   . esc_attr( $this->github_user ) . '/'
                                   . esc_attr( $this->github_repo )
                                   . '/releases" target="_blank">GitHub Releases</a> for changelog.</p>',
            ],
            'download_link' => $this->get_download_url( $release ),
        ];
    }

    /**
     * Hook: upgrader_source_selection
     *
     * GitHub's zipball extracts to a randomly-named folder such as
     * "dylanfostercoxgp-tnt-marine-listings-v2-abc1234/".
     * WordPress expects the folder to keep the same name as the plugin
     * directory ("tnt-marine-listings-v2/").
     * This hook renames the extracted folder before WP moves it into place.
     *
     * @param string      $source        Path to extracted source.
     * @param string      $remote_source Path to temp dir.
     * @param WP_Upgrader $upgrader      Upgrader instance.
     * @param array       $hook_extra    Extra data passed to hook.
     */
    public function fix_extracted_folder(
        string $source,
        string $remote_source,
        /* WP_Upgrader */ $upgrader,
        array $hook_extra
    ): string {
        // Only act on updates to this specific plugin.
        if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->slug ) {
            return $source;
        }

        global $wp_filesystem;

        $expected_folder = trailingslashit( $remote_source ) . dirname( $this->slug ) . '/';

        // Nothing to do if the folder is already correctly named.
        if ( trailingslashit( $source ) === $expected_folder ) {
            return $source;
        }

        // Rename the extracted folder to the expected plugin directory name.
        if ( $wp_filesystem && $wp_filesystem->exists( $source ) ) {
            if ( $wp_filesystem->move( $source, $expected_folder, true ) ) {
                return $expected_folder;
            }
        }

        return $source;
    }
}
