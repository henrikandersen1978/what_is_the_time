<?php
/**
 * GitHub-based plugin updater.
 *
 * Checks for updates from GitHub releases and provides update information
 * to WordPress core update system.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/helpers
 */

/**
 * GitHub updater class.
 *
 * Handles checking for plugin updates from GitHub releases and integrating
 * with WordPress plugin update system.
 *
 * @since 1.0.0
 */
class WTA_GitHub_Updater {

	/**
	 * GitHub repository in format 'owner/repo'.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $github_repo;

	/**
	 * Current plugin version.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $version;

	/**
	 * Plugin basename (e.g., 'plugin-folder/plugin-file.php').
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $plugin_basename;

	/**
	 * Plugin slug (e.g., 'plugin-folder').
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $plugin_slug;

	/**
	 * Cache key for GitHub API response.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $cache_key;

	/**
	 * Cache duration in seconds (12 hours).
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    int
	 */
	private $cache_duration = 43200;

	/**
	 * Initialize the GitHub updater.
	 *
	 * @since 1.0.0
	 * @param string $github_repo     GitHub repository in format 'owner/repo'.
	 * @param string $version         Current plugin version.
	 * @param string $plugin_basename Plugin basename.
	 */
	public function __construct( $github_repo, $version, $plugin_basename ) {
		$this->github_repo     = $github_repo;
		$this->version         = $version;
		$this->plugin_basename = $plugin_basename;
		$this->plugin_slug     = dirname( $plugin_basename );
		$this->cache_key       = 'wta_github_release_' . md5( $github_repo );
	}

	/**
	 * Register hooks with WordPress.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
	}

	/**
	 * Check for plugin updates from GitHub.
	 *
	 * @since 1.0.0
	 * @param object $transient The update_plugins transient object.
	 * @return object Modified transient object.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		// Get latest release info from GitHub
		$release = $this->get_latest_release();

		if ( ! $release || is_wp_error( $release ) ) {
			return $transient;
		}

		// Compare versions
		if ( $this->is_newer_version( $release['version'] ) ) {
			$plugin_data = array(
				'slug'        => $this->plugin_slug,
				'plugin'      => $this->plugin_basename,
				'new_version' => $release['version'],
				'url'         => $release['html_url'],
				'package'     => $release['download_url'],
				'tested'      => get_bloginfo( 'version' ),
			);

			$transient->response[ $this->plugin_basename ] = (object) $plugin_data;
		}

		return $transient;
	}

	/**
	 * Provide plugin information for the "View details" screen.
	 *
	 * @since 1.0.0
	 * @param false|object|array $result The result object or array. Default false.
	 * @param string             $action The type of information being requested from the Plugin Installation API.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object Modified result object or false.
	 */
	public function plugin_info( $result, $action, $args ) {
		// Check if this is a request for our plugin
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( $this->plugin_slug !== $args->slug ) {
			return $result;
		}

		// Get latest release info
		$release = $this->get_latest_release();

		if ( ! $release || is_wp_error( $release ) ) {
			return $result;
		}

		// Build plugin info object
		$plugin_info = new stdClass();
		$plugin_info->name          = 'World Time AI';
		$plugin_info->slug          = $this->plugin_slug;
		$plugin_info->version       = $release['version'];
		$plugin_info->author        = '<a href="https://github.com/' . esc_attr( $this->github_repo ) . '">World Time AI Team</a>';
		$plugin_info->homepage      = 'https://github.com/' . $this->github_repo;
		$plugin_info->download_link = $release['download_url'];
		$plugin_info->sections      = array(
			'description' => $release['description'],
		);

		if ( ! empty( $release['changelog'] ) ) {
			$plugin_info->sections['changelog'] = $release['changelog'];
		}

		$plugin_info->last_updated = $release['published_at'];
		$plugin_info->requires     = '6.8';
		$plugin_info->requires_php = '8.4';
		$plugin_info->tested       = get_bloginfo( 'version' );

		return $plugin_info;
	}

	/**
	 * Get latest release information from GitHub.
	 *
	 * Uses transient caching to avoid hitting GitHub API on every request.
	 *
	 * @since 1.0.0
	 * @return array|false|WP_Error Release data array or false on failure.
	 */
	private function get_latest_release() {
		// Check cache first
		$cached = get_transient( $this->cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		// Fetch from GitHub API
		$api_url  = sprintf( 'https://api.github.com/repos/%s/releases/latest', $this->github_repo );
		$response = wp_remote_get(
			$api_url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/vnd.github.v3+json',
				),
			)
		);

		// Handle errors gracefully
		if ( is_wp_error( $response ) ) {
			WTA_Logger::error( 'GitHub API request failed: ' . $response->get_error_message() );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			WTA_Logger::error( 'GitHub API returned status code: ' . $response_code );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data ) || ! isset( $data['tag_name'] ) ) {
			WTA_Logger::error( 'Invalid GitHub API response' );
			return false;
		}

		// Parse release data
		$release = array(
			'version'      => $this->normalize_version( $data['tag_name'] ),
			'html_url'     => $data['html_url'] ?? '',
			'download_url' => $data['zipball_url'] ?? '',
			'description'  => ! empty( $data['body'] ) ? $this->parse_description( $data['body'] ) : 'No description available.',
			'changelog'    => ! empty( $data['body'] ) ? $this->parse_changelog( $data['body'] ) : '',
			'published_at' => $data['published_at'] ?? '',
		);

		// Cache the result
		set_transient( $this->cache_key, $release, $this->cache_duration );

		return $release;
	}

	/**
	 * Normalize version string by removing 'v' prefix.
	 *
	 * @since 1.0.0
	 * @param string $version Version string (e.g., 'v1.0.0' or '1.0.0').
	 * @return string Normalized version string.
	 */
	private function normalize_version( $version ) {
		return ltrim( $version, 'vV' );
	}

	/**
	 * Compare if a version is newer than the current version.
	 *
	 * @since 1.0.0
	 * @param string $new_version Version to compare.
	 * @return bool True if new version is newer.
	 */
	private function is_newer_version( $new_version ) {
		return version_compare( $new_version, $this->version, '>' );
	}

	/**
	 * Parse description from release body.
	 *
	 * Extracts the first paragraph or first 200 characters.
	 *
	 * @since 1.0.0
	 * @param string $body Release body text.
	 * @return string Parsed description.
	 */
	private function parse_description( $body ) {
		// Convert markdown to HTML (basic conversion)
		$description = wpautop( wp_kses_post( $body ) );

		// If too long, truncate to first paragraph or 500 characters
		$paragraphs = explode( '</p>', $description );
		if ( ! empty( $paragraphs[0] ) ) {
			$description = $paragraphs[0] . '</p>';
		}

		if ( strlen( $description ) > 500 ) {
			$description = wp_trim_words( $description, 50, '...' );
		}

		return $description;
	}

	/**
	 * Parse changelog from release body.
	 *
	 * @since 1.0.0
	 * @param string $body Release body text.
	 * @return string Parsed changelog HTML.
	 */
	private function parse_changelog( $body ) {
		// Convert markdown to HTML (basic conversion)
		$changelog = wpautop( wp_kses_post( $body ) );
		return '<h3>Release Notes</h3>' . $changelog;
	}

	/**
	 * Clear the cached release data.
	 *
	 * Useful for forcing a fresh check, e.g., from admin tools.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	public function clear_cache() {
		return delete_transient( $this->cache_key );
	}
}

