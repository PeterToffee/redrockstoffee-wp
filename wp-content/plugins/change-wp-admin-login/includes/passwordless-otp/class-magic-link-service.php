<?php
/**
 * Login link token generation, storage, and verification.
 *
 * @package AIO_Login
 */

namespace AIO_Login\Passwordless_Otp;

use AIO_Login\Helper\Helper;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Passwordless_Otp\\Magic_Link_Service' ) ) {
	/**
	 * Magic_Link_Service
	 */
	final class Magic_Link_Service {

		private const TRANSIENT_PREFIX     = 'aio_login_ml_sess_';
		private const USER_REQUEST_PREFIX  = 'aio_login_ml_user_req_';
		private const IP_SEND_PREFIX       = 'aio_login_ml_ip_send_';

		/**
		 * Create a one-time login link for a registered user.
		 *
		 * @param string $email        Email address.
		 * @param int    $user_id      User ID.
		 * @param string $redirect_to  Optional post-login redirect (same-site, validated by caller).
		 * @return array{token:string,secret:string,url:string}|WP_Error
		 */
		public static function create_link( $email, $user_id, $redirect_to = '' ) {
			if ( ! Magic_Link_Settings::is_login_available() ) {
				return new \WP_Error( 'disabled', __( 'Login link sign-in is currently disabled.', 'change-wp-admin-login' ) );
			}

			$user_id = (int) $user_id;
			if ( $user_id <= 0 ) {
				return new \WP_Error( 'not_registered', __( 'You do not have an account yet. Please register first or contact the site administrator.', 'change-wp-admin-login' ) );
			}

			$ip    = Helper::get_ip();
			$email = strtolower( sanitize_email( $email ) );

			if ( OTP_Lockout::is_passwordless_verify_blocked( $ip, $email, 'email' ) ) {
				return new \WP_Error(
					'verify_blocked',
					OTP_Lockout::get_passwordless_verify_blocked_message( $ip, $email, 'email' )
				);
			}

			// Atomically reserve a user/IP request slot before minting a link (AIOL-692).
			$rate = self::reserve_request_slots( $user_id, $ip );
			if ( is_wp_error( $rate ) ) {
				return $rate;
			}

			$token  = wp_generate_password( 32, false, false );
			$secret = wp_generate_password( 32, false, false );

			$ttl     = Magic_Link_Settings::get_validity_minutes() * MINUTE_IN_SECONDS;
			$expires = time() + $ttl;

			$validated_redirect = self::sanitize_redirect_to( $redirect_to );

			$session = array(
				'user_id'      => $user_id,
				'email'        => $email,
				'secret_hash'  => wp_hash_password( $secret ),
				'secret_enc'   => OTP_Encryption::encrypt( $secret ),
				'expires'      => $expires,
				'used'         => false,
				'ip'           => $ip,
				'created'      => time(),
				'redirect_to'  => $validated_redirect,
			);

			set_transient( self::transient_key( $token ), $session, $ttl + 120 );

			return array(
				'token'  => $token,
				'secret' => $secret,
				'url'    => self::build_login_url( $token, $secret, $validated_redirect ),
			);
		}

		/**
		 * @param string $token  Public token from URL.
		 * @param string $secret Secret key from URL.
		 * @return array{user_id:int,redirect_to:string}|\WP_Error
		 */
		public static function consume_link( $token, $secret ) {
			// Preserve case — sanitize_key() lowercases and breaks transient lookup.
			$token  = self::sanitize_link_credential( (string) $token );
			$secret = self::sanitize_link_credential( (string) $secret );

			if ( strlen( $token ) < 16 || strlen( $secret ) < 16 ) {
				return new \WP_Error( 'invalid_link', self::get_error_message( 'invalid_link' ) );
			}

			$session = get_transient( self::transient_key( $token ) );
			if ( ! is_array( $session ) ) {
				return new \WP_Error( 'session_expired', self::get_error_message( 'session_expired' ) );
			}

			$session_email = isset( $session['email'] ) ? strtolower( sanitize_email( (string) $session['email'] ) ) : '';
			if ( OTP_Lockout::is_passwordless_verify_blocked( Helper::get_ip(), $session_email, 'email' ) ) {
				return new \WP_Error(
					'verify_blocked',
					OTP_Lockout::get_passwordless_verify_blocked_message( Helper::get_ip(), $session_email, 'email' )
				);
			}

			if ( ! empty( $session['used'] ) ) {
				return new \WP_Error( 'already_used', self::get_error_message( 'already_used' ) );
			}

			if ( time() > (int) $session['expires'] ) {
				delete_transient( self::transient_key( $token ) );
				return new \WP_Error( 'expired', self::get_error_message( 'expired' ) );
			}

			if ( ! self::secret_matches_session( $secret, $session ) ) {
				$fail = OTP_Lockout::increment_identifier_failure_atomic( $session_email, 'email' );
				OTP_Lockout::record_shared_password_limiter_failure( Helper::get_ip() );
				$max  = OTP_Lockout::get_verify_attempt_limit( 'email' );
				if ( ! is_wp_error( $fail ) && (int) $fail >= $max ) {
					OTP_Lockout::block_ip( $session_email, Helper::get_ip(), 'email' );
					return new \WP_Error(
						'verify_blocked',
						OTP_Lockout::get_passwordless_verify_blocked_message( Helper::get_ip(), $session_email, 'email' )
					);
				}
				return new \WP_Error( 'invalid_link', self::get_error_message( 'invalid_link' ) );
			}

			$session['used'] = true;
			$remaining_ttl   = max( 60, (int) $session['expires'] - time() );
			set_transient( self::transient_key( $token ), $session, $remaining_ttl );
			OTP_Lockout::clear_account_lockout( (int) ( $session['user_id'] ?? 0 ), $session_email, 'email' );
			OTP_Lockout::clear_verify_lockout( Helper::get_ip() );
			Helper::update_user_attempt_count( Helper::get_ip(), true );

			$user_id = isset( $session['user_id'] ) ? (int) $session['user_id'] : 0;
			if ( $user_id <= 0 || ! get_user_by( 'id', $user_id ) ) {
				return new \WP_Error( 'invalid_link', self::get_error_message( 'invalid_link' ) );
			}

			$redirect_to = isset( $session['redirect_to'] ) ? (string) $session['redirect_to'] : '';

			return array(
				'user_id'     => $user_id,
				'redirect_to' => self::sanitize_redirect_to( $redirect_to ),
			);
		}

		/**
		 * Allow reopening an already-used link when the same user is still logged in.
		 *
		 * @param string $token  Public token from URL.
		 * @param string $secret Secret key from URL.
		 * @return array{user_id:int,redirect_to:string}|false
		 */
		public static function resolve_for_logged_in_user( $token, $secret ) {
			if ( ! is_user_logged_in() ) {
				return false;
			}

			$token  = self::sanitize_link_credential( (string) $token );
			$secret = self::sanitize_link_credential( (string) $secret );

			if ( strlen( $token ) < 16 || strlen( $secret ) < 16 ) {
				return false;
			}

			$session = get_transient( self::transient_key( $token ) );
			if ( ! is_array( $session ) || empty( $session['used'] ) ) {
				return false;
			}

			if ( ! self::secret_matches_session( $secret, $session ) ) {
				return false;
			}

			$user_id = isset( $session['user_id'] ) ? (int) $session['user_id'] : 0;
			if ( $user_id <= 0 || $user_id !== get_current_user_id() ) {
				return false;
			}

			if ( ! get_user_by( 'id', $user_id ) ) {
				return false;
			}

			$redirect_to = isset( $session['redirect_to'] ) ? (string) $session['redirect_to'] : '';

			return array(
				'user_id'     => $user_id,
				'redirect_to' => self::sanitize_redirect_to( $redirect_to ),
			);
		}

		/**
		 * @param string               $secret  Secret from URL.
		 * @param array<string, mixed> $session Stored session.
		 * @return bool
		 */
		private static function secret_matches_session( $secret, $session ) {
			if ( ! empty( $session['secret_hash'] ) && wp_check_password( $secret, $session['secret_hash'] ) ) {
				return true;
			}

			if ( ! empty( $session['secret_enc'] ) ) {
				$decrypted = OTP_Encryption::decrypt( $session['secret_enc'] );
				if ( is_string( $decrypted ) && hash_equals( $decrypted, $secret ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * @param string $token  Token.
		 * @param string $secret Secret.
		 * @return string
		 */
		public static function build_login_url( $token, $secret, $redirect_to = '' ) {
			$args = array(
				'action' => 'aio_login_magic_link',
				't'      => $token,
				'k'      => $secret,
			);

			$redirect_to = self::sanitize_redirect_to( $redirect_to );
			if ( '' !== $redirect_to ) {
				$args['redirect_to'] = $redirect_to;
			}

			return add_query_arg( $args, wp_login_url() );
		}

		/**
		 * Validate a same-site redirect URL (checkout and other allowed front-end URLs).
		 *
		 * @param string $redirect_to Raw redirect URL.
		 * @return string Sanitized URL or empty string.
		 */
		public static function sanitize_redirect_to( $redirect_to ) {
			$redirect_to = wp_validate_redirect( (string) $redirect_to, '' );
			if ( '' === $redirect_to ) {
				return '';
			}

			$home = wp_parse_url( home_url( '/' ) );
			$dest = wp_parse_url( $redirect_to );

			if ( empty( $home['host'] ) || empty( $dest['host'] ) || ! hash_equals( (string) $home['host'], (string) $dest['host'] ) ) {
				return '';
			}

			if ( function_exists( 'wc_get_checkout_url' ) ) {
				$checkout = untrailingslashit( wc_get_checkout_url() );
				$target   = untrailingslashit( $redirect_to );
				if ( $checkout === $target ) {
					return $redirect_to;
				}
			}

			return '';
		}

		/**
		 * Strip unsafe characters while keeping token case intact.
		 *
		 * @param string $value Raw query value.
		 * @return string
		 */
		private static function sanitize_link_credential( $value ) {
			return preg_replace( '/[^a-zA-Z0-9]/', '', $value );
		}

		/**
		 * @param string $code Error code.
		 * @return string
		 */
		public static function get_error_message( $code ) {
			switch ( $code ) {
				case 'already_used':
					return __( 'This login link has already been used. Please request a new login link.', 'change-wp-admin-login' );
				case 'expired':
					return __( 'This login link has expired. Please request a new login link.', 'change-wp-admin-login' );
				case 'rate_limited':
					$max = Magic_Link_Settings::get_max_requests();
					return sprintf(
						/* translators: %d: maximum login link requests allowed within the validity window */
						__( 'You have reached the maximum of %d login link requests. Please wait until the validity window resets before requesting another link.', 'change-wp-admin-login' ),
						$max
					);
				case 'verify_blocked':
					return OTP_Lockout::get_passwordless_verify_blocked_message( Helper::get_ip(), '', 'email' );
				case 'session_expired':
					return __( 'This login link is no longer valid. Please request a new login link.', 'change-wp-admin-login' );
				default:
					return __( 'Invalid login link detected. Please request a new magic link.', 'change-wp-admin-login' );
			}
		}

		/**
		 * Atomically enforce per-user and per-IP login-link request limits.
		 *
		 * @param int    $user_id User ID.
		 * @param string $ip      IP address.
		 * @return true|\WP_Error
		 */
		private static function reserve_request_slots( $user_id, $ip ) {
			$max_user = Magic_Link_Settings::get_max_requests();
			$window   = max( MINUTE_IN_SECONDS, Magic_Link_Settings::get_validity_minutes() * MINUTE_IN_SECONDS );

			$user_result = self::with_rate_limit_lock(
				'user_' . (int) $user_id,
				function () use ( $user_id, $max_user, $window ) {
					return self::reserve_windowed_slot(
						self::USER_REQUEST_PREFIX . (int) $user_id,
						$max_user,
						$window
					);
				}
			);

			if ( is_wp_error( $user_result ) ) {
				return $user_result;
			}

			$ip_max = max( $max_user, 10 );
			$ip_key = self::IP_SEND_PREFIX . md5( (string) $ip );

			$ip_result = self::with_rate_limit_lock(
				'ip_' . md5( (string) $ip ),
				function () use ( $ip_key, $ip_max, $window ) {
					return self::reserve_windowed_slot( $ip_key, $ip_max, $window );
				}
			);

			if ( is_wp_error( $ip_result ) ) {
				return $ip_result;
			}

			return true;
		}

		/**
		 * Keep request timestamps inside the validity window and reserve one slot.
		 *
		 * @param string $transient_key Transient key.
		 * @param int    $max           Max requests in window.
		 * @param int    $window        Window length in seconds.
		 * @return true|\WP_Error
		 */
		private static function reserve_windowed_slot( $transient_key, $max, $window ) {
			$max    = max( 1, (int) $max );
			$window = max( MINUTE_IN_SECONDS, (int) $window );
			$now    = time();
			$stored = get_transient( $transient_key );
			$times  = array();

			if ( is_array( $stored ) && isset( $stored['times'] ) && is_array( $stored['times'] ) ) {
				$times = $stored['times'];
			} elseif ( is_numeric( $stored ) && (int) $stored > 0 ) {
				// Legacy counter → treat as N requests already used in this window.
				$legacy = min( $max, (int) $stored );
				for ( $i = 0; $i < $legacy; $i++ ) {
					$times[] = $now;
				}
			}

			$times = array_values(
				array_filter(
					$times,
					static function ( $timestamp ) use ( $now, $window ) {
						return ( $now - (int) $timestamp ) < $window;
					}
				)
			);

			if ( count( $times ) >= $max ) {
				return new \WP_Error( 'rate_limited', self::get_error_message( 'rate_limited' ) );
			}

			$times[] = $now;
			set_transient(
				$transient_key,
				array(
					'times' => $times,
				),
				$window
			);

			return true;
		}

		/**
		 * Serialize rate-limit updates so concurrent requests cannot skip the max.
		 *
		 * @param string   $lock_suffix Unique suffix for this lock.
		 * @param callable $callback    Callback executed while locked.
		 * @return mixed|\WP_Error
		 */
		private static function with_rate_limit_lock( $lock_suffix, $callback ) {
			if ( ! is_callable( $callback ) ) {
				return new \WP_Error( 'rate_limited', self::get_error_message( 'rate_limited' ) );
			}

			global $wpdb;

			$lock_name = 'aio_ml_rl_' . md5( (string) $lock_suffix );
			$got       = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, 5 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( '1' !== (string) $got ) {
				return new \WP_Error( 'rate_limited', self::get_error_message( 'rate_limited' ) );
			}

			try {
				return $callback();
			} finally {
				$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			}
		}

		/**
		 * @param string $token Token.
		 * @return string
		 */
		private static function transient_key( $token ) {
			return self::TRANSIENT_PREFIX . hash_hmac( 'sha256', (string) $token, wp_salt( 'aio_login_magic_link' ) );
		}

		/**
		 * Human-readable validity for emails.
		 *
		 * @return string
		 */
		public static function get_validity_label() {
			$value = max( 1, absint( get_option( 'aio_login_magic_link_validity_value', 10 ) ) );
			$unit  = Magic_Link_Settings::sanitize_validity_unit( get_option( 'aio_login_magic_link_validity_unit', 'minutes' ) );

			switch ( $unit ) {
				case 'hours':
					/* translators: %d: number of hours */
					return sprintf( _n( '%d hour', '%d hours', $value, 'change-wp-admin-login' ), $value );
				case 'days':
					/* translators: %d: number of days */
					return sprintf( _n( '%d day', '%d days', $value, 'change-wp-admin-login' ), $value );
				default:
					/* translators: %d: number of minutes */
					return sprintf( _n( '%d minute', '%d minutes', $value, 'change-wp-admin-login' ), $value );
			}
		}
	}
}
