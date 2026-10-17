<?php
/**
 * Captcha credential validation and frontend activation gate.
 *
 * @package AIO_Login
 */

namespace AIO_Login\Captcha;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Captcha\\Captcha_Validation' ) ) {
	/**
	 * Captcha_Validation
	 */
	final class Captcha_Validation {

		/**
		 * @var array<string, string>
		 */
		private const VALIDATED_OPTIONS = array(
			'recaptcha' => 'aio_login_google_recaptcha_validated',
			'hcaptcha'  => 'aio_login_hcaptcha_validated',
			'turnstile' => 'aio_login_turnstile_validated',
		);

		/**
		 * @param string $provider recaptcha|hcaptcha|turnstile
		 * @return bool
		 */
		public static function is_validated( $provider ) {
			if ( ! isset( self::VALIDATED_OPTIONS[ $provider ] ) ) {
				return false;
			}

			return 'on' === get_option( self::VALIDATED_OPTIONS[ $provider ], 'off' );
		}

		/**
		 * @param string $provider recaptcha|hcaptcha|turnstile
		 * @param bool   $validated Validated state.
		 * @return void
		 */
		public static function set_validated( $provider, $validated ) {
			if ( ! isset( self::VALIDATED_OPTIONS[ $provider ] ) ) {
				return;
			}

			update_option( self::VALIDATED_OPTIONS[ $provider ], $validated ? 'on' : 'off' );
		}

		/**
		 * Whether captcha should run on the login frontend.
		 *
		 * @param string $provider recaptcha|hcaptcha|turnstile
		 * @return bool
		 */
		public static function is_active_for_frontend( $provider ) {
			if ( ! self::is_toggle_enabled( $provider ) ) {
				return false;
			}

			if ( ! self::has_stored_keys( $provider ) ) {
				return false;
			}

			return self::is_validated( $provider );
		}

		/**
		 * @param string $provider recaptcha|hcaptcha|turnstile
		 * @return bool
		 */
		public static function is_toggle_enabled( $provider ) {
			switch ( $provider ) {
				case 'recaptcha':
					return 'on' === get_option( 'aio_login_google_recaptcha_enable', 'off' );
				case 'hcaptcha':
					return 'on' === get_option( 'aio_login_hcaptcha_enable', 'off' );
				case 'turnstile':
					return 'on' === get_option( 'aio_login_turnstile_enable', 'off' );
			}

			return false;
		}

		/**
		 * @param string $provider recaptcha|hcaptcha|turnstile
		 * @return bool
		 */
		public static function has_stored_keys( $provider ) {
			switch ( $provider ) {
				case 'recaptcha':
					$version = get_option( 'aio_login_google_recaptcha_version', 'v2' );
					return '' !== (string) get_option( 'aio_login_google_recaptcha_' . $version . '_site_key', '' )
						&& '' !== (string) get_option( 'aio_login_google_recaptcha_' . $version . '_secret_key', '' );
				case 'hcaptcha':
					return '' !== (string) get_option( 'aio_login_hcaptcha_site_key', '' )
						&& '' !== (string) get_option( 'aio_login_hcaptcha_secret_key', '' );
				case 'turnstile':
					return '' !== (string) get_option( 'aio_login_turnstile_site_key', '' )
						&& '' !== (string) get_option( 'aio_login_turnstile_secret_key', '' );
			}

			return false;
		}

		/**
		 * Verify provider credentials against the remote API.
		 *
		 * @param string               $provider recaptcha|hcaptcha|turnstile
		 * @param array<string, mixed> $args     site_key, secret_key, response (optional), version (recaptcha).
		 * @return true|\WP_Error
		 */
		public static function verify_provider_keys( $provider, $args ) {
			$site_key   = isset( $args['site_key'] ) ? sanitize_text_field( (string) $args['site_key'] ) : '';
			$secret_key = isset( $args['secret_key'] ) ? sanitize_text_field( (string) $args['secret_key'] ) : '';
			$response   = self::sanitize_captcha_response( $args['response'] ?? '' );

			if ( '' === $site_key || '' === $secret_key ) {
				return new \WP_Error(
					'captcha_missing_keys',
					__( 'Site key and secret key are required.', 'change-wp-admin-login' )
				);
			}

			switch ( $provider ) {
				case 'recaptcha':
					return self::verify_recaptcha_keys( $secret_key, $response );
				case 'hcaptcha':
					return self::verify_hcaptcha_keys( $secret_key, $response );
				case 'turnstile':
					return self::verify_turnstile_keys( $secret_key, $site_key, $response );
			}

			return new \WP_Error(
				'captcha_invalid_provider',
				__( 'Unknown captcha provider.', 'change-wp-admin-login' )
			);
		}

		/**
		 * Preserve captcha token characters; do not run sanitize_text_field on JWT-like values.
		 *
		 * @param mixed $response Raw response token.
		 * @return string
		 */
		public static function sanitize_captcha_response_token( $response ) {
			return self::sanitize_captcha_response( $response );
		}

		/**
		 * @param mixed $response Raw response token.
		 * @return string
		 */
		private static function sanitize_captcha_response( $response ) {
			if ( ! is_scalar( $response ) ) {
				return '';
			}

			return trim( (string) wp_unslash( $response ) );
		}

		/**
		 * Verify reCAPTCHA credentials.
		 *
		 * @param string $secret_key Secret key.
		 * @param string $response   Optional client token.
		 * @return true|\WP_Error
		 */
		private static function verify_recaptcha_keys( $secret_key, $response = '' ) {
			if ( '' === $response ) {
				return self::verify_recaptcha_secret_only( $secret_key );
			}

			$remote = wp_remote_post(
				'https://www.google.com/recaptcha/api/siteverify',
				array(
					'timeout' => 15,
					'body'    => array(
						'secret'   => $secret_key,
						'response' => $response,
					),
				)
			);

			$result = self::interpret_siteverify_response( $remote, 'recaptcha' );
			if ( true === $result ) {
				return true;
			}

			return self::verify_recaptcha_secret_only( $secret_key );
		}

		/**
		 * Google accepts a valid secret key with no response token and returns success=true.
		 *
		 * @param string $secret_key Secret key.
		 * @return true|\WP_Error
		 */
		private static function verify_recaptcha_secret_only( $secret_key ) {
			$remote = wp_remote_post(
				'https://www.google.com/recaptcha/api/siteverify',
				array(
					'timeout' => 15,
					'body'    => array(
						'secret' => $secret_key,
					),
				)
			);

			return self::interpret_siteverify_response( $remote, 'recaptcha' );
		}

		/**
		 * hCaptcha requires a real client-generated response token to validate keys.
		 *
		 * @param string $secret_key Secret key.
		 * @param string $response   Client token.
		 * @return true|\WP_Error
		 */
		private static function verify_hcaptcha_keys( $secret_key, $response ) {
			if ( '' === $response ) {
				return new \WP_Error(
					'captcha_response_required',
					__( 'Complete the hCaptcha challenge to test the connection.', 'change-wp-admin-login' )
				);
			}

			$remote = wp_remote_post(
				'https://api.hcaptcha.com/siteverify',
				array(
					'timeout' => 15,
					'body'    => array(
						'secret'   => $secret_key,
						'response' => $response,
					),
				)
			);

			return self::interpret_siteverify_response( $remote, 'hcaptcha' );
		}

		/**
		 * Turnstile requires a real client-generated response token to validate keys.
		 *
		 * @param string $secret_key Secret key.
		 * @param string $site_key   Site key.
		 * @param string $response   Client token.
		 * @return true|\WP_Error
		 */
		private static function verify_turnstile_keys( $secret_key, $site_key, $response ) {
			if ( '' === $response ) {
				return new \WP_Error(
					'captcha_response_required',
					__( 'Complete the Turnstile challenge to test the connection.', 'change-wp-admin-login' )
				);
			}

			$remote = wp_remote_post(
				'https://challenges.cloudflare.com/turnstile/v0/siteverify',
				array(
					'timeout' => 15,
					'body'    => array(
						'secret'   => $secret_key,
						'response' => $response,
					),
				)
			);

			$result = self::interpret_siteverify_response( $remote, 'turnstile' );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( '' === $site_key ) {
				return new \WP_Error(
					'captcha_invalid_site_key',
					__( 'Site key is required.', 'change-wp-admin-login' )
				);
			}

			return true;
		}

		/**
		 * @param array|\WP_Error $remote   Remote response.
		 * @param string          $provider Provider slug.
		 * @return true|\WP_Error
		 */
		private static function interpret_siteverify_response( $remote, $provider ) {
			if ( is_wp_error( $remote ) ) {
				return new \WP_Error(
					'captcha_network',
					__( 'Unable to connect to the captcha provider. Please try again.', 'change-wp-admin-login' )
				);
			}

			$body = json_decode( wp_remote_retrieve_body( $remote ), true );
			if ( ! is_array( $body ) ) {
				return new \WP_Error(
					'captcha_invalid_response',
					__( 'Unexpected response from the captcha provider.', 'change-wp-admin-login' )
				);
			}

			if ( ! empty( $body['success'] ) ) {
				return true;
			}

			$error_codes = array();
			if ( ! empty( $body['error-codes'] ) && is_array( $body['error-codes'] ) ) {
				$error_codes = $body['error-codes'];
			}

			if ( in_array( 'invalid-input-secret', $error_codes, true ) || in_array( 'missing-input-secret', $error_codes, true ) ) {
				return new \WP_Error(
					'captcha_invalid_secret',
					__( 'Secret key is invalid. Please check your credentials.', 'change-wp-admin-login' )
				);
			}

			// Keys are valid but the current domain is not listed in the provider console (common on local/staging).
			if ( in_array( 'hostname-mismatch', $error_codes, true ) ) {
				return true;
			}

			if ( in_array( 'invalid-input-response', $error_codes, true ) ) {
				return new \WP_Error(
					'captcha_invalid_credentials',
					__( 'Captcha credentials are invalid. Please verify your site key and secret key.', 'change-wp-admin-login' )
				);
			}

			if ( in_array( 'missing-input-response', $error_codes, true ) ) {
				return new \WP_Error(
					'captcha_response_required',
					__( 'Complete the captcha challenge to test the connection.', 'change-wp-admin-login' )
				);
			}

			if ( in_array( 'timeout-or-duplicate', $error_codes, true ) ) {
				return new \WP_Error(
					'captcha_token_expired',
					__( 'Captcha token expired. Please test the connection again.', 'change-wp-admin-login' )
				);
			}

			return new \WP_Error(
				'captcha_verification_failed',
				__( 'Could not verify captcha credentials. Please check your keys and try again.', 'change-wp-admin-login' )
			);
		}
	}
}
