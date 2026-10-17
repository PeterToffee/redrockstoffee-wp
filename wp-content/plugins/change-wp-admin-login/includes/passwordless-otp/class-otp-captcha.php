<?php
/**
 * Reuse active captcha providers for passwordless OTP requests.
 *
 * @package AIO_Login
 */

namespace AIO_Login\Passwordless_Otp;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Passwordless_Otp\\OTP_Captcha' ) ) {
	/**
	 * OTP_Captcha
	 */
	final class OTP_Captcha {

		/**
		 * Whether any captcha is enabled site-wide.
		 *
		 * @return bool
		 */
		public static function is_required() {
			return self::get_active_provider() !== '';
		}

		/**
		 * @return string recaptcha|hcaptcha|turnstile|''
		 */
		public static function get_active_provider() {
			if ( class_exists( '\AIO_Login\Captcha\Captcha_Validation' ) && \AIO_Login\Captcha\Captcha_Validation::is_active_for_frontend( 'recaptcha' ) ) {
				return 'recaptcha';
			}
			if ( class_exists( '\AIO_Login\Captcha\Captcha_Validation' ) && class_exists( '\AIO_Login_Pro\HCaptcha\HCaptcha' ) && \AIO_Login\Captcha\Captcha_Validation::is_active_for_frontend( 'hcaptcha' ) ) {
				return 'hcaptcha';
			}
			if ( class_exists( '\AIO_Login\Captcha\Captcha_Validation' ) && class_exists( '\AIO_Login_Pro\Turnstile\Turnstile' ) && \AIO_Login\Captcha\Captcha_Validation::is_active_for_frontend( 'turnstile' ) ) {
				return 'turnstile';
			}
			return '';
		}

		/**
		 * Config for login script (site keys only).
		 *
		 * @return array<string, mixed>
		 */
		public static function get_frontend_config() {
			$provider = self::get_active_provider();
			$config   = array(
				'provider' => $provider,
			);

			if ( 'recaptcha' === $provider ) {
				$version            = get_option( 'aio_login_google_recaptcha_version', 'v2' );
				$config['version']  = $version;
				$config['site_key'] = (string) get_option( 'aio_login_google_recaptcha_' . $version . '_site_key', '' );
				$config['theme']    = (string) get_option( 'aio_login_google_recaptcha_v2_theme', 'light' );
			} elseif ( 'hcaptcha' === $provider ) {
				$config['site_key'] = (string) get_option( 'aio_login_hcaptcha_site_key', '' );
				$config['theme']    = (string) get_option( 'aio_login_hcaptcha_theme', 'light' );
				$config['size']     = (string) get_option( 'aio_login_hcaptcha_size', 'normal' );
			} elseif ( 'turnstile' === $provider ) {
				$config['site_key'] = (string) get_option( 'aio_login_turnstile_site_key', '' );
				$config['theme']    = (string) get_option( 'aio_login_turnstile_theme', 'auto' );
			}

			return $config;
		}

		/**
		 * Verify captcha token from POST.
		 *
		 * @return true|\WP_Error
		 */
		public static function verify_request() {
			$provider = self::get_active_provider();
			if ( '' === $provider ) {
				return true;
			}

			if ( 'recaptcha' === $provider ) {
				return self::verify_recaptcha();
			}
			if ( 'hcaptcha' === $provider ) {
				return self::verify_hcaptcha();
			}
			if ( 'turnstile' === $provider ) {
				return self::verify_turnstile();
			}

			return true;
		}

		/**
		 * @return true|\WP_Error
		 */
		private static function verify_recaptcha() {
			$response = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( '' === $response ) {
				return new \WP_Error( 'captcha_required', __( 'Please verify that you are not a robot.', 'change-wp-admin-login' ) );
			}

			$version    = get_option( 'aio_login_google_recaptcha_version', 'v2' );
			$secret_key = (string) get_option( 'aio_login_google_recaptcha_' . $version . '_secret_key', '' );
			if ( '' === $secret_key ) {
				return new \WP_Error( 'captcha_misconfigured', __( 'Captcha is not configured correctly.', 'change-wp-admin-login' ) );
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

			if ( is_wp_error( $remote ) ) {
				return new \WP_Error( 'captcha_network', __( 'Unable to verify captcha. Please try again.', 'change-wp-admin-login' ) );
			}

			$body = json_decode( wp_remote_retrieve_body( $remote ), true );
			if ( empty( $body['success'] ) ) {
				return new \WP_Error( 'captcha_invalid', __( 'Please verify that you are not a robot.', 'change-wp-admin-login' ) );
			}

			if ( 'v3' === $version ) {
				$threshold = (float) get_option( 'aio_login_google_recaptcha_v3_threshold', 0.5 );
				$score     = isset( $body['score'] ) ? (float) $body['score'] : 0;
				if ( $score < $threshold ) {
					return new \WP_Error( 'captcha_invalid', __( 'Please verify that you are not a robot.', 'change-wp-admin-login' ) );
				}
			}

			return true;
		}

		/**
		 * @return true|\WP_Error
		 */
		private static function verify_hcaptcha() {
			$response = isset( $_POST['h-captcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['h-captcha-response'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( '' === $response ) {
				return new \WP_Error( 'captcha_required', __( 'Please complete the captcha challenge.', 'change-wp-admin-login' ) );
			}

			$secret = (string) get_option( 'aio_login_hcaptcha_secret_key', '' );
			if ( '' === $secret ) {
				return new \WP_Error( 'captcha_misconfigured', __( 'Captcha is not configured correctly.', 'change-wp-admin-login' ) );
			}

			$remote = wp_remote_post(
				'https://api.hcaptcha.com/siteverify',
				array(
					'timeout' => 15,
					'body'    => array(
						'secret'   => $secret,
						'response' => $response,
					),
				)
			);

			if ( is_wp_error( $remote ) ) {
				return new \WP_Error( 'captcha_network', __( 'Unable to verify captcha. Please try again.', 'change-wp-admin-login' ) );
			}

			$body = json_decode( wp_remote_retrieve_body( $remote ), true );
			if ( empty( $body['success'] ) ) {
				return new \WP_Error( 'captcha_invalid', __( 'Please complete the captcha challenge.', 'change-wp-admin-login' ) );
			}

			return true;
		}

		/**
		 * @return true|\WP_Error
		 */
		private static function verify_turnstile() {
			$response = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( '' === $response ) {
				return new \WP_Error( 'captcha_required', __( 'Please complete the captcha challenge.', 'change-wp-admin-login' ) );
			}

			$secret = (string) get_option( 'aio_login_turnstile_secret_key', '' );
			if ( '' === $secret ) {
				return new \WP_Error( 'captcha_misconfigured', __( 'Captcha is not configured correctly.', 'change-wp-admin-login' ) );
			}

			$remote = wp_remote_post(
				'https://challenges.cloudflare.com/turnstile/v0/siteverify',
				array(
					'timeout' => 15,
					'body'    => array(
						'secret'   => $secret,
						'response' => $response,
						'remoteip' => \AIO_Login\Helper\Helper::get_ip(),
					),
				)
			);

			if ( is_wp_error( $remote ) ) {
				return new \WP_Error( 'captcha_network', __( 'Unable to verify captcha. Please try again.', 'change-wp-admin-login' ) );
			}

			$body = json_decode( wp_remote_retrieve_body( $remote ), true );
			if ( empty( $body['success'] ) ) {
				return new \WP_Error( 'captcha_invalid', __( 'Please complete the captcha challenge.', 'change-wp-admin-login' ) );
			}

			return true;
		}
	}
}
