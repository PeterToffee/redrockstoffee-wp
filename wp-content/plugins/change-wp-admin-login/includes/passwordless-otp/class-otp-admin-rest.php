<?php
/**
 * REST API for passwordless OTP admin settings.
 *
 * @package AIO_Login
 */

namespace AIO_Login\Passwordless_Otp;

use AIO_Login\Helper\Helper;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Passwordless_Otp\\OTP_Admin_Rest' ) ) {
	/**
	 * OTP_Admin_Rest
	 */
	final class OTP_Admin_Rest {

		/**
		 * @return self
		 */
		public static function get_instance() {
			static $instance = null;
			if ( null === $instance ) {
				$instance = new self();
			}
			return $instance;
		}

		private function __construct() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}

		public function register_routes() {
			register_rest_route(
				'aio-login/passwordless-otp',
				'/get-settings',
				array(
					'methods'             => 'GET',
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
					'callback'            => array( $this, 'get_settings' ),
				)
			);

			register_rest_route(
				'aio-login/passwordless-otp',
				'/save-settings',
				array(
					'methods'             => 'POST',
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
					'callback'            => array( $this, 'save_settings' ),
				)
			);
		}

		/**
		 * @return \WP_REST_Response
		 */
		public function get_settings() {
			return rest_ensure_response( OTP_Settings::get_admin_settings() );
		}

		/**
		 * @param \WP_REST_Request $request Request.
		 * @return \WP_REST_Response|\WP_Error
		 */
		public function save_settings( $request ) {
			$params = $request->get_json_params();
			if ( ! is_array( $params ) ) {
				$params = $request->get_params();
			}

			if ( empty( $params['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $params['_wpnonce'] ) ), OTP_Settings::NONCE_ACTION ) ) {
				return new \WP_Error( 'forbidden', __( 'Invalid security token.', 'change-wp-admin-login' ), array( 'status' => 403 ) );
			}

			$email_enable = ! empty( $params['email_enable'] ) ? 'on' : 'off';
			update_option( 'aio_login_otp_email_enable', $email_enable, false );

			update_option(
				'aio_login_otp_email_block_duration',
				(string) max( 1, min( 1440, absint( $params['email_block_duration'] ?? 15 ) ) ),
				false
			);
			update_option(
				'aio_login_otp_email_skip_2fa',
				! empty( $params['email_skip_2fa'] ) ? 'on' : 'off',
				false
			);

			if ( 'on' === $email_enable ) {
				$this->save_email_settings( $params );
			}

			$sms_enable = 'off';
			if ( \AIO_Login\AIO_Login::has_pro() ) {
				if ( ! empty( $params['sms_enable'] ) ) {
					update_option( 'aio_login_otp_sms_enable', 'on', false );
					$sms_enable = 'on';
				} else {
					OTP_Settings::disable_sms_channel();
				}
			}

			if ( \AIO_Login\AIO_Login::has_pro() ) {
				update_option(
					'aio_login_otp_sms_block_duration',
					(string) max( 1, min( 1440, absint( $params['sms_block_duration'] ?? 15 ) ) ),
					false
				);
				update_option(
					'aio_login_otp_sms_skip_2fa',
					! empty( $params['sms_skip_2fa'] ) ? 'on' : 'off',
					false
				);
			}

			if ( 'on' === $sms_enable && \AIO_Login\AIO_Login::has_pro() ) {
				$twilio_error = $this->validate_twilio_settings( $params );
				if ( is_wp_error( $twilio_error ) ) {
					return $twilio_error;
				}

				$allowed_isos = OTP_Settings::sanitize_sms_allowed_country_isos( $params['sms_allowed_countries'] ?? array() );
				if ( empty( $allowed_isos ) ) {
					return new \WP_Error(
						'sms_allowed_countries_required',
						__( 'Select at least one allowed country for SMS login.', 'change-wp-admin-login' ),
						array( 'status' => 400 )
					);
				}
				$this->save_sms_settings( $params );
			}

			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Settings saved successfully.', 'change-wp-admin-login' ),
					'data'    => OTP_Settings::get_admin_settings(),
				)
			);
		}

		/**
		 * @param array<string, mixed> $params Params.
		 */
		private function save_email_settings( $params ) {
			$length = isset( $params['email_length'] ) ? absint( $params['email_length'] ) : 6;
			if ( ! in_array( $length, array( 4, 6, 8 ), true ) ) {
				$length = 6;
			}
			update_option( 'aio_login_otp_email_length', (string) $length, false );
			update_option( 'aio_login_otp_email_expiration', (string) max( 1, min( 60, absint( $params['email_expiration'] ?? 10 ) ) ), false );
			update_option( 'aio_login_otp_email_resend_timer', (string) max( 30, min( 600, absint( $params['email_resend_timer'] ?? 60 ) ) ), false );
			update_option( 'aio_login_otp_email_max_retries', (string) max( 1, min( 20, absint( $params['email_max_retries'] ?? 5 ) ) ), false );
		}

		/**
		 * Require Twilio credentials when SMS OTP is enabled.
		 *
		 * @param array<string, mixed> $params Params.
		 * @return true|\WP_Error
		 */
		private function validate_twilio_settings( $params ) {
			$sid    = sanitize_text_field( $params['twilio_account_sid'] ?? '' );
			$from   = sanitize_text_field( $params['twilio_sender_number'] ?? '' );
			$token  = sanitize_text_field( $params['twilio_auth_token'] ?? '' );
			$has_stored_token = '' !== (string) get_option( 'aio_login_otp_twilio_auth_token', '' );
			$has_new_token    = '' !== $token && '••••••••••••' !== $token;

			if ( '' === $sid ) {
				return new \WP_Error(
					'twilio_account_sid_required',
					__( 'Account SID is required.', 'change-wp-admin-login' ),
					array( 'status' => 400 )
				);
			}

			if ( ! $has_stored_token && ! $has_new_token ) {
				return new \WP_Error(
					'twilio_auth_token_required',
					__( 'Auth Token is required.', 'change-wp-admin-login' ),
					array( 'status' => 400 )
				);
			}

			if ( '' === $from ) {
				return new \WP_Error(
					'twilio_sender_number_required',
					__( 'Sender number is required.', 'change-wp-admin-login' ),
					array( 'status' => 400 )
				);
			}

			return true;
		}

		/**
		 * @param array<string, mixed> $params Params.
		 */
		private function save_sms_settings( $params ) {
			$length = isset( $params['sms_length'] ) ? absint( $params['sms_length'] ) : 6;
			if ( ! in_array( $length, array( 4, 6, 8 ), true ) ) {
				$length = 6;
			}
			update_option( 'aio_login_otp_sms_length', (string) $length, false );
			update_option( 'aio_login_otp_sms_expiration', (string) max( 1, min( 60, absint( $params['sms_expiration'] ?? 10 ) ) ), false );
			update_option( 'aio_login_otp_sms_resend_timer', (string) max( 30, min( 600, absint( $params['sms_resend_timer'] ?? 60 ) ) ), false );
			update_option( 'aio_login_otp_sms_max_retries', (string) max( 1, min( 20, absint( $params['sms_max_retries'] ?? 5 ) ) ), false );

			update_option( 'aio_login_otp_twilio_account_sid', sanitize_text_field( $params['twilio_account_sid'] ?? '' ), false );
			update_option( 'aio_login_otp_twilio_sender_number', sanitize_text_field( $params['twilio_sender_number'] ?? '' ), false );
			$default_iso = strtoupper( sanitize_text_field( $params['sms_default_country_iso'] ?? 'US' ) );
			if ( ! preg_match( '/^[A-Z]{2}$/', $default_iso ) ) {
				$default_iso = 'US';
			}
			$iso_valid = false;
			foreach ( OTP_Settings::get_country_codes() as $country ) {
				if ( $country['iso'] === $default_iso ) {
					$iso_valid = true;
					break;
				}
			}
			if ( ! $iso_valid ) {
				$default_iso = 'US';
			}

			update_option( 'aio_login_otp_sms_default_country_iso', $default_iso, false );
			update_option( 'aio_login_otp_sms_default_country', OTP_Settings::get_dial_code_for_country_iso( $default_iso ), false );

			if ( ! empty( $params['twilio_auth_token'] ) && '••••••••••••' !== $params['twilio_auth_token'] ) {
				$encrypted = OTP_Encryption::encrypt( sanitize_text_field( $params['twilio_auth_token'] ) );
				if ( '' !== $encrypted ) {
					update_option( 'aio_login_otp_twilio_auth_token', $encrypted, false );
				}
			}

			$allowed = OTP_Settings::sanitize_sms_allowed_country_isos( $params['sms_allowed_countries'] ?? array() );
			if ( ! empty( $allowed ) ) {
				update_option( 'aio_login_otp_sms_allowed_countries', wp_json_encode( $allowed ), false );
			}
		}
	}
}
