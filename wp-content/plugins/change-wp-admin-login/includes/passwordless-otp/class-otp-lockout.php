<?php
/**
 * OTP-specific IP lockouts (Activity Log → Lockouts).
 *
 * @package AIO_Login
 */

namespace AIO_Login\Passwordless_Otp;

use AIO_Login\Helper\Helper;
use AIO_Login\Login_Controller\Failed_Logins;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Passwordless_Otp\\OTP_Lockout' ) ) {
	/**
	 * OTP_Lockout
	 */
	final class OTP_Lockout {

		private const SEND_COUNT_PREFIX          = 'aio_login_otp_send_count_';
		private const VERIFY_LOCKOUT_PREFIX      = 'aio_login_otp_verify_lockout_until_';
		private const SEND_LOCKOUT_PREFIX        = 'aio_login_otp_send_lockout_until_';
		private const LOCKOUT_CHANNEL_PREFIX     = 'aio_login_otp_lockout_channel_';
		private const IDENTIFIER_LOCKOUT_PREFIX  = 'aio_login_otp_verify_lockout_id_';
		private const IDENTIFIER_FAIL_PREFIX     = 'aio_login_otp_fail_id_';
		private const IDENTIFIER_SEND_PREFIX     = 'aio_login_otp_send_id_';
		private const ACTIVE_SESSION_PREFIX      = 'aio_login_otp_active_tok_';

		/** Stored in login_lockouts.user_agent to distinguish OTP verify blocks from password login lockouts. */
		private const LOCKOUT_ACTIVITY_PREFIX = 'aio-login-otp:';

		/** @deprecated Legacy key — treated as verify lockout for backward compatibility. */
		private const LEGACY_LOCKOUT_PREFIX = 'aio_login_otp_lockout_until_';

		/**
		 * Whether this IP is blocked for OTP operations.
		 *
		 * @param string $ip      IP.
		 * @param string $context send|verify.
		 * @return array<string, mixed>|false
		 */
		public static function is_ip_blocked( $ip = '', $context = 'verify' ) {
			if ( empty( $ip ) ) {
				$ip = Helper::get_ip();
			}

			if ( 'send' === $context ) {
				return self::get_active_send_lockout( $ip );
			}

			$verify_lockout = self::get_active_verify_lockout( $ip );
			if ( false !== $verify_lockout ) {
				return $verify_lockout;
			}

			$row = Failed_Logins::is_user_blocked_raw( $ip );
			if ( ! is_array( $row ) ) {
				return false;
			}

			$until = self::get_row_lockout_until( $row, $ip );
			if ( $until <= current_time( 'timestamp' ) ) { // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
				return false;
			}

			return array(
				'ip_address' => $ip,
				'time'       => (int) $row['time'],
				'until'      => $until,
			);
		}

		/**
		 * Block IP after too many failed OTP verifications.
		 *
		 * @param string $identifier Email or phone.
		 * @param string $ip         IP.
		 * @param string $channel    email|sms.
		 */
		public static function block_ip( $identifier = '', $ip = '', $channel = 'email' ) {
			self::apply_verify_lockout( $identifier, $ip, $channel );
		}

		/**
		 * Whether verify is blocked for this email/phone (cross-method passwordless lockout).
		 *
		 * @param string $identifier Email or E.164 phone.
		 * @param string $channel    email|sms.
		 * @return bool
		 */
		public static function is_identifier_blocked( $identifier, $channel = 'email' ) {
			$identifier = self::normalize_lockout_identifier( $identifier, $channel );
			if ( '' === $identifier ) {
				return false;
			}

			self::sweep_expired_identifier_lockout_state( $identifier, $channel );

			return self::get_identifier_lockout_until( $identifier, $channel ) > current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		}

		/**
		 * Whether OTP verify or login link should be denied for this request.
		 *
		 * @param string $ip         IP.
		 * @param string $identifier Email or phone (optional).
		 * @param string $channel    email|sms.
		 * @return bool
		 */
		public static function is_passwordless_verify_blocked( $ip = '', $identifier = '', $channel = 'email' ) {
			if ( empty( $ip ) ) {
				$ip = Helper::get_ip();
			}

			if ( false !== self::is_ip_blocked( $ip, 'verify' ) ) {
				return true;
			}

			if ( Helper::is_ip_blocked( $ip ) ) {
				return true;
			}

			return self::is_identifier_blocked( $identifier, $channel );
		}

		/**
		 * User-facing verify-block message for OTP and login link flows.
		 *
		 * @param string $ip         IP.
		 * @param string $identifier Email or phone (optional).
		 * @param string $channel    email|sms.
		 * @return string
		 */
		public static function get_passwordless_verify_blocked_message( $ip = '', $identifier = '', $channel = 'email' ) {
			if ( empty( $ip ) ) {
				$ip = Helper::get_ip();
			}

			if ( false !== self::is_ip_blocked( $ip, 'verify' ) ) {
				return self::get_blocked_message( $ip, 'verify' );
			}

			if ( Helper::is_ip_blocked( $ip ) ) {
				return self::get_blocked_message( $ip, 'verify' );
			}

			$remaining = self::get_identifier_remaining_block_minutes( $identifier, $channel );
			if ( $remaining <= 0 ) {
				return __( 'Too many incorrect attempts. Please try again after some time.', 'change-wp-admin-login' );
			}

			return sprintf(
				/* translators: %d: minutes until unblock */
				__( 'Too many incorrect attempts. Please try again after %d minutes.', 'change-wp-admin-login' ),
				$remaining
			);
		}

		/**
		 * Clear identifier verify lockout after a successful passwordless login.
		 *
		 * @param string $identifier Email or phone.
		 * @param string $channel    email|sms.
		 */
		public static function clear_identifier_lockout( $identifier, $channel = 'email' ) {
			$identifier = self::normalize_lockout_identifier( $identifier, $channel );
			if ( '' === $identifier ) {
				return;
			}

			delete_transient( self::identifier_lockout_transient_key( $identifier, $channel ) );
			self::clear_identifier_failures( $identifier, $channel );
		}

		/**
		 * Clear account-level passwordless verify lockouts.
		 *
		 * @param int    $user_id    Unused; kept for callers.
		 * @param string $identifier Email or phone.
		 * @param string $channel    email|sms.
		 */
		public static function clear_account_lockout( $user_id, $identifier, $channel = 'email' ) {
			unset( $user_id );
			self::clear_identifier_lockout( $identifier, $channel );
		}

		/**
		 * Track OTP send; block when over limit.
		 *
		 * @param string $ip      IP.
		 * @param string $channel email|sms.
		 * @return true|\WP_Error
		 */
		public static function record_send_attempt( $ip = '', $channel = 'email' ) {
			if ( empty( $ip ) ) {
				$ip = Helper::get_ip();
			}

			$key   = self::SEND_COUNT_PREFIX . md5( $ip );
			$count = (int) get_transient( $key );

			// Recover from stale send lockouts left by older plugin versions.
			if ( false !== self::get_active_send_lockout( $ip ) && $count <= 0 ) {
				self::clear_send_lockout( $ip );
			}

			if ( self::is_ip_blocked( $ip, 'send' ) ) {
				return new \WP_Error( 'ip_blocked', self::get_blocked_message( $ip, 'send' ) );
			}
			$max   = max( 1, OTP_Settings::get_max_send_requests() );

			if ( $count >= $max ) {
				self::apply_send_lockout( $ip, $channel );
				return new \WP_Error( 'ip_blocked', self::get_blocked_message( $ip, 'send' ) );
			}

			set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );

			return true;
		}

		/**
		 * Limit Login Attempts uses the same budget for password + passwordless auth.
		 *
		 * @return bool
		 */
		public static function is_limit_login_attempts_enabled() {
			return 'on' === get_option( 'aio_login_limit_attempts_enable', 'off' );
		}

		/**
		 * Shared verify attempt budget (LLA max when enabled, else OTP retries).
		 *
		 * @param string $channel email|sms.
		 * @return int
		 */
		public static function get_verify_attempt_limit( $channel = 'email' ) {
			if ( self::is_limit_login_attempts_enabled() ) {
				$max = absint( get_option( 'aio_login_limit_attempts_maximum_attempts', 5 ) );
				return max( 1, $max );
			}

			return OTP_Settings::get_max_retries( $channel );
		}

		/**
		 * Shared verify lockout duration (LLA timeout when enabled).
		 *
		 * @param string $channel email|sms.
		 * @return int Minutes.
		 */
		public static function get_verify_block_minutes( $channel = 'email' ) {
			if ( self::is_limit_login_attempts_enabled() ) {
				$minutes = absint( get_option( 'aio_login_limit_attempts_timeout', 5 ) );
				return max( 1, $minutes > 0 ? $minutes : 5 );
			}

			return OTP_Settings::get_block_duration_minutes( $channel );
		}

		/**
		 * Per-identifier send/resend cap (stops minting extra live codes for one account).
		 *
		 * @param string $identifier Email or phone.
		 * @param string $channel    email|sms.
		 * @return true|\WP_Error
		 */
		public static function record_identifier_send( $identifier, $channel = 'email' ) {
			$identifier = self::normalize_lockout_identifier( $identifier, $channel );
			if ( '' === $identifier ) {
				return true;
			}

			$key   = self::identifier_send_transient_key( $identifier, $channel );
			$max   = max( 1, OTP_Settings::get_max_send_requests() );
			$count = (int) get_transient( $key );

			if ( $count >= $max ) {
				return new \WP_Error(
					'ip_blocked',
					__( 'Too many verification code requests. Please try again after some time.', 'change-wp-admin-login' )
				);
			}

			set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );

			return true;
		}

		/**
		 * Keep a single live OTP session per identifier; drop the previous token.
		 *
		 * @param string $identifier Email or phone.
		 * @param string $channel    email|sms.
		 * @param string $token      New session token.
		 */
		public static function adopt_active_session( $identifier, $channel, $token ) {
			$identifier = self::normalize_lockout_identifier( $identifier, $channel );
			$token      = OTP_Service::normalize_session_token( $token );
			if ( '' === $identifier || strlen( $token ) < 20 ) {
				return;
			}

			$key = self::active_session_transient_key( $identifier, $channel );
			$old = (string) get_transient( $key );
			if ( '' !== $old && $old !== $token ) {
				OTP_Service::destroy_session( $old );
			}

			set_transient( $key, $token, 30 * MINUTE_IN_SECONDS );
		}

		/**
		 * @param string $identifier Email or phone.
		 * @param string $channel    email|sms.
		 * @param string $token      Session token that succeeded or was discarded.
		 */
		public static function clear_active_session( $identifier, $channel, $token = '' ) {
			$identifier = self::normalize_lockout_identifier( $identifier, $channel );
			if ( '' === $identifier ) {
				return;
			}

			$key = self::active_session_transient_key( $identifier, $channel );
			if ( '' !== $token ) {
				$current = (string) get_transient( $key );
				if ( $current !== OTP_Service::normalize_session_token( $token ) ) {
					return;
				}
			}

			delete_transient( $key );
		}

		/**
		 * Increment identifier failures. Call inside with_identifier_lock() for verify.
		 *
		 * @param string $identifier Email or phone.
		 * @param string $channel    email|sms.
		 * @return int New count.
		 */
		public static function increment_identifier_failure( $identifier, $channel = 'email' ) {
			$identifier = self::normalize_lockout_identifier( $identifier, $channel );
			if ( '' === $identifier ) {
				return 0;
			}

			self::sweep_expired_identifier_lockout_state( $identifier, $channel );

			$key   = self::identifier_fail_transient_key( $identifier, $channel );
			$count = (int) get_transient( $key );
			++$count;
			// Keep the in-progress attempt budget at least as long as the verify block window.
			$ttl = max( 15, self::get_verify_block_minutes( $channel ) ) * MINUTE_IN_SECONDS;
			set_transient( $key, $count, $ttl );

			return $count;
		}

		/**
		 * Locked increment for callers that do not already hold the identifier lock.
		 *
		 * @param string $identifier Email or phone.
		 * @param string $channel    email|sms.
		 * @return int|\WP_Error
		 */
		public static function increment_identifier_failure_atomic( $identifier, $channel = 'email' ) {
			return self::with_identifier_lock(
				$identifier,
				$channel,
				static function () use ( $identifier, $channel ) {
					return self::increment_identifier_failure( $identifier, $channel );
				}
			);
		}

		/**
		 * @param string $identifier Email or phone.
		 * @param string $channel    email|sms.
		 * @return int
		 */
		public static function get_identifier_failure_count( $identifier, $channel = 'email' ) {
			$identifier = self::normalize_lockout_identifier( $identifier, $channel );
			if ( '' === $identifier ) {
				return 0;
			}

			self::sweep_expired_identifier_lockout_state( $identifier, $channel );

			return (int) get_transient( self::identifier_fail_transient_key( $identifier, $channel ) );
		}

		/**
		 * @param string $identifier Email or phone.
		 * @param string $channel    email|sms.
		 */
		public static function clear_identifier_failures( $identifier, $channel = 'email' ) {
			$identifier = self::normalize_lockout_identifier( $identifier, $channel );
			if ( '' === $identifier ) {
				return;
			}

			delete_transient( self::identifier_fail_transient_key( $identifier, $channel ) );
		}

		/**
		 * Serialize verify/guess work per account so attempt counters cannot race.
		 *
		 * @param string   $identifier Email or phone.
		 * @param string   $channel    email|sms.
		 * @param callable $callback   Runs while the MySQL lock is held.
		 * @return mixed|\WP_Error
		 */
		public static function with_identifier_lock( $identifier, $channel, $callback ) {
			$identifier = self::normalize_lockout_identifier( $identifier, $channel );
			if ( '' === $identifier || ! is_callable( $callback ) ) {
				return is_callable( $callback ) ? $callback() : new \WP_Error( 'invalid_lock', __( 'Please try again.', 'change-wp-admin-login' ) );
			}

			global $wpdb;

			$lock_name = 'aio_otp_' . md5( $channel . '|' . $identifier );
			$got       = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, 10 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( '1' !== (string) $got ) {
				return new \WP_Error( 'lock_busy', __( 'Please try again.', 'change-wp-admin-login' ) );
			}

			try {
				return $callback();
			} finally {
				$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			}
		}

		/**
		 * Count OTP/magic-link failures toward Limit Login Attempts (IP budget).
		 *
		 * @param string $ip IP.
		 * @return int New IP attempt count, or 0 when LLA is off.
		 */
		public static function record_shared_password_limiter_failure( $ip = '' ) {
			if ( ! self::is_limit_login_attempts_enabled() ) {
				return 0;
			}

			if ( empty( $ip ) ) {
				$ip = Helper::get_ip();
			}

			Helper::increment_user_attempt_count_atomic( $ip );
			$count = Helper::get_user_attempt_count( $ip );
			$max   = self::get_verify_attempt_limit( 'email' );

			if ( $count >= $max && ! Helper::is_ip_blocked( $ip ) ) {
				Helper::block_ip( $ip );
				Helper::update_user_attempt_count( $ip, true );
			}

			return $count;
		}

		/**
		 * New verification code = fresh verify attempts (does not reset send-rate counter).
		 *
		 * @param string $ip IP.
		 */
		public static function clear_verify_lockout( $ip = '' ) {
			if ( empty( $ip ) ) {
				$ip = Helper::get_ip();
			}
			delete_transient( self::verify_lockout_transient_key( $ip ) );
			delete_transient( self::legacy_lockout_transient_key( $ip ) );
			delete_transient( self::lockout_channel_transient_key( $ip ) );
			self::clear_verify_lockout_activity( $ip );
		}

		/**
		 * Reset send-rate counter after a successful OTP login.
		 *
		 * @param string $ip IP.
		 */
		public static function clear_send_count( $ip = '' ) {
			if ( empty( $ip ) ) {
				$ip = Helper::get_ip();
			}
			delete_transient( self::SEND_COUNT_PREFIX . md5( $ip ) );
		}

		/**
		 * @param string $ip IP.
		 */
		public static function clear_send_lockout( $ip = '' ) {
			if ( empty( $ip ) ) {
				$ip = Helper::get_ip();
			}
			delete_transient( self::send_lockout_transient_key( $ip ) );
		}

		/**
		 * User-facing message with remaining minutes.
		 *
		 * @param string $ip      IP.
		 * @param string $context send|verify.
		 * @return string
		 */
		public static function get_blocked_message( $ip = '', $context = 'verify' ) {
			if ( empty( $ip ) ) {
				$ip = Helper::get_ip();
			}

			$remaining = self::get_remaining_block_minutes( $ip, $context );
			if ( $remaining <= 0 ) {
				if ( 'send' === $context ) {
					return __( 'Too many verification code requests. Please try again after some time.', 'change-wp-admin-login' );
				}
				return __( 'Too many incorrect attempts. Please try again after some time.', 'change-wp-admin-login' );
			}

			if ( 'send' === $context ) {
				return sprintf(
					/* translators: %d: minutes until unblock */
					__( 'Too many verification code requests. Please try again after %d minutes.', 'change-wp-admin-login' ),
					$remaining
				);
			}

			return sprintf(
				/* translators: %d: minutes until unblock */
				__( 'Too many incorrect attempts. Please try again after %d minutes.', 'change-wp-admin-login' ),
				$remaining
			);
		}

		/**
		 * @param string $ip      IP.
		 * @param string $context send|verify.
		 * @return int
		 */
		public static function get_remaining_block_minutes( $ip = '', $context = 'verify' ) {
			if ( empty( $ip ) ) {
				$ip = Helper::get_ip();
			}

			$until = self::get_lockout_until_timestamp( $ip, $context );
			if ( $until <= 0 ) {
				return 0;
			}

			$diff = $until - current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			if ( $diff <= 0 ) {
				return 0;
			}

			return max( 1, (int) ceil( $diff / MINUTE_IN_SECONDS ) );
		}

		/**
		 * @param string $identifier Identifier.
		 * @param string $ip         IP.
		 * @param string $channel    email|sms.
		 */
		private static function apply_verify_lockout( $identifier, $ip, $channel ) {
			if ( empty( $ip ) ) {
				$ip = Helper::get_ip();
			}

			$channel = 'sms' === $channel ? 'sms' : 'email';
			$minutes = self::get_verify_block_minutes( $channel );
			$until   = current_time( 'timestamp' ) + ( $minutes * MINUTE_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

			set_transient( self::verify_lockout_transient_key( $ip ), $until, $minutes * MINUTE_IN_SECONDS );
			set_transient( self::legacy_lockout_transient_key( $ip ), $until, $minutes * MINUTE_IN_SECONDS );
			set_transient( self::lockout_channel_transient_key( $ip ), $channel, $minutes * MINUTE_IN_SECONDS );

			if ( '' !== $identifier ) {
				OTP_Service::log_failed_attempt_public( $identifier );
				self::apply_identifier_lockout( $identifier, $channel );
			}
			self::log_verify_lockout_activity( $ip, $channel );
		}

		/**
		 * @param string $identifier Email or phone.
		 * @param string $channel    email|sms.
		 */
		private static function apply_identifier_lockout( $identifier, $channel ) {
			$identifier = self::normalize_lockout_identifier( $identifier, $channel );
			if ( '' === $identifier ) {
				return;
			}

			$channel = 'sms' === $channel ? 'sms' : 'email';
			$minutes = self::get_verify_block_minutes( $channel );
			$until   = current_time( 'timestamp' ) + ( $minutes * MINUTE_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

			set_transient(
				self::identifier_lockout_transient_key( $identifier, $channel ),
				$until,
				$minutes * MINUTE_IN_SECONDS
			);

			// Attempt budget must start fresh after this block expires (AIOL-694).
			// Failures outlived short block windows (TTL was max(15, block minutes)).
			self::clear_identifier_failures( $identifier, $channel );
		}

		/**
		 * Drop leftover OTP failure budget once a verify lockout is no longer active.
		 *
		 * Without this, max failures from the previous block cycle remain and the next
		 * wrong (or even correct) OTP immediately re-applies the lockout.
		 *
		 * @param string $identifier Email or phone.
		 * @param string $channel    email|sms.
		 */
		private static function sweep_expired_identifier_lockout_state( $identifier, $channel ) {
			$identifier = self::normalize_lockout_identifier( $identifier, $channel );
			if ( '' === $identifier ) {
				return;
			}

			$channel     = 'sms' === $channel ? 'sms' : 'email';
			$lockout_key = self::identifier_lockout_transient_key( $identifier, $channel );
			$until       = (int) get_transient( $lockout_key );
			$now         = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

			if ( $until > $now ) {
				return;
			}

			if ( $until > 0 ) {
				delete_transient( $lockout_key );
				self::clear_identifier_failures( $identifier, $channel );
				return;
			}

			// Lockout transient already gone, but failure counter may still be at/above max.
			$max   = self::get_verify_attempt_limit( $channel );
			$fails = (int) get_transient( self::identifier_fail_transient_key( $identifier, $channel ) );
			if ( $fails >= $max ) {
				self::clear_identifier_failures( $identifier, $channel );
			}
		}

		/**
		 * @param string $identifier Email or phone.
		 * @param string $channel    email|sms.
		 * @return string
		 */
		private static function normalize_lockout_identifier( $identifier, $channel ) {
			$channel = 'sms' === $channel ? 'sms' : 'email';
			if ( 'sms' === $channel ) {
				$digits = preg_replace( '/\D+/', '', (string) $identifier );
				return strlen( $digits ) >= 8 ? $digits : '';
			}

			return strtolower( sanitize_email( (string) $identifier ) );
		}

		/**
		 * @param string $identifier Email or phone.
		 * @param string $channel    email|sms.
		 * @return int Unix timestamp when lockout ends, or 0.
		 */
		private static function get_identifier_lockout_until( $identifier, $channel ) {
			$identifier = self::normalize_lockout_identifier( $identifier, $channel );
			if ( '' === $identifier ) {
				return 0;
			}

			return (int) get_transient( self::identifier_lockout_transient_key( $identifier, $channel ) );
		}

		/**
		 * @param string $identifier Email or phone.
		 * @param string $channel    email|sms.
		 * @return int
		 */
		private static function get_identifier_remaining_block_minutes( $identifier, $channel ) {
			$until = self::get_identifier_lockout_until( $identifier, $channel );
			if ( $until <= 0 ) {
				return 0;
			}

			$diff = $until - current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			if ( $diff <= 0 ) {
				return 0;
			}

			return max( 1, (int) ceil( $diff / MINUTE_IN_SECONDS ) );
		}

		/**
		 * @param string $ip      IP.
		 * @param string $channel email|sms.
		 */
		private static function apply_send_lockout( $ip, $channel ) {
			if ( empty( $ip ) ) {
				$ip = Helper::get_ip();
			}

			$channel = 'sms' === $channel ? 'sms' : 'email';
			// Match the 15-minute send-rate window, not the verify block duration setting.
			$minutes = 15;
			$until   = current_time( 'timestamp' ) + ( $minutes * MINUTE_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

			set_transient( self::send_lockout_transient_key( $ip ), $until, $minutes * MINUTE_IN_SECONDS );
		}

		/**
		 * @param string $ip IP.
		 * @return array<string, mixed>|false
		 */
		private static function get_active_send_lockout( $ip ) {
			$until = (int) get_transient( self::send_lockout_transient_key( $ip ) );
			if ( $until <= 0 || $until <= current_time( 'timestamp' ) ) { // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
				return false;
			}

			return array(
				'ip_address' => $ip,
				'time'       => $until - ( 15 * MINUTE_IN_SECONDS ),
				'until'      => $until,
			);
		}

		/**
		 * @param string $ip IP.
		 * @return array<string, mixed>|false
		 */
		private static function get_active_verify_lockout( $ip ) {
			$until = self::get_verify_lockout_until( $ip );
			if ( $until <= 0 ) {
				return false;
			}

			$channel = self::get_lockout_channel( $ip );
			return array(
				'ip_address' => $ip,
				'time'       => $until - ( self::get_verify_block_minutes( $channel ) * MINUTE_IN_SECONDS ),
				'until'      => $until,
			);
		}

		/**
		 * @param string $ip IP.
		 * @return int
		 */
		private static function get_verify_lockout_until( $ip ) {
			$candidates = array(
				(int) get_transient( self::verify_lockout_transient_key( $ip ) ),
				(int) get_transient( self::legacy_lockout_transient_key( $ip ) ),
			);

			$now   = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			$until = 0;
			foreach ( $candidates as $candidate ) {
				if ( $candidate > $until && $candidate > $now ) {
					$until = $candidate;
				}
			}

			return $until;
		}

		/**
		 * @param string $ip      IP.
		 * @param string $context send|verify.
		 * @return int
		 */
		private static function get_lockout_until_timestamp( $ip, $context = 'verify' ) {
			if ( 'send' === $context ) {
				$until = (int) get_transient( self::send_lockout_transient_key( $ip ) );
				if ( $until > current_time( 'timestamp' ) ) { // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
					return $until;
				}
				return 0;
			}

			$until = self::get_verify_lockout_until( $ip );
			if ( $until > 0 ) {
				return $until;
			}

			$row = Failed_Logins::is_user_blocked_raw( $ip );
			if ( ! is_array( $row ) || ! isset( $row['time'] ) ) {
				return 0;
			}

			return self::get_row_lockout_until( $row, $ip );
		}

		/**
		 * Lockout expiry for a login_lockouts row (OTP verify vs password login).
		 *
		 * @param array<string, mixed> $row Lockout row.
		 * @param string               $ip  IP.
		 * @return int Unix timestamp, or 0 when not blocked.
		 */
		public static function get_row_lockout_until( $row, $ip = '' ) {
			if ( empty( $ip ) ) {
				$ip = Helper::get_ip();
			}

			if ( ! is_array( $row ) || ! isset( $row['time'] ) ) {
				return 0;
			}

			$timestamp = (int) $row['time'];
			if ( self::is_otp_lockout_row( $row ) ) {
				$channel = self::get_channel_from_otp_lockout_row( $row );
				$timeout = $timestamp + ( self::get_verify_block_minutes( $channel ) * MINUTE_IN_SECONDS );
			} else {
				$minutes = (int) get_option( 'aio_login_limit_attempts_timeout', 0 );
				if ( $minutes <= 0 ) {
					$minutes = 5;
				}
				$timeout = $timestamp + ( $minutes * MINUTE_IN_SECONDS );
			}

			if ( $timeout <= current_time( 'timestamp' ) ) { // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
				return 0;
			}

			return $timeout;
		}

		/**
		 * @param string $ip      IP.
		 * @param string $channel email|sms.
		 */
		private static function log_verify_lockout_activity( $ip, $channel ) {
			$location = Helper::get_location( $ip );
			Failed_Logins::log_blocked_user(
				array(
					'ip_address' => $ip,
					'country'    => $location['country'] ?? 'Unknown',
					'city'       => $location['city'] ?? 'Unknown',
					'time'       => current_time( 'timestamp' ), // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
					'user_agent' => self::LOCKOUT_ACTIVITY_PREFIX . ( 'sms' === $channel ? 'sms' : 'email' ),
				)
			);
		}

		/**
		 * @param string $ip IP.
		 */
		private static function clear_verify_lockout_activity( $ip ) {
			global $wpdb;

			$table_name = $wpdb->prefix . 'aio_login_login_lockouts';
			$like       = $wpdb->esc_like( self::LOCKOUT_ACTIVITY_PREFIX ) . '%';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					'DELETE FROM %i WHERE `ip_address` = %s AND `user_agent` LIKE %s',
					$table_name,
					$ip,
					$like
				)
			);
		}

		/**
		 * @param array<string, mixed> $row Lockout row.
		 * @return bool
		 */
		private static function is_otp_lockout_row( $row ) {
			$user_agent = isset( $row['user_agent'] ) ? (string) $row['user_agent'] : '';
			return str_starts_with( $user_agent, self::LOCKOUT_ACTIVITY_PREFIX );
		}

		/**
		 * @param array<string, mixed> $row Lockout row.
		 * @return string email|sms
		 */
		private static function get_channel_from_otp_lockout_row( $row ) {
			$user_agent = isset( $row['user_agent'] ) ? (string) $row['user_agent'] : '';
			return str_ends_with( $user_agent, ':sms' ) ? 'sms' : 'email';
		}

		/**
		 * @param string $ip IP.
		 * @return string email|sms
		 */
		private static function get_lockout_channel( $ip ) {
			$channel = get_transient( self::lockout_channel_transient_key( $ip ) );
			return 'sms' === $channel ? 'sms' : 'email';
		}

		/**
		 * @param string $ip IP.
		 * @return string
		 */
		private static function verify_lockout_transient_key( $ip ) {
			return self::VERIFY_LOCKOUT_PREFIX . md5( $ip );
		}

		/**
		 * @param string $ip IP.
		 * @return string
		 */
		private static function send_lockout_transient_key( $ip ) {
			return self::SEND_LOCKOUT_PREFIX . md5( $ip );
		}

		/**
		 * @param string $ip IP.
		 * @return string
		 */
		private static function legacy_lockout_transient_key( $ip ) {
			return self::LEGACY_LOCKOUT_PREFIX . md5( $ip );
		}

		/**
		 * @param string $ip IP.
		 * @return string
		 */
		private static function lockout_channel_transient_key( $ip ) {
			return self::LOCKOUT_CHANNEL_PREFIX . md5( $ip );
		}

		/**
		 * @param string $identifier Normalized email or phone digits.
		 * @param string $channel    email|sms.
		 * @return string
		 */
		private static function identifier_lockout_transient_key( $identifier, $channel ) {
			$channel = 'sms' === $channel ? 'sms' : 'email';
			return self::IDENTIFIER_LOCKOUT_PREFIX . $channel . '_' . md5( $identifier );
		}

		/**
		 * @param string $identifier Normalized identifier.
		 * @param string $channel    email|sms.
		 * @return string
		 */
		private static function identifier_fail_transient_key( $identifier, $channel ) {
			$channel = 'sms' === $channel ? 'sms' : 'email';
			return self::IDENTIFIER_FAIL_PREFIX . $channel . '_' . md5( $identifier );
		}

		/**
		 * @param string $identifier Normalized identifier.
		 * @param string $channel    email|sms.
		 * @return string
		 */
		private static function identifier_send_transient_key( $identifier, $channel ) {
			$channel = 'sms' === $channel ? 'sms' : 'email';
			return self::IDENTIFIER_SEND_PREFIX . $channel . '_' . md5( $identifier );
		}

		/**
		 * @param string $identifier Normalized identifier.
		 * @param string $channel    email|sms.
		 * @return string
		 */
		private static function active_session_transient_key( $identifier, $channel ) {
			$channel = 'sms' === $channel ? 'sms' : 'email';
			return self::ACTIVE_SESSION_PREFIX . $channel . '_' . md5( $identifier );
		}
	}
}
