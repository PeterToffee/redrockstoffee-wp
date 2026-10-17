<?php
/**
 * User Enumeration Protection Class
 *
 * @package All In One Login
 */

namespace AIO_Login\User_Enumeration_Protection;

defined( 'ABSPATH' ) || exit;

/**
 * Class User_Enumeration_Protection
 */
class User_Enumeration_Protection {

	/**
	 * Instance of this class
	 *
	 * @var User_Enumeration_Protection $instance The single instance of the class.
	 */
	private static $instance;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Get the instance of this class
	 *
	 * @return User_Enumeration_Protection
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the protection
	 */
	public function init() {
		if ( $this->is_protection_enabled() ) {
			$this->add_protection_hooks();
		}
	}

	/**
	 * Check if protection is enabled
	 */
	private function is_protection_enabled() {
		return get_option( 'aio_login_user_enumeration_enable', 'off' ) === 'on';
	}

	/**
	 * Add protection hooks
	 */
	private function add_protection_hooks() {
		// Author page protection
		add_action( 'template_redirect', array( $this, 'block_author_pages' ) );

		// Author sitemaps protection
		add_filter( 'wp_sitemaps_users_query_args', array( $this, 'disable_author_sitemaps' ) );
		add_action( 'template_redirect', array( $this, 'block_author_sitemap_urls' ) );
		add_filter( 'wp_sitemaps_providers', array( $this, 'remove_users_sitemap_provider' ) );
		add_filter( 'wp_sitemaps_index_entry', array( $this, 'filter_sitemap_index_entry' ), 10, 2 );

		// oEmbed protection
		if ( $this->is_oembed_protection_enabled() ) {
			add_filter( 'oembed_response_data', array( $this, 'filter_oembed_response' ), 10, 4 );
		}

		// REST API protection
		if ( $this->is_rest_api_protection_enabled() ) {
			add_filter( 'rest_authentication_errors', array( $this, 'protect_rest_api' ), 10, 1 );
			add_filter( 'rest_user_collection_params', array( $this, 'filter_user_collection_params' ) );
		}

		// Login/Registration error protection
		if ( $this->is_login_registration_protection_enabled() ) {
			add_filter( 'login_errors', array( $this, 'generic_login_error' ) );
			add_filter( 'registration_errors', array( $this, 'generic_registration_error' ) );
		}

		// Comment protection
		if ( $this->is_comment_protection_enabled() ) {
			add_filter( 'get_comment_author', array( $this, 'filter_comment_author' ), 10, 3 );
			add_filter( 'get_comment_author_url', array( $this, 'filter_comment_author_url' ), 10, 3 );
		}

		// Post author protection (always active when main protection is enabled)
		if ( $this->is_protection_enabled() ) {
			add_filter( 'the_author', array( $this, 'obfuscate_author_name' ) );
			add_filter( 'author_link', array( $this, 'obfuscate_author_link' ) );
			add_filter( 'get_the_author', array( $this, 'obfuscate_author_name' ) );
			add_filter( 'get_the_author_display_name', array( $this, 'obfuscate_author_name' ) );
			add_filter( 'the_author_posts_link', array( $this, 'obfuscate_author_link' ) );
			add_filter( 'the_content', array( $this, 'obfuscate_author_in_content' ) );
		}

		// Logging
		if ( $this->is_logging_enabled() ) {
			add_action( 'wp_login_failed', array( $this, 'log_failed_login' ) );
			add_action( 'user_register', array( $this, 'log_registration_attempt' ) );
		}
	}



	/**
	 * Block author pages for non-admin users
	 */
	public function block_author_pages() {
		if ( is_author() ) {
			// Allow admin users and authors accessing their own pages
			if ( current_user_can( 'manage_options' ) ) {
				return;
			}

			$author = get_queried_object();
			if ( $author && is_user_logged_in() && $GLOBALS['current_user']->user_login === $author->user_login ) {
				return;
			}

			// Log the attempt
			$this->log_enumeration_attempts();

			// Obfuscate the page content
			$this->obfuscate_author_page();
		}
	}

	/**
	 * Disable author sitemaps
	 */
	public function disable_author_sitemaps( $args ) {
		if ( $this->is_author_sitemaps_disabled() ) {
			$args['number'] = 0;
		}
		return $args;
	}

	/**
	 * Block author sitemap URLs
	 */
	public function block_author_sitemap_urls() {
		if ( $this->is_author_sitemaps_disabled() ) {
			global $wp;
			if ( strpos( $wp->request, 'wp-sitemap-users' ) !== false ) {
				wp_die( 'Author sitemap not available.', 'Not Found', array( 'response' => 404 ) );
			}
		}
	}

	/**
	 * Remove users sitemap provider from main sitemap index
	 */
	public function remove_users_sitemap_provider( $providers ) {
		if ( $this->is_author_sitemaps_disabled() ) {
			// Remove the users sitemap provider
			unset( $providers['users'] );
		}
		return $providers;
	}

	/**
	 * Filter sitemap index entries to remove users sitemap
	 */
	public function filter_sitemap_index_entry( $entry, $subtype ) {
		if ( $this->is_author_sitemaps_disabled() && $subtype === 'users' ) {
			return false; // Remove this entry
		}
		
		return $entry;
	}

	/**
	 * Filter oEmbed response to remove author information
	 */
	public function filter_oembed_response( $data, $post, $width, $height ) {
		// Remove author information from oEmbed response
		unset( $data['author_name'] );
		unset( $data['author_url'] );
		
		return $data;
	}

	/**
	 * Obfuscate author page content
	 */
	private function obfuscate_author_page() {
		// Add filters to obfuscate author information (content only)
		add_filter( 'the_author', array( $this, 'obfuscate_author_name' ) );
		add_filter( 'get_the_author_display_name', array( $this, 'obfuscate_author_name' ) );
		add_filter( 'get_the_author', array( $this, 'obfuscate_author_name' ) );
		// Don't filter author_link to prevent URL changes
		add_filter( 'get_avatar', array( $this, 'obfuscate_author_avatar' ), 10, 6 );
		add_filter( 'author_feed_link', '__return_empty_string' );

		// Remove author meta tags
		add_action( 'wp_head', array( $this, 'remove_author_meta' ) );

		// Add CSS to hide sensitive elements
		add_action( 'wp_head', array( $this, 'add_obfuscation_css' ) );
	}

	/**
	 * Obfuscate author name
	 */
	public function obfuscate_author_name( $name ) {
		// Allow admin users and authors to see real names
		if ( current_user_can( 'manage_options' ) ) {
			return $name;
		}
		
		// Allow authors to see their own names
		if ( is_user_logged_in() && $GLOBALS['current_user']->user_login === $name ) {
			return $name;
		}
		
		// Generate consistent obfuscated name for others
		$salt = 'aio_login_protection';
		$hash = md5( $name . $salt );
		$obfuscated = 'Protected_User_' . substr( $hash, 0, 8 );
		return $obfuscated;
	}

	/**
	 * Obfuscate author link
	 */
	public function obfuscate_author_link( $link ) {
		// Return original link to prevent 404s
		return $link;
	}

	/**
	 * Obfuscate author in content
	 */
	public function obfuscate_author_in_content( $content ) {
		// Replace "Written by admin" with obfuscated version
		$content = preg_replace_callback(
			'/Written by\s+(\w+)/i',
			function( $matches ) {
				$author = $matches[1];
				$obfuscated = $this->obfuscate_author_name( $author );
				return 'Written by ' . $obfuscated;
			},
			$content
		);
		
		return $content;
	}

	/**
	 * Obfuscate author avatar
	 */
	public function obfuscate_author_avatar( $avatar, $id_or_email, $size, $default, $alt, $args ) {
		// Return generic avatar
		return get_avatar( 'nobody@example.com', $size, $default, $alt, $args );
	}

	/**
	 * Remove author meta tags
	 */
	public function remove_author_meta() {
		remove_action( 'wp_head', 'author_feed_link' );
		remove_action( 'wp_head', 'author_link' );
	}

	/**
	 * Add obfuscation CSS
	 */
	public function add_obfuscation_css() {
		echo '<style>
			.author-bio, .author-description, .author-email, .author-url, .author-meta, .author-posts-link, .author-archive-link { display: none !important; }
			.author-name::after { content: " (Protected)"; }
		</style>';
	}

	/**
	 * Protect REST API user endpoints from public enumeration.
	 * Do not block /users/me — WordPress admin (api-fetch) needs it.
	 */
	public function protect_rest_api( $result ) {
		// If there's already an error, return it
		if ( $result !== null ) {
			return $result;
		}

		// Authenticated requests are fine (and cookie auth may still be resolving).
		if ( is_user_logged_in() ) {
			return $result;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( '' === $request_uri || false === strpos( $request_uri, '/wp-json/wp/v2/users' ) ) {
			return $result;
		}

		// Current-user endpoint is not enumeration — used heavily by wp-admin.
		if ( preg_match( '#/wp-json/wp/v2/users/me(?:/|\?|$)#', $request_uri ) ) {
			return $result;
		}

		return new \WP_Error( 'rest_forbidden', 'User enumeration not allowed.', array( 'status' => 403 ) );
	}

	/**
	 * Filter user collection parameters
	 */
	public function filter_user_collection_params( $params ) {
		// Remove sensitive parameters
		unset( $params['search'] );
		unset( $params['slug'] );
		return $params;
	}

	/**
	 * Generic login error message
	 */
	public function generic_login_error( $error ) {
		return 'Invalid username or password.';
	}

	/**
	 * Generic registration error
	 */
	public function generic_registration_error( $errors ) {
		// Remove specific error messages that might reveal user information
		if ( isset( $errors->errors['username_exists'] ) ) {
			unset( $errors->errors['username_exists'] );
			$errors->add( 'username_exists', 'Registration failed. Please try again.' );
		}
		
		if ( isset( $errors->errors['email_exists'] ) ) {
			unset( $errors->errors['email_exists'] );
			$errors->add( 'email_exists', 'Registration failed. Please try again.' );
		}
		
		return $errors;
	}

	/**
	 * Filter comment author information
	 */
	public function filter_comment_author( $author, $comment_ID, $comment ) {
		// Allow admin users to see real comment author names
		if ( current_user_can( 'manage_options' ) ) {
			return $author;
		}
		
		// Allow comment authors to see their own names
		if ( is_user_logged_in() && $GLOBALS['current_user']->user_login === $author ) {
			return $author;
		}
		
		// Obfuscate comment author names to prevent username enumeration
		if ( ! empty( $author ) ) {
			$hash = md5( $author . $comment_ID );
			return 'Protected_User_' . substr( $hash, 0, 8 );
		}
		return $author;
	}

	/**
	 * Filter comment author URL
	 */
	public function filter_comment_author_url( $url, $comment_ID, $comment ) {
		// Return empty URL for comment author links
		return '';
	}

	/**
	 * Log failed login attempts
	 */
	public function log_failed_login( $username ) {
		$this->log_enumeration_attempt( 'failed_login', $username );
	}

	/**
	 * Log registration attempts
	 */
	public function log_registration_attempt( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( $user ) {
			$this->log_enumeration_attempt( 'registration_attempt', $user->user_login );
		}
	}

	/**
	 * Log enumeration attempts
	 */
	public function log_enumeration_attempts() {
		$this->log_enumeration_attempt( 'author_page_access' );
	}

	/**
	 * Log enumeration attempt
	 */
	private function log_enumeration_attempt( $type, $username = '' ) {
		if ( ! $this->is_logging_enabled() ) {
			return;
		}

		global $wpdb;

		$ip_address = $this->get_client_ip();
		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
		$duration = get_option( 'aio_login_user_enumeration_duration', 30 );
		$blocked_until = time() + ( $duration * 24 * 60 * 60 );

		// Check if IP is already logged
		$existing_log = $this->get_existing_log( $ip_address );

		if ( $existing_log ) {
			// Update existing log
			$this->update_enumeration_log( $existing_log->id, $type, $username, $blocked_until );
		} else {
			// Create new log
			$this->create_enumeration_log( $ip_address, $type, $username, $user_agent, $blocked_until );
		}

		do_action( 'aio_login_enumeration_logged', $type, $username, $ip_address );
	}

	/**
	 * Get client IP address
	 */
	private function get_client_ip() {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		
		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
					$ip = trim( $ip );
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
						return $ip;
					}
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	}

	/**
	 * Get existing log for IP
	 */
	private function get_existing_log( $ip_address ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'aio_login_enumeration_logs';
		
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE ip_address = %s ORDER BY created_at DESC LIMIT 1",
			$ip_address
		) );
	}

	/**
	 * Create enumeration log
	 */
	private function create_enumeration_log( $ip_address, $type, $username, $user_agent, $blocked_until ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'aio_login_enumeration_logs';
		
		$wpdb->insert(
			$table_name,
			array(
				'ip_address' => $ip_address,
				'type' => $type,
				'username' => $username,
				'user_agent' => $user_agent,
				'blocked_until' => date( 'Y-m-d H:i:s', $blocked_until ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Update enumeration log
	 */
	private function update_enumeration_log( $log_id, $type, $username, $blocked_until ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'aio_login_enumeration_logs';
		
		$wpdb->update(
			$table_name,
			array(
				'type' => $type,
				'username' => $username,
				'blocked_until' => date( 'Y-m-d H:i:s', $blocked_until ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $log_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Check if author sitemaps are disabled
	 */
	private function is_author_sitemaps_disabled() {
		$enabled = get_option( 'aio_login_user_enumeration_sitemaps', 'off' ) === 'on';
		return $enabled;
	}

	/**
	 * Check if oEmbed protection is enabled
	 */
	private function is_oembed_protection_enabled() {
		$enabled = get_option( 'aio_login_user_enumeration_oembed', 'off' ) === 'on';
		return $enabled;
	}

	/**
	 * Check if REST API protection is enabled
	 */
	private function is_rest_api_protection_enabled() {
		return get_option( 'aio_login_user_enumeration_rest_api', 'off' ) === 'on';
	}

	/**
	 * Check if login/registration protection is enabled
	 */
	private function is_login_registration_protection_enabled() {
		return get_option( 'aio_login_user_enumeration_login_registration', 'off' ) === 'on';
	}

	/**
	 * Check if comment protection is enabled
	 */
	private function is_comment_protection_enabled() {
		return get_option( 'aio_login_user_enumeration_comments', 'off' ) === 'on';
	}

	/**
	 * Check if logging is enabled
	 */
	private function is_logging_enabled() {
		return get_option( 'aio_login_user_enumeration_log', 'off' ) === 'on';
	}
} 