<?php
/**
 * OTP generation, session storage, verification.
 *
 * @package AIO_Login
 */

namespace AIO_Login\Passwordless_Otp;

use AIO_Login\Helper\Helper;
use AIO_Login\Login_Controller\Failed_Logins;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Passwordless_Otp\\OTP_Service' ) ) {
	/**
	 * OTP_Service
	 */
	final class OTP_Service {

		private const TRANSIENT_PREFIX = 'aio_login_otp_sess_';

		/**
		 * Normalize session token for storage and lookup (must match create_challenge).
		 *
		 * @param string $token Raw token.
		 * @return string
		 */
		public static function normalize_session_token( $token ) {
			$token = preg_replace( '/[^a-zA-Z0-9]/', '', (string) wp_unslash( $token ) );
			return strtolower( $token );
		}

		/**
		 * Keep OTP session alive long after the code itself expires so resend still works.
		 *
		 * @param string $channel email|sms.
		 * @return int Seconds.
		 */
		private static function get_session_ttl_seconds( $channel ) {
			$otp_minutes = OTP_Settings::get_expiration_minutes( $channel );
			return max( ( $otp_minutes + 20 ) * MINUTE_IN_SECONDS, 30 * MINUTE_IN_SECONDS );
		}

		/**
		 * Create OTP challenge and return session token.
		 *
		 * @param string $channel   email|sms.
		 * @param string $identifier Normalized email or E.164 phone.
		 * @param int    $user_id   WordPress user ID.
		 * @return array{token:string,otp:string,resend_in:int}|WP_Error
		 */
		public static function create_challenge( $channel, $identifier, $user_id ) {
			$channel = sanitize_key( $channel );
			if ( ! in_array( $channel, array( 'email', 'sms' ), true ) ) {
				return new \WP_Error( 'invalid_channel', __( 'Invalid authentication channel.', 'change-wp-admin-login' ) );
			}

			if ( ! OTP_Settings::is_channel_enabled( $channel ) ) {
				return new \WP_Error( 'channel_disabled', __( 'This login method is currently disabled.', 'change-wp-admin-login' ) );
			}

			if ( (int) $user_id <= 0 ) {
				return new \WP_Error(
					'not_registered',
					__( 'Account not found. Please register first.', 'change-wp-admin-login' )
				);
			}

			$ip = Helper::get_ip();

			if ( OTP_Lockout::is_ip_blocked( $ip, 'verify' ) ) {
				return new \WP_Error(
					'verify_blocked',
					OTP_Lockout::get_blocked_message( $ip, 'verify' ),
					array(
						'length' => OTP_Settings::get_otp_length( $channel ),
					)
				);
			}

			if ( OTP_Lockout::is_passwordless_verify_blocked( $ip, $identifier, $channel ) ) {
				return new \WP_Error(
					'verify_blocked',
					OTP_Lockout::get_passwordless_verify_blocked_message( $ip, $identifier, $channel ),
					array(
						'length' => OTP_Settings::get_otp_length( $channel ),
					)
				);
			}

			$send_check = OTP_Lockout::record_send_attempt( $ip, $channel );
			if ( is_wp_error( $send_check ) ) {
				return $send_check;
			}

			if ( 'email' === $channel ) {
				$identifier = strtolower( sanitize_email( (string) $identifier ) );
			}

			$id_send = OTP_Lockout::record_identifier_send( $identifier, $channel );
			if ( is_wp_error( $id_send ) ) {
				return $id_send;
			}

			$length = OTP_Settings::get_otp_length( $channel );
			$otp    = self::generate_numeric_otp( $length );
			$token  = self::normalize_session_token( wp_generate_password( 32, false, false ) );

			$session = array(
				'channel'    => $channel,
				'identifier' => $identifier,
				'user_id'    => (int) $user_id,
				'hash'       => wp_hash_password( $otp ),
				'expires'    => time() + ( OTP_Settings::get_expiration_minutes( $channel ) * MINUTE_IN_SECONDS ),
				'attempts'   => 0,
				'resend_at'  => time() + OTP_Settings::get_resend_seconds( $channel ),
			);

			set_transient( self::TRANSIENT_PREFIX . $token, $session, self::get_session_ttl_seconds( $channel ) );
			OTP_Lockout::adopt_active_session( $identifier, $channel, $token );

			return array(
				'token'     => $token,
				'otp'       => $otp,
				'resend_in' => OTP_Settings::get_resend_seconds( $channel ),
			);
		}

		/**
		 * Resend OTP for existing session if timer elapsed.
		 *
		 * @param string $token Session token.
		 * @return array{otp:string,resend_in:int}|WP_Error
		 */
		public static function resend_challenge( $token ) {
			$token = self::normalize_session_token( $token );
			$session = self::get_session( $token );
			if ( is_wp_error( $session ) ) {
				return $session;
			}

			$ip         = Helper::get_ip();
			$channel    = $session['channel'];
			$identifier = isset( $session['identifier'] ) ? (string) $session['identifier'] : '';

			if ( OTP_Lockout::is_ip_blocked( $ip, 'verify' ) ) {
				return new \WP_Error(
					'verify_blocked',
					OTP_Lockout::get_blocked_message( $ip, 'verify' ),
					array(
						'length' => OTP_Settings::get_otp_length( $channel ),
					)
				);
			}

			if ( OTP_Lockout::is_passwordless_verify_blocked( $ip, $identifier, $channel ) ) {
				return new \WP_Error(
					'verify_blocked',
					OTP_Lockout::get_passwordless_verify_blocked_message( $ip, $identifier, $channel ),
					array(
						'length' => OTP_Settings::get_otp_length( $channel ),
					)
				);
			}

			$send_check = OTP_Lockout::record_send_attempt( $ip, $channel );
			if ( is_wp_error( $send_check ) ) {
				return $send_check;
			}

			$id_send = OTP_Lockout::record_identifier_send( $identifier, $channel );
			if ( is_wp_error( $id_send ) ) {
				return $id_send;
			}

			$otp_expired = time() > (int) $session['expires'];

			if ( ! $otp_expired && time() < (int) $session['resend_at'] ) {
				return new \WP_Error(
					'resend_wait',
					__( 'Please wait before requesting a new code.', 'change-wp-admin-login' ),
					array( 'resend_in' => (int) $session['resend_at'] - time() )
				);
			}

			$length = OTP_Settings::get_otp_length( $channel );
			$otp    = self::generate_numeric_otp( $length );

			$session['hash']      = wp_hash_password( $otp );
			$session['expires']   = time() + ( OTP_Settings::get_expiration_minutes( $channel ) * MINUTE_IN_SECONDS );
			$session['resend_at'] = time() + OTP_Settings::get_resend_seconds( $channel );

			self::save_session( $token, $session );

			return array(
				'otp'       => $otp,
				'resend_in' => OTP_Settings::get_resend_seconds( $channel ),
			);
		}

		/**
		 * Verify OTP and destroy session on success.
		 *
		 * @param string $token Session token.
		 * @param string $otp   User-entered OTP.
		 * @return array{user_id:int,channel:string}|WP_Error
		 */
		public static function verify_challenge( $token, $otp ) {
			$token = self::normalize_session_token( $token );
			$ip    = Helper::get_ip();
			if ( OTP_Lockout::is_ip_blocked( $ip ) || Helper::is_ip_blocked( $ip ) ) {
				return new \WP_Error( 'ip_blocked', OTP_Lockout::get_blocked_message( $ip ) );
			}

			$session = self::get_session( $token );
			if ( is_wp_error( $session ) ) {
				return $session;
			}

			$channel    = isset( $session['channel'] ) ? sanitize_key( (string) $session['channel'] ) : 'email';
			$identifier = isset( $session['identifier'] ) ? (string) $session['identifier'] : '';
			if ( ! in_array( $channel, array( 'email', 'sms' ), true ) ) {
				$channel = 'email';
			}

			if ( OTP_Lockout::is_passwordless_verify_blocked( $ip, $identifier, $channel ) ) {
				return new \WP_Error(
					'verify_blocked',
					OTP_Lockout::get_passwordless_verify_blocked_message( $ip, $identifier, $channel )
				);
			}

			if ( time() > (int) $session['expires'] ) {
				self::save_session( $token, $session );

				return new \WP_Error(
					'otp_expired',
					__( 'Your OTP has expired. Please request a new verification code.', 'change-wp-admin-login' )
				);
			}

			$otp = preg_replace( '/\D/', '', (string) $otp );
			$max = OTP_Lockout::get_verify_attempt_limit( $channel );

			$result = OTP_Lockout::with_identifier_lock(
				$identifier,
				$channel,
				static function () use ( $token, $otp, $ip, $session, $channel, $identifier, $max ) {
					if ( OTP_Lockout::is_passwordless_verify_blocked( $ip, $identifier, $channel ) ) {
						return new \WP_Error(
							'verify_blocked',
							OTP_Lockout::get_passwordless_verify_blocked_message( $ip, $identifier, $channel )
						);
					}

					$fail_count = OTP_Lockout::get_identifier_failure_count( $identifier, $channel );
					if ( $fail_count >= $max ) {
						OTP_Lockout::block_ip( $identifier, $ip, $channel );
						self::destroy_session( $token );
						return new \WP_Error( 'max_attempts', OTP_Lockout::get_blocked_message( $ip ) );
					}

					if ( wp_check_password( $otp, $session['hash'] ) ) {
						self::destroy_session( $token );
						OTP_Lockout::clear_send_count( $ip );
						OTP_Lockout::clear_send_lockout( $ip );
						OTP_Lockout::clear_verify_lockout( $ip );
						OTP_Lockout::clear_account_lockout( (int) ( $session['user_id'] ?? 0 ), $identifier, $channel );
						OTP_Lockout::clear_active_session( $identifier, $channel, $token );
						Helper::update_user_attempt_count( $ip, true );

						$user_id = self::ensure_user_for_verified_session( $session );
						if ( is_wp_error( $user_id ) ) {
							return $user_id;
						}

						return array(
							'user_id' => (int) $user_id,
							'channel' => $channel,
						);
					}

					$fail_count = OTP_Lockout::increment_identifier_failure( $identifier, $channel );
					OTP_Service::log_failed_attempt_public( $identifier );
					OTP_Lockout::record_shared_password_limiter_failure( $ip );

					if ( $fail_count >= $max ) {
						self::destroy_session( $token );
						OTP_Lockout::clear_active_session( $identifier, $channel, $token );
						OTP_Lockout::block_ip( $identifier, $ip, $channel );
						return new \WP_Error( 'max_attempts', OTP_Lockout::get_blocked_message( $ip ) );
					}

					$remaining = max( 0, $max - $fail_count );

					return new \WP_Error(
						'invalid_otp',
						sprintf(
							/* translators: %s: remaining attempts phrase */
							__( 'Invalid OTP. Please enter the correct verification code. %s', 'change-wp-admin-login' ),
							sprintf(
								/* translators: %d: remaining verification attempts */
								_n(
									'You have %d attempt remaining.',
									'You have %d attempts remaining.',
									$remaining,
									'change-wp-admin-login'
								),
								$remaining
							)
						)
					);
				}
			);

			return $result;
		}

		/**
		 * Drop an OTP session token.
		 *
		 * @param string $token Session token.
		 */
		public static function destroy_session( $token ) {
			$token = self::normalize_session_token( $token );
			if ( strlen( $token ) < 20 ) {
				return;
			}

			delete_transient( self::TRANSIENT_PREFIX . $token );
		}

		/**
		 * After OTP is valid: return existing user_id or create account (deferred from send step).
		 *
		 * @param array<string, mixed> $session OTP session.
		 * @return int|\WP_Error
		 */
		public static function ensure_user_for_verified_session( $session ) {
			$user_id = isset( $session['user_id'] ) ? (int) $session['user_id'] : 0;
			if ( $user_id > 0 ) {
				return $user_id;
			}

			return new \WP_Error(
				'not_registered',
				__( 'Account not found. Please register first, then sign in with OTP.', 'change-wp-admin-login' )
			);
		}

		/**
		 * Whether the user has a phone number saved for SMS OTP login.
		 *
		 * @param int $user_id User ID.
		 * @return bool
		 */
		public static function user_has_phone( $user_id ) {
			if ( class_exists( 'AIO_Login\\Passwordless_Otp\\OTP_User_Phone' ) ) {
				return OTP_User_Phone::user_has_phone( $user_id );
			}

			$phone = (string) get_user_meta( (int) $user_id, 'aio_login_phone', true );
			return '' !== trim( $phone );
		}

		/**
		 * @param string $token Token.
		 * @return array<string, mixed>|\WP_Error
		 */
		public static function get_session( $token ) {
			$token = self::normalize_session_token( $token );
			if ( strlen( $token ) < 20 ) {
				return new \WP_Error( 'invalid_session', __( 'Your session has expired. Please start again.', 'change-wp-admin-login' ) );
			}

			$session = get_transient( self::TRANSIENT_PREFIX . $token );
			if ( ! is_array( $session ) ) {
				return new \WP_Error( 'invalid_session', __( 'Your session has expired. Please start again.', 'change-wp-admin-login' ) );
			}

			return $session;
		}

		/**
		 * @param string               $token   Token.
		 * @param array<string, mixed> $session Session.
		 */
		private static function save_session( $token, $session ) {
			$channel = $session['channel'];
			$token   = self::normalize_session_token( $token );
			set_transient(
				self::TRANSIENT_PREFIX . $token,
				$session,
				self::get_session_ttl_seconds( $channel )
			);
		}

		/**
		 * @param int $length Digits.
		 * @return string
		 */
		private static function generate_numeric_otp( $length ) {
			$length = max( 4, min( 8, (int) $length ) );
			$min    = (int) pow( 10, $length - 1 );
			$max    = (int) pow( 10, $length ) - 1;
			return (string) wp_rand( $min, $max );
		}

		/**
		 * @param string $identifier Email or phone for logs.
		 */
		/**
		 * Log failed OTP attempt to login_attempts table.
		 *
		 * @param string $identifier Email or phone.
		 */
		public static function log_failed_attempt_public( $identifier ) {
			self::log_failed_attempt( $identifier );
		}

		/**
		 * @param string $identifier Email or phone.
		 */
		private static function log_failed_attempt( $identifier ) {
			$location = Helper::get_location();
			Failed_Logins::insert_logs(
				array(
					'user_login' => sanitize_text_field( $identifier ),
					'ip_address' => Helper::get_ip(),
					'country'    => $location['country'] ?? '',
					'city'       => $location['city'] ?? '',
					'time'       => (string) current_time( 'timestamp' ), // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
					'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
					'status'     => 'failed',
				)
			);
		}

		/**
		 * Find existing user by email (does not create).
		 *
		 * @param string $email Email.
		 * @return int|\WP_Error User ID, or 0 if none.
		 */
		public static function find_user_id_by_email( $email ) {
			$email = sanitize_email( $email );
			if ( ! is_email( $email ) ) {
				return new \WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'change-wp-admin-login' ) );
			}

			$user = get_user_by( 'email', $email );
			if ( $user instanceof \WP_User ) {
				return (int) $user->ID;
			}

			return 0;
		}

		/**
		 * Create WP user for email (call only after OTP verified).
		 *
		 * @param string $email Email.
		 * @return int|\WP_Error
		 */
		public static function create_user_for_email( $email ) {
			$email = sanitize_email( $email );
			if ( ! is_email( $email ) ) {
				return new \WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'change-wp-admin-login' ) );
			}

			$existing = self::find_user_id_by_email( $email );
			if ( is_wp_error( $existing ) ) {
				return $existing;
			}
			if ( $existing > 0 ) {
				return $existing;
			}

			$username = sanitize_user( current( explode( '@', $email ) ), true );
			if ( username_exists( $username ) ) {
				$username = sanitize_user( $username . wp_rand( 100, 999 ), true );
			}

			$user_id = wp_create_user( $username, wp_generate_password( 24, true, true ), $email );
			if ( is_wp_error( $user_id ) ) {
				return new \WP_Error( 'user_create_failed', __( 'Unable to create account. Please contact the administrator.', 'change-wp-admin-login' ) );
			}

			$role    = apply_filters( 'aio_login_otp_new_user_role', 'subscriber', $email );
			$wp_user = new \WP_User( $user_id );
			$wp_user->set_role( $role );

			return (int) $user_id;
		}

		/**
		 * Find existing user by phone meta (does not create).
		 *
		 * @param string $country_code Country dial code e.g. +1.
		 * @param string $number       Local number.
		 * @return int|\WP_Error User ID, or 0 if none.
		 */
		public static function find_user_id_by_phone( $country_code, $number, $country_iso = '' ) {
			$phone = self::normalize_phone( $country_code, $number, $country_iso );
			if ( is_wp_error( $phone ) ) {
				return $phone;
			}

			$user_id = self::find_user_id_by_phone_e164( $phone );
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}

			return $user_id;
		}

		/**
		 * @param string $phone E.164 phone.
		 * @return int|\WP_Error
		 */
		public static function find_user_id_by_phone_e164( $phone ) {
			$users = get_users(
				array(
					'meta_key'   => 'aio_login_phone', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $phone, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'number'     => 1,
					'fields'     => 'ID',
				)
			);

			if ( ! empty( $users ) ) {
				return (int) $users[0];
			}

			return 0;
		}

		/**
		 * Create WP user for phone (call only after OTP verified).
		 *
		 * @param string $phone E.164 phone.
		 * @return int|\WP_Error
		 */
		public static function create_user_for_phone( $phone ) {
			$phone = (string) $phone;
			if ( '' === $phone ) {
				return new \WP_Error( 'invalid_phone', __( 'Please enter a valid phone number.', 'change-wp-admin-login' ) );
			}

			$existing = self::find_user_id_by_phone_e164( $phone );
			if ( $existing > 0 ) {
				return $existing;
			}

			$email    = 'sms_' . md5( $phone ) . '@otp.local';
			$username = 'sms_' . substr( md5( $phone ), 0, 12 );
			$user_id  = wp_create_user( $username, wp_generate_password( 24, true, true ), $email );
			if ( is_wp_error( $user_id ) ) {
				return new \WP_Error( 'user_create_failed', __( 'Unable to create account. Please contact the administrator.', 'change-wp-admin-login' ) );
			}

			update_user_meta( $user_id, 'aio_login_phone', $phone );

			$role    = apply_filters( 'aio_login_otp_new_user_role', 'subscriber', $phone );
			$wp_user = new \WP_User( $user_id );
			$wp_user->set_role( $role );

			return (int) $user_id;
		}

		/**
		 * Split stored E.164 into dial code, local number, and ISO (longest-prefix match).
		 *
		 * @param string $e164          E.164 phone e.g. +15551234567.
		 * @param string $preferred_iso Saved ISO when multiple countries share a dial code.
		 * @return array{code:string,local:string,iso:string}|null
		 */
		public static function split_e164( $e164, $preferred_iso = '' ) {
			$e164 = preg_replace( '/\s+/', '', (string) $e164 );
			if ( '' !== $e164 && '+' !== $e164[0] ) {
				$digits_only = preg_replace( '/\D+/', '', $e164 );
				$e164        = '' === $digits_only ? '' : '+' . $digits_only;
			}
			if ( ! preg_match( '/^\+(\d{4,15})$/', $e164, $matches ) ) {
				return null;
			}

			$digits        = $matches[1];
			$preferred_iso = strtoupper( sanitize_text_field( (string) $preferred_iso ) );
			$allowed       = OTP_Settings::get_sms_allowed_country_isos();

			if ( '' !== $preferred_iso ) {
				foreach ( OTP_Country_Codes::all() as $country ) {
					if ( $country['iso'] !== $preferred_iso ) {
						continue;
					}
					$prefix = ltrim( $country['code'], '+' );
					if ( '' === $prefix || strlen( $digits ) <= strlen( $prefix ) ) {
						continue;
					}
					if ( substr( $digits, 0, strlen( $prefix ) ) !== $prefix ) {
						continue;
					}
					$local = substr( $digits, strlen( $prefix ) );
					if ( '' === $local ) {
						continue;
					}
					return array(
						'code'  => '+' . $prefix,
						'local' => $local,
						'iso'   => $preferred_iso,
					);
				}
			}

			$prefixes      = array();
			$code_by_digit = array();

			foreach ( OTP_Country_Codes::all() as $country ) {
				$prefix = ltrim( $country['code'], '+' );
				if ( '' === $prefix ) {
					continue;
				}
				$prefixes[ $prefix ]           = true;
				$code_by_digit[ $prefix ][] = $country;
			}

			$prefix_keys = array_keys( $prefixes );
			usort(
				$prefix_keys,
				static function ( $a, $b ) {
					return strlen( $b ) - strlen( $a );
				}
			);

			foreach ( $prefix_keys as $prefix ) {
				if ( strlen( $digits ) <= strlen( $prefix ) ) {
					continue;
				}
				if ( substr( $digits, 0, strlen( $prefix ) ) !== $prefix ) {
					continue;
				}

				$local = substr( $digits, strlen( $prefix ) );
				if ( '' === $local ) {
					continue;
				}

				$code = '+' . $prefix;
				$iso  = self::resolve_iso_for_dial_code( $code, $preferred_iso, $code_by_digit[ $prefix ] ?? array(), $allowed );

				return array(
					'code'  => $code,
					'local' => $local,
					'iso'   => $iso,
				);
			}

			return null;
		}

		/**
		 * @param string                             $code      Dial code e.g. +1.
		 * @param string                             $preferred Preferred ISO.
		 * @param array<int, array{code:string,label:string,iso:string}> $candidates Countries for this dial code.
		 * @param string[]                           $allowed   Allowed ISO list for SMS.
		 * @return string
		 */
		private static function resolve_iso_for_dial_code( $code, $preferred, $candidates, $allowed ) {
			if ( '' !== $preferred ) {
				foreach ( $candidates as $country ) {
					if ( $country['iso'] === $preferred && $country['code'] === $code ) {
						return $preferred;
					}
				}
			}

			foreach ( $candidates as $country ) {
				if ( empty( $allowed ) || in_array( $country['iso'], $allowed, true ) ) {
					return $country['iso'];
				}
			}

			if ( ! empty( $candidates[0]['iso'] ) ) {
				return $candidates[0]['iso'];
			}

			return '';
		}

		/**
		 * @param string $country_code e.g. +1.
		 * @param string $number       Local number.
		 * @param string $country_iso  ISO 3166-1 alpha-2 when known.
		 * @param bool   $require_allowed When true, reject countries outside SMS allowed list (login flow).
		 * @return string|\WP_Error E.164.
		 */
		public static function normalize_phone( $country_code, $number = '', $country_iso = '', $require_allowed = true ) {
			if ( '' === $number ) {
				$number = $country_code;
				$country_code = '';
			}

			$digits = preg_replace( '/\D+/', '', (string) $number );
			$cc     = preg_replace( '/\D+/', '', (string) $country_code );

			if ( '' === $digits ) {
				return new \WP_Error( 'invalid_phone', __( 'Please enter a valid phone number.', 'change-wp-admin-login' ) );
			}

			$e164 = '+' . $cc . $digits;
			if ( strlen( $e164 ) < 8 || strlen( $e164 ) > 16 ) {
				return new \WP_Error( 'invalid_phone', __( 'Please enter a valid phone number.', 'change-wp-admin-login' ) );
			}

			if ( $require_allowed && ! self::is_country_allowed( $e164, $cc, $country_iso ) ) {
				return new \WP_Error( 'country_not_allowed', __( 'SMS login is not available for this country.', 'change-wp-admin-login' ) );
			}

			return $e164;
		}

		/**
		 * @param string $e164        Full number.
		 * @param string $cc          Country calling code digits.
		 * @param string $country_iso ISO 3166-1 alpha-2 when known (from login country picker).
		 * @return bool
		 */
		public static function is_country_allowed( $e164, $cc = '', $country_iso = '' ) {
			$allowed = OTP_Settings::get_sms_allowed_country_isos();
			if ( empty( $allowed ) ) {
				return false;
			}

			$country_iso = strtoupper( sanitize_text_field( (string) $country_iso ) );
			if ( '' !== $country_iso ) {
				return in_array( $country_iso, $allowed, true );
			}

			$map = OTP_Country_Codes::calling_code_map();
			$iso_guess = array();
			if ( '' !== $cc && isset( $map[ $cc ] ) ) {
				$iso_guess = $map[ $cc ];
			}

			if ( empty( $iso_guess ) ) {
				return false;
			}

			foreach ( $iso_guess as $iso ) {
				if ( in_array( $iso, $allowed, true ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Device info string for emails.
		 *
		 * @return string
		 */
		public static function get_device_info() {
			$ip       = Helper::get_ip();
			$location = Helper::get_location( $ip );
			$ua       = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

			return sprintf(
				"IP: %s\nLocation: %s, %s\nBrowser: %s",
				$ip,
				$location['city'] ?? '',
				$location['country'] ?? '',
				$ua
			);
		}
	}
}
