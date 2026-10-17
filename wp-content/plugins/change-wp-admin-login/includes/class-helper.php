<?php
/**
 * Class Helper
 *
 * @package AIO Login
 */

namespace AIO_Login\Helper;

use AIO_Login\Login_Controller\Failed_Logins;

defined('ABSPATH') || exit;

if (!class_exists('AIO_Login\\Helper\\Helper')) {
	/**
	 * Class Helper
	 */
	class Helper
	{
		/**
		 * Getting the IP address of the user
		 *
		 * Trusts REMOTE_ADDR by default, including private/reserved addresses (load balancers).
		 * Proxy headers are ignored unless an operator configures a trusted proxy header.
		 *
		 * @return string
		 */
		public static function get_ip()
		{
			$remote = '';
			if (isset($_SERVER['REMOTE_ADDR'])) {
				$remote = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
			}

			if ('::1' === $remote) {
				$remote = '127.0.0.1';
			}

			$header = (string) apply_filters(
				'aio_login_trusted_proxy_header',
				(string) get_option('aio_login_trusted_proxy_header', '')
			);
			$trust_proxy = (bool) apply_filters(
				'aio_login_trust_proxy',
				'' !== $header && self::is_trusted_proxy_peer($remote)
			);

			if ($trust_proxy && '' !== $header) {
				$forwarded = self::ip_from_trusted_header($header);
				if ('' !== $forwarded) {
					return $forwarded;
				}
			}

			if (self::is_valid_ip($remote)) {
				return $remote;
			}

			return '0.0.0.0';
		}

		/**
		 * Validate if an IP address is valid (public, private, or reserved).
		 *
		 * @param string $ip IP address to validate.
		 * @return bool
		 */
		private static function is_valid_ip($ip)
		{
			$ip = trim((string) $ip);
			if ('' === $ip || 'UNKNOWN' === $ip) {
				return false;
			}

			return false !== filter_var($ip, FILTER_VALIDATE_IP);
		}

		/**
		 * Whether REMOTE_ADDR is a configured proxy (or private peer when a header is set).
		 *
		 * @param string $ip Connecting IP.
		 * @return bool
		 */
		private static function is_trusted_proxy_peer($ip)
		{
			$ip = trim((string) $ip);
			if ('' === $ip || !self::is_valid_ip($ip)) {
				return false;
			}

			$proxies = apply_filters(
				'aio_login_trusted_proxies',
				array_filter(array_map('trim', explode(',', (string) get_option('aio_login_trusted_proxies', ''))))
			);

			if (!empty($proxies) && is_array($proxies)) {
				foreach ($proxies as $proxy) {
					if ($ip === $proxy || self::ip_in_cidr($ip, $proxy)) {
						return true;
					}
				}
				return false;
			}

			return false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
		}

		/**
		 * Read client IP from a trusted $_SERVER header name (e.g. HTTP_CF_CONNECTING_IP).
		 *
		 * @param string $header Header key.
		 * @return string
		 */
		private static function ip_from_trusted_header($header)
		{
			$header = strtoupper(str_replace('-', '_', trim((string) $header)));
			if ('' === $header) {
				return '';
			}
			if (0 !== strpos($header, 'HTTP_') && 'REMOTE_ADDR' !== $header) {
				$header = 'HTTP_' . $header;
			}

			if (!isset($_SERVER[$header])) {
				return '';
			}

			$raw = sanitize_text_field(wp_unslash($_SERVER[$header]));
			$parts = preg_split('/\s*,\s*/', $raw);
			if (!is_array($parts)) {
				return '';
			}

			foreach ($parts as $part) {
				$candidate = trim($part);
				if (0 === strpos($candidate, '[')) {
					$candidate = trim($candidate, '[]');
				}
				if (false !== strpos($candidate, ':') && substr_count($candidate, ':') === 1 && preg_match('/^(\d+\.\d+\.\d+\.\d+):\d+$/', $candidate, $m)) {
					$candidate = $m[1];
				}
				if (self::is_valid_ip($candidate) && false !== filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
					return $candidate;
				}
			}

			foreach ($parts as $part) {
				$candidate = trim($part);
				if (self::is_valid_ip($candidate)) {
					return $candidate;
				}
			}

			return '';
		}

		/**
		 * @param string $ip   IP.
		 * @param string $cidr IP or CIDR.
		 * @return bool
		 */
		private static function ip_in_cidr($ip, $cidr)
		{
			$cidr = trim((string) $cidr);
			if ('' === $cidr) {
				return false;
			}
			if (false === strpos($cidr, '/')) {
				return $ip === $cidr;
			}

			list($subnet, $bits) = array_pad(explode('/', $cidr, 2), 2, '');
			$bits = absint($bits);
			$ip_bin = @inet_pton($ip); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$subnet_bin = @inet_pton($subnet); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if (false === $ip_bin || false === $subnet_bin || strlen($ip_bin) !== strlen($subnet_bin)) {
				return false;
			}

			$max_bits = 8 * strlen($ip_bin);
			if ($bits < 0 || $bits > $max_bits) {
				return false;
			}

			$mask = str_repeat("\xff", (int) floor($bits / 8));
			$rem = $bits % 8;
			if ($rem > 0) {
				$mask .= chr((0xff << (8 - $rem)) & 0xff);
			}
			$mask = str_pad($mask, strlen($ip_bin), "\x00");

			return ($ip_bin & $mask) === ($subnet_bin & $mask);
		}

		/**
		 * Getting the location of the user by IP.
		 *
		 * Uses ipapi (https://ipapi.co) for IP geolocation and normalizes
		 * the response to a simple associative array containing at least
		 * 'country' and 'city' keys for use across the plugin.
		 *
		 * @param string $ip The IP address of the user.
		 *
		 * @return array {
		 *     @type string $country Country name (if available).
		 *     @type string $city    City name (if available).
		 * }
		 */
		public static function get_location($ip = '')
		{
			if (empty($ip)) {
				$ip = self::get_ip();
			}

			// If we still don't have a valid IP, bail early.
			if (empty($ip) || 'UNKNOWN' === $ip) {
				return array(
					'country' => '',
					'city'    => '',
				);
			}

			// Cache lookups per IP to avoid repeated external requests.
			$cache_key = 'aio_login_ip_location_' . md5($ip);
			$cached    = get_transient($cache_key);
			if (false !== $cached && is_array($cached)) {
				return $cached;
			}

			// Use ipapi.co JSON API – no key required for basic data.
			$url      = sprintf('https://ipapi.co/%s/json/', rawurlencode($ip));
			$response = wp_remote_get(
				$url,
				array(
					'timeout'   => 5,
					'sslverify' => true,
				)
			);

			if (is_wp_error($response)) {
				return array(
					'country' => '',
					'city'    => '',
				);
			}

			$code = wp_remote_retrieve_response_code($response);
			if (200 !== $code) {
				return array(
					'country' => '',
					'city'    => '',
				);
			}

			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);

			if (!is_array($data)) {
				return array(
					'country' => '',
					'city'    => '',
				);
			}

			// ipapi returns 'country_name' and 'city'. Normalize to 'country' and 'city'.
			$location = array(
				'country' => isset($data['country_name']) ? sanitize_text_field($data['country_name']) : '',
				'city'    => isset($data['city']) ? sanitize_text_field($data['city']) : '',
			);

			// Cache for 1 day to balance freshness and performance.
			set_transient($cache_key, $location, DAY_IN_SECONDS);

			return $location;
		}

		/**
		 * Create table
		 *
		 * @param string $table_name Table name.
		 * @param array  $cols Columns.
		 *
		 * @return bool
		 */
		public static function create_table($table_name, $cols = array())
		{
			global $wpdb;
			$charset_collate = $wpdb->get_charset_collate();
			$table_name = $wpdb->prefix . 'aio_login_' . $table_name;

			$sql = "CREATE TABLE IF NOT EXISTS $table_name (
				id bigint(20) NOT NULL AUTO_INCREMENT,";

			foreach ($cols as $col => $val) {
				$sql .= $col . ' ' . $val . ',';
			}

			$sql .= "
				PRIMARY KEY (id)
			) $charset_collate;";

			if (!function_exists('maybe_create_table')) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}

			return maybe_create_table($table_name, $sql);
		}

		/**
		 * Drop table
		 *
		 * @param string $table_name Table name.
		 *
		 * @return bool
		 */
		public static function drop_table($table_name)
		{
			global $wpdb;
			$table_name = $wpdb->prefix . 'aio_login_' . $table_name;

			$sql = "DROP TABLE IF EXISTS $table_name;";

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			return $wpdb->query(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$sql
			);
		}

		/**
		 * Blocking user IP from login
		 *
		 * @param string $ip IP.
		 */
		public static function block_ip($ip = '')
		{
			// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			$current_time = current_time('timestamp');
			if (empty($ip)) {
				$ip = self::get_ip();
			}

			$location = self::get_location($ip);
			$country = isset($location['country']) ? $location['country'] : 'Unknown';
			$city = isset($location['city']) ? $location['city'] : 'Unknown';

			$user_agent = 'Unknown';
			if (isset($_POST['aio_login__user_agent'])) { // phpcs:ignore WordPress.Security.NonceVerification
				$user_agent = sanitize_text_field(wp_unslash($_POST['aio_login__user_agent'])); // phpcs:ignore WordPress.Security.NonceVerification
			}

			$blocked_user_data = array(
				'ip_address' => $ip,
				'country' => $country,
				'city' => $city,
				'time' => $current_time,
				'user_agent' => $user_agent,
			);

			Failed_Logins::log_blocked_user($blocked_user_data);
		}

		/**
		 * Check if user IP is blocked
		 *
		 * @param string $ip IP.
		 *
		 * @return array|false
		 */
		public static function is_ip_blocked($ip = '')
		{
			if (empty($ip)) {
				$ip = self::get_ip();
			}
			$blocked_ip = Failed_Logins::is_user_blocked($ip);

			if (\AIO_Login\Login_Controller\Login_Controller::get_instance()->is_enabled()) {
				return $blocked_ip;
			}

			return false;
		}

		/**
		 * Update user attempt count
		 *
		 * @param string $ip IP.
		 * @param bool   $clear Clear.
		 */
		public static function update_user_attempt_count($ip = '', $clear = false)
		{
			if (empty($ip)) {
				$ip = self::get_ip();
			}

			$attempts = get_transient('aio_login__user_attempts_' . $ip);
			if (false === $attempts) {
				$attempts = 0;
			}

			++$attempts;

			if ($clear) {
				$attempts = 0;
			}

			set_transient('aio_login__user_attempts_' . $ip, $attempts, 60 * 60);
		}

		/**
		 * Atomic increment for Limit Login Attempts (OTP / magic-link share this budget).
		 *
		 * @param string $ip IP.
		 * @return int New count.
		 */
		public static function increment_user_attempt_count_atomic($ip = '')
		{
			if (empty($ip)) {
				$ip = self::get_ip();
			}

			global $wpdb;
			$lock_name = 'aio_lla_' . md5($ip);
			$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 5)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			try {
				$attempts = (int) get_transient('aio_login__user_attempts_' . $ip);
				++$attempts;
				set_transient('aio_login__user_attempts_' . $ip, $attempts, 60 * 60);
				return $attempts;
			} finally {
				$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			}
		}

		/**
		 * Get user attempt count
		 *
		 * @param string $ip IP.
		 *
		 * @return int
		 */
		public static function get_user_attempt_count($ip = '')
		{
			if (empty($ip)) {
				$ip = self::get_ip();
			}

			$attempts = get_transient('aio_login__user_attempts_' . $ip);
			if (false === $attempts) {
				$attempts = 0;
			}

			return $attempts;
		}

		/**
		 * Get lockout attempts count
		 *
		 * @param int $timestamp Timestamp.
		 *
		 * @return int
		 */
		public static function get_timeout( $timestamp, $ip = '' ) {
			if ( empty( $ip ) ) {
				$ip = self::get_ip();
			}

			if ( class_exists( '\AIO_Login\Passwordless_Otp\OTP_Lockout' ) ) {
				$hash = md5( $ip );
				$keys = array(
					'aio_login_otp_verify_lockout_until_' . $hash,
					'aio_login_otp_lockout_until_' . $hash,
				);
				foreach ( $keys as $key ) {
					$otp_until = (int) get_transient( $key );
					if ( $otp_until > (int) $timestamp ) {
						return $otp_until;
					}
				}

				$row = Failed_Logins::is_user_blocked_raw( $ip );
				if ( is_array( $row ) && isset( $row['time'] ) && (int) $row['time'] === (int) $timestamp ) {
					$user_agent = isset( $row['user_agent'] ) ? (string) $row['user_agent'] : '';
					if ( str_starts_with( $user_agent, 'aio-login-otp:' ) && class_exists( '\AIO_Login\Passwordless_Otp\OTP_Settings' ) ) {
						$channel = str_ends_with( $user_agent, ':sms' ) ? 'sms' : 'email';
						$minutes = \AIO_Login\Passwordless_Otp\OTP_Settings::get_block_duration_minutes( $channel );
						return (int) $timestamp + ( (int) $minutes * MINUTE_IN_SECONDS );
					}
				}
			}

			$timeout = get_option( 'aio_login_limit_attempts_timeout', 0 );
			if ( empty( $timeout ) ) {
				$timeout = 5;
			}
			return (int) $timestamp + ( (int) $timeout * 60 );
		}

		/**
		 * Getting time stamps between two dates
		 *
		 * @param string $between Between it should be ( today, last_7_days, last_14_days, last_month ).
		 *
		 * @return string[]
		 */
		public static function get_timestamps($between)
		{
			$timestamps = array();
			$current_time = current_time('timestamp'); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

			switch ($between) {
				case 'last_7_days':
					$timestamps['start'] = strtotime('-7 days', $current_time);
					$timestamps['end'] = $current_time;
					break;
				case 'last_14_days':
					$timestamps['start'] = strtotime('-14 days', $current_time);
					$timestamps['end'] = $current_time;
					break;
				case 'last_month':
					$timestamps['start'] = strtotime('first day of last month', $current_time);
					$timestamps['end'] = strtotime('last day of last month', $current_time);
					break;
				case 'today':
				default:
					$timestamps['start'] = strtotime('today', $current_time);
					$timestamps['end'] = strtotime('tomorrow', $current_time) - 1;
					break;
			}

			return $timestamps;
		}

		/**
		 * Get logs
		 *
		 * @param string $type Type of attempts.
		 *
		 * @return array
		 */
		public static function get_logs($type)
		{
			if ('lockout' === $type) {
				return Failed_Logins::get_locked_ips();
			}

			return Failed_Logins::query_all_logs('failed', '', 'id', 'desc', 0);
		}

		/**
		 * Update the configured providers snapshot option.
		 * This provides a single source of truth for which providers are fully active and configured,
		 * allowing other settings (like WooCommerce integration) to load this data instantly without 
		 * recalculating or making multiple API calls.
		 *
		 * @return void
		 */
		public static function update_configured_providers_snapshot()
		{
			// Helper to get option with default
			$get_opt = function ($key, $default = '') {
				return get_option($key, $default);
			};

			$snapshot = array(
				'captcha' => array(),
				'social' => array(),
			);

			// 1. Check hCaptcha
			if (
				\AIO_Login\Captcha\Captcha_Validation::is_active_for_frontend( 'hcaptcha' )
			) {
				$snapshot['captcha'][] = 'hcaptcha';
			}

			// 2. Check reCAPTCHA
			if ( \AIO_Login\Captcha\Captcha_Validation::is_active_for_frontend( 'recaptcha' ) ) {
				$snapshot['captcha'][] = 'recaptcha';
			}

			// 3. Check Cloudflare Turnstile
			if (
				\AIO_Login\Captcha\Captcha_Validation::is_active_for_frontend( 'turnstile' )
			) {
				$snapshot['captcha'][] = 'turnstile';
			}

			// 4. Check Social Providers
			// Note: These options might be managed by the PRO plugin, but we can access the options table from here.
			$social_providers = array('google', 'microsoft', 'facebook', 'line', 'github', 'discord', 'apple');
			$plan_allows_social = true;
			if (class_exists('\AIO_Login_Pro\Plan\Plan_Features')) {
				$plan_allows_social = \AIO_Login_Pro\Plan\Plan_Features::can('social_login');
			}
			foreach ($social_providers as $provider) {
				if (!$plan_allows_social) {
					continue;
				}
				// Social Login Pro usually stores '1' for enabled.
				if ($get_opt('aio_' . $provider . '_enabled') !== '1') {
					continue;
				}

				if ('apple' === $provider) {
					// Apple is configured when Service ID, Key ID, Team ID and Private Key are present.
					if (
						!empty($get_opt('aio_apple_service_id')) &&
						!empty($get_opt('aio_apple_key_id')) &&
						!empty($get_opt('aio_apple_team_id')) &&
						!empty($get_opt('aio_apple_private_key')) &&
						$get_opt('aio_apple_verified', '0') === '1'
					) {
						$snapshot['social'][] = $provider;
					}
				} else {
					if (
						!empty($get_opt('aio_' . $provider . '_client_id')) &&
						!empty($get_opt('aio_' . $provider . '_client_secret'))
					) {
						$snapshot['social'][] = $provider;
					}
				}
			}

			update_option('aio_login_configured_providers_list', $snapshot);
		}

		public static function get_api_permission()
		{
			return is_user_logged_in() && current_user_can('manage_options');
		}
	}
}
