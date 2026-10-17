<?php
/**
 * Passwordless OTP option keys and defaults.
 *
 * @package AIO_Login
 */

namespace AIO_Login\Passwordless_Otp;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Passwordless_Otp\\OTP_Settings' ) ) {
	/**
	 * OTP_Settings
	 */
	final class OTP_Settings {

		public const NONCE_ACTION = 'aio-login-passwordless-otp';

		/**
		 * Whether SMS OTP is enabled in saved settings (ignores Pro availability).
		 *
		 * @return bool
		 */
		public static function is_sms_enabled_in_settings() {
			return 'on' === get_option( 'aio_login_otp_sms_enable', 'off' );
		}

		/**
		 * Persist SMS OTP as disabled in settings (admin explicitly turns it off).
		 */
		public static function disable_sms_channel() {
			update_option( 'aio_login_otp_sms_enable', 'off', false );
		}

		/**
		 * Default email body template.
		 *
		 * @return string
		 */
		public static function default_email_body() {
			return "Hello {{display_name}},\n\nYour One-Time Password (OTP) for secure login is:\n\n{{otp_code}}\n\nThis verification code will expire in {{expiration_time}} minutes.\n\nIf you did not request this code, please ignore this email. No changes will be made to your account.\n\nDevice Information:\n{{device_info}}\n\nFor security reasons, do not share this OTP with anyone.\n\nRegards,\n{{site_name}}\n{{site_url}}";
		}

		/**
		 * Set activation defaults.
		 */
		public static function set_defaults() {
			$defaults = array(
				'aio_login_otp_email_enable'          => 'off',
				'aio_login_otp_email_length'          => '6',
				'aio_login_otp_email_expiration'      => '10',
				'aio_login_otp_email_resend_timer'    => '60',
				'aio_login_otp_email_max_retries'     => '5',
				'aio_login_otp_sms_enable'            => 'off',
				'aio_login_otp_sms_length'            => '6',
				'aio_login_otp_sms_expiration'        => '10',
				'aio_login_otp_sms_resend_timer'      => '60',
				'aio_login_otp_sms_max_retries'       => '5',
				'aio_login_otp_twilio_account_sid'    => '',
				'aio_login_otp_twilio_auth_token'     => '',
				'aio_login_otp_twilio_sender_number'  => '',
				'aio_login_otp_sms_default_country'     => '+1',
				'aio_login_otp_sms_default_country_iso' => 'US',
				'aio_login_otp_sms_allowed_countries' => wp_json_encode( array( 'US', 'CA', 'GB', 'AU', 'IN' ) ),
				'aio_login_otp_email_block_duration'  => '15',
				'aio_login_otp_email_skip_2fa'        => 'on',
				'aio_login_otp_sms_block_duration'    => '15',
				'aio_login_otp_sms_skip_2fa'          => 'on',
			);

			Magic_Link_Settings::set_defaults();

			foreach ( $defaults as $key => $value ) {
				if ( false === get_option( $key, false ) ) {
					add_option( $key, $value, '', false );
				}
			}

			self::maybe_raise_default_otp_length();
		}

		/**
		 * One-time bump of shipped 4-digit OTP default to 6 (AIOL-690).
		 */
		public static function maybe_raise_default_otp_length() {
			if ( '1' === get_option( 'aio_login_otp_length_hardened_6', '' ) ) {
				return;
			}

			foreach ( array( 'aio_login_otp_email_length', 'aio_login_otp_sms_length' ) as $key ) {
				$current = (string) get_option( $key, '6' );
				if ( '4' === $current || '' === $current ) {
					update_option( $key, '6', false );
				}
			}

			update_option( 'aio_login_otp_length_hardened_6', '1', false );
		}

		/**
		 * @param string $channel email|sms.
		 * @return bool
		 */
		public static function is_channel_enabled( $channel ) {
			if ( 'email' === $channel ) {
				return 'on' === get_option( 'aio_login_otp_email_enable', 'off' );
			}
			if ( 'sms' === $channel ) {
				if ( ! \AIO_Login\AIO_Login::has_pro() ) {
					return false;
				}
				return 'on' === get_option( 'aio_login_otp_sms_enable', 'off' );
			}
			return false;
		}

		/**
		 * @param string $channel email|sms.
		 * @return int
		 */
		public static function get_otp_length( $channel ) {
			$key    = 'email' === $channel ? 'aio_login_otp_email_length' : 'aio_login_otp_sms_length';
			$length = absint( get_option( $key, 6 ) );
			if ( ! in_array( $length, array( 4, 6, 8 ), true ) ) {
				$length = 6;
			}
			return $length;
		}

		/**
		 * @param string $channel email|sms.
		 * @return int Minutes.
		 */
		public static function get_expiration_minutes( $channel ) {
			$key = 'email' === $channel ? 'aio_login_otp_email_expiration' : 'aio_login_otp_sms_expiration';
			return max( 1, min( 60, absint( get_option( $key, 10 ) ) ) );
		}

		/**
		 * @param string $channel email|sms.
		 * @return int Seconds.
		 */
		public static function get_resend_seconds( $channel ) {
			$key = 'email' === $channel ? 'aio_login_otp_email_resend_timer' : 'aio_login_otp_sms_resend_timer';
			return max( 30, min( 600, absint( get_option( $key, 60 ) ) ) );
		}

		/**
		 * @param string $channel email|sms.
		 * @return int
		 */
		public static function get_max_retries( $channel ) {
			$key = 'email' === $channel ? 'aio_login_otp_email_max_retries' : 'aio_login_otp_sms_max_retries';
			return max( 1, min( 20, absint( get_option( $key, 5 ) ) ) );
		}

		/**
		 * Max OTP send/resend requests per IP before lockout (15-minute window, not configurable in admin).
		 */
		public static function get_max_send_requests() {
			return 5;
		}

		/**
		 * Whether successful OTP login for this channel should bypass AIO Login 2FA.
		 *
		 * @param string $channel email|sms.
		 */
		public static function should_skip_two_factor( $channel ) {
			$channel = 'sms' === $channel ? 'sms' : 'email';
			$key     = 'email' === $channel ? 'aio_login_otp_email_skip_2fa' : 'aio_login_otp_sms_skip_2fa';
			return 'on' === get_option( $key, 'on' );
		}

		/**
		 * IP block duration after too many OTP attempts (minutes).
		 *
		 * @param string $channel email|sms.
		 */
		public static function get_block_duration_minutes( $channel = 'email' ) {
			$channel = 'sms' === $channel ? 'sms' : 'email';
			$key     = 'email' === $channel ? 'aio_login_otp_email_block_duration' : 'aio_login_otp_sms_block_duration';

			$legacy = absint( get_option( 'aio_login_otp_block_duration', 0 ) );
			$fallback = $legacy > 0 ? $legacy : 15;

			return max( 1, min( 1440, absint( get_option( $key, $fallback ) ) ) );
		}

		/**
		 * Admin settings payload for REST.
		 *
		 * @return array<string, mixed>
		 */
		public static function get_admin_settings() {
			$token_enc = (string) get_option( 'aio_login_otp_twilio_auth_token', '' );
			$has_token = '' !== $token_enc;

			$allowed = get_option( 'aio_login_otp_sms_allowed_countries', '[]' );
			if ( is_string( $allowed ) ) {
				$allowed = json_decode( $allowed, true );
			}
			if ( ! is_array( $allowed ) ) {
				$allowed = array();
			}

			return array(
				'nonce'                      => wp_create_nonce( self::NONCE_ACTION ),
				'email_enable'               => 'on' === get_option( 'aio_login_otp_email_enable', 'off' ),
				'email_block_duration'       => (string) self::get_block_duration_minutes( 'email' ),
				'email_length'               => (string) self::get_otp_length( 'email' ),
				'email_expiration'           => (string) self::get_expiration_minutes( 'email' ),
				'email_resend_timer'         => (string) self::get_resend_seconds( 'email' ),
				'email_max_retries'          => (string) self::get_max_retries( 'email' ),
				'email_skip_2fa'             => self::should_skip_two_factor( 'email' ),
				'sms_enable'                 => self::is_sms_enabled_in_settings(),
				'sms_active'                 => self::is_channel_enabled( 'sms' ),
				'sms_length'                 => (string) self::get_otp_length( 'sms' ),
				'sms_expiration'             => (string) self::get_expiration_minutes( 'sms' ),
				'sms_resend_timer'           => (string) self::get_resend_seconds( 'sms' ),
				'sms_max_retries'            => (string) self::get_max_retries( 'sms' ),
				'sms_block_duration'         => (string) self::get_block_duration_minutes( 'sms' ),
				'sms_skip_2fa'               => self::should_skip_two_factor( 'sms' ),
				'twilio_account_sid'         => (string) get_option( 'aio_login_otp_twilio_account_sid', '' ),
				'twilio_auth_token'          => $has_token ? OTP_Encryption::masked_placeholder( true ) : '',
				'twilio_auth_token_stored'   => $has_token,
				'twilio_sender_number'       => (string) get_option( 'aio_login_otp_twilio_sender_number', '' ),
				'sms_default_country'        => self::get_dial_code_for_country_iso( self::get_sms_default_country_iso() ),
				'sms_default_country_iso'    => self::get_sms_default_country_iso(),
				'sms_allowed_countries'      => array_values( array_map( 'sanitize_text_field', $allowed ) ),
				'has_pro'                    => \AIO_Login\AIO_Login::has_pro(),
				'country_codes'              => self::get_country_codes(),
			);
		}

		/**
		 * Common country dial codes for SMS UI.
		 *
		 * @return array<int, array{code:string,label:string,iso:string}>
		 */
		public static function get_country_codes() {
			return OTP_Country_Codes::all();
		}

		/**
		 * Sanitize allowed-country ISO list from option or request payload.
		 *
		 * @param mixed $raw Raw value.
		 * @return string[]
		 */
		public static function sanitize_sms_allowed_country_isos( $raw ) {
			if ( ! is_array( $raw ) ) {
				return array();
			}

			$isos = array();
			foreach ( $raw as $iso ) {
				$iso = strtoupper( sanitize_text_field( (string) $iso ) );
				if ( preg_match( '/^[A-Z]{2}$/', $iso ) ) {
					$isos[] = $iso;
				}
			}

			return array_values( array_unique( $isos ) );
		}

		/**
		 * Allowed SMS country ISO codes from settings.
		 *
		 * @return string[]
		 */
		public static function get_sms_allowed_country_isos() {
			$allowed = get_option( 'aio_login_otp_sms_allowed_countries', '[]' );
			if ( is_string( $allowed ) ) {
				$allowed = json_decode( $allowed, true );
			}

			return self::sanitize_sms_allowed_country_isos( $allowed );
		}

		/**
		 * Country list for wp-login SMS picker (filtered by allowed countries).
		 *
		 * @return array<int, array{code:string,label:string,iso:string}>
		 */
		public static function get_login_country_codes() {
			$all     = self::get_country_codes();
			$allowed = self::get_sms_allowed_country_isos();
			if ( empty( $allowed ) ) {
				return array();
			}

			$set = array_fill_keys( $allowed, true );
			$out = array();
			foreach ( $all as $country ) {
				if ( isset( $set[ $country['iso'] ] ) ) {
					$out[] = $country;
				}
			}

			return $out;
		}

		/**
		 * Default SMS country ISO (unique; dial codes like +1 are shared by multiple countries).
		 *
		 * @return string ISO 3166-1 alpha-2.
		 */
		public static function get_sms_default_country_iso() {
			$iso = strtoupper( (string) get_option( 'aio_login_otp_sms_default_country_iso', '' ) );

			if ( preg_match( '/^[A-Z]{2}$/', $iso ) ) {
				foreach ( self::get_country_codes() as $country ) {
					if ( $country['iso'] === $iso ) {
						return $iso;
					}
				}
			}

			return 'US';
		}

		/**
		 * @param string $iso ISO 3166-1 alpha-2.
		 * @return string Dial code e.g. +1.
		 */
		public static function get_dial_code_for_country_iso( $iso ) {
			$iso = strtoupper( sanitize_text_field( (string) $iso ) );
			foreach ( self::get_country_codes() as $country ) {
				if ( $country['iso'] === $iso ) {
					return $country['code'];
				}
			}

			return '+1';
		}

		/**
		 * Default dial code + ISO for login country select (must be in allowed list).
		 *
		 * @return array{code:string,iso:string}
		 */
		public static function get_login_sms_default_country() {
			$iso   = self::get_sms_default_country_iso();
			$codes = self::get_login_country_codes();

			foreach ( $codes as $country ) {
				if ( $country['iso'] === $iso ) {
					return array(
						'code' => $country['code'],
						'iso'  => $country['iso'],
					);
				}
			}

			if ( ! empty( $codes ) ) {
				return array(
					'code' => $codes[0]['code'],
					'iso'  => $codes[0]['iso'],
				);
			}

			return array(
				'code' => self::get_dial_code_for_country_iso( $iso ),
				'iso'  => $iso,
			);
		}

		/**
		 * Whether SMS passwordless login should appear on wp-login.
		 *
		 * @return bool
		 */
		public static function is_sms_login_available() {
			if ( ! \AIO_Login\AIO_Login::has_pro() || ! self::is_channel_enabled( 'sms' ) ) {
				return false;
			}

			return ! empty( self::get_sms_allowed_country_isos() );
		}
	}
}
