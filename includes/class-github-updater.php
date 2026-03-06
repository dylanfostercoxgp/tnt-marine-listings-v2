<?php
/**
 * TNT Marine Listings – GitHub Auto-Updater
 *
 * Hooks into WordPress's native plugin-update system to check GitHub
 * tags for a newer version and enables one-click updates from the
 * WordPress Plugins admin screen — no third-party plugins required.
 *
 * How it works:
 *   1. On every WordPress update check, the class calls the GitHub
 *      Tags API to find the latest version tag.
 *   2. If the tag version is higher than the installed version, WordPress
 *      shows "Update Available" in the Plugins list.
 *   3. Clicking "Update Now" downloads the archive zip from GitHub and
 *      installs it exactly like a WordPress.org update.
 *
 * Updating the plugin:
 *   - Make changes to the plugin code.
 *   - Bump the version in tnt-marine-listings.php + TNT_MARINE_VERSION.
 *   - Push to GitHub and create a new tag  v{new-version}  (e.g. v1.0.8).
 *   - WordPress sites will detect the update on the next check (~12 hrs)
 *     or immediately after clicking "Check Again" in the Plugins screen.
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

    /** Cached latest version string (e.g. "1.0.8") */
    private ?string $latest_version = null;

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
     * Fetch the latest version string from the GitHub Tags API.
     * Tags should follow the format  v1.0.7  or  1.0.7.
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

        // Tags are returned newest-first. Pick the first semver-looking tag.
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
     * GitHub always provides a zip archive for any tag — no release needed.
     *
     * @param string $version  e.g. "1.0.8"
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
     *
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
     * GitHub's archive zip extracts to a folder like
     * "tnt-marine-listings-v2-1.0.7/".
     * WordPress expects the folder to match the installed plugin directory
     * name ("tnt-marine-listings-v2/").
     * This hook renames the folder before WP moves it into place.
     *
     * @param string      $source        Path to extracted source.
     * @param string      $remote_source Path to temp dir.
     * @param WP_Upgrader $upgrader      Upgrader instance.
     * @param array       $hook_extra    Extra data passed to hook.
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
