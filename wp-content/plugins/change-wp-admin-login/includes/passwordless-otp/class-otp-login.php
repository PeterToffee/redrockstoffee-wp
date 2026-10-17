<?php
/**
 * Passwordless OTP login UI and AJAX handlers.
 *
 * @package AIO_Login
 */

namespace AIO_Login\Passwordless_Otp;

use AIO_Login\AIO_Login;
use AIO_Login\Helper\Helper;
use AIO_Login\Login_Controller\Login_Redirection;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Passwordless_Otp\\OTP_Login' ) ) {
	/**
	 * OTP_Login
	 */
	final class OTP_Login {

		private const AJAX_NONCE = 'aio_login_otp_login';

		private const COMPLETE_LOGIN_ACTION = 'aio_login_otp_complete';

		private const COMPLETE_TRANSIENT_PREFIX = 'aio_login_otp_complete_';

		/**
		 * Channel for the in-flight passwordless verify AJAX (email|sms).
		 *
		 * @var string|null
		 */
		private static $passwordless_login_channel = null;

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
			if ( ! self::should_boot() ) {
				return;
			}

			add_action( 'login_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'login_form', array( $this, 'render_launcher_buttons' ), 8 );
			add_action( 'login_footer', array( $this, 'render_otp_inline_panel' ), 5 );

			add_action( 'wp_ajax_nopriv_aio_login_otp_send', array( $this, 'ajax_send' ) );
			add_action( 'wp_ajax_aio_login_otp_send', array( $this, 'ajax_send' ) );
			add_action( 'wp_ajax_nopriv_aio_login_otp_resend', array( $this, 'ajax_resend' ) );
			add_action( 'wp_ajax_aio_login_otp_resend', array( $this, 'ajax_resend' ) );
			add_action( 'wp_ajax_nopriv_aio_login_otp_verify', array( $this, 'ajax_verify' ) );
			add_action( 'wp_ajax_aio_login_otp_verify', array( $this, 'ajax_verify' ) );

			// After 2FA redirect (100) and setup interstitial (101) so passwordless policy wins.
			add_filter( 'login_redirect', array( $this, 'finalize_passwordless_login_redirect' ), 102, 3 );

			// Full-page completion so auth + 2FA cookies are set reliably (AJAX Set-Cookie is unreliable).
			add_action( 'login_init', array( $this, 'maybe_complete_passwordless_login' ), 0 );
		}

		/**
		 * @return bool
		 */
		private static function should_boot() {
			return OTP_Settings::is_channel_enabled( 'email' )
				|| OTP_Settings::is_sms_login_available();
		}

		public function enqueue_assets() {
			wp_enqueue_style(
				'aio-login-passwordless-otp',
				AIO_LOGIN__DIR_URL . 'assets/css/passwordless-otp-login.css',
				array(),
				AIO_LOGIN__VERSION
			);

			wp_enqueue_script(
				'aio-login-passwordless-captcha',
				AIO_LOGIN__DIR_URL . 'assets/js/passwordless-captcha.js',
				array(),
				AIO_LOGIN__VERSION,
				true
			);

			wp_enqueue_script(
				'aio-login-passwordless-otp',
				AIO_LOGIN__DIR_URL . 'assets/js/passwordless-otp-login.js',
				array( 'jquery', 'aio-login-passwordless-captcha' ),
				AIO_LOGIN__VERSION,
				true
			);

			$captcha = OTP_Captcha::get_frontend_config();
			if ( ! empty( $captcha['provider'] ) && 'recaptcha' === $captcha['provider'] ) {
				$version = $captcha['version'] ?? 'v2';
				if ( 'v3' === $version ) {
					wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $captcha['site_key'] ), array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				} else {
					wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				}
			} elseif ( ! empty( $captcha['provider'] ) && 'hcaptcha' === $captcha['provider'] ) {
				wp_enqueue_script( 'hcaptcha', 'https://js.hcaptcha.com/1/api.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			} elseif ( ! empty( $captcha['provider'] ) && 'turnstile' === $captcha['provider'] ) {
				if ( wp_script_is( 'aio-login-turnstile', 'registered' ) ) {
					wp_enqueue_script( 'aio-login-turnstile' );
				} else {
					wp_enqueue_script( 'cloudflare-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				}
			}

			$sms_default = OTP_Settings::get_login_sms_default_country();

			wp_localize_script(
				'aio-login-passwordless-otp',
				'aioLoginOtp',
				array(
					'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
					'nonce'             => wp_create_nonce( self::AJAX_NONCE ),
					'emailEnabled'      => OTP_Settings::is_channel_enabled( 'email' ),
					'smsEnabled'        => OTP_Settings::is_sms_login_available(),
					'emailLength'       => OTP_Settings::get_otp_length( 'email' ),
					'smsLength'         => OTP_Settings::get_otp_length( 'sms' ),
					'emailResend'       => OTP_Settings::get_resend_seconds( 'email' ),
					'smsResend'         => OTP_Settings::get_resend_seconds( 'sms' ),
					'defaultCountry'    => $sms_default['code'],
					'defaultCountryIso' => $sms_default['iso'],
					'countryCodes'      => OTP_Settings::get_login_country_codes(),
					'captcha'           => $captcha,
					'captchaRequired'   => OTP_Captcha::is_required(),
					'i18n'              => array(
						'continueEmail'    => __( 'Continue with Email', 'change-wp-admin-login' ),
						'continueSms'      => __( 'Continue with SMS', 'change-wp-admin-login' ),
						'sendCode'         => __( 'Send Code', 'change-wp-admin-login' ),
						'back'             => __( 'Back', 'change-wp-admin-login' ),
						'backToLogin'      => __( 'Back to login', 'change-wp-admin-login' ),
						'verify'           => __( 'Verify', 'change-wp-admin-login' ),
						'resend'           => __( 'Resend code', 'change-wp-admin-login' ),
						'resendIn'         => __( 'Resend code in %ds', 'change-wp-admin-login' ),
						'successTitle'     => __( "You're signed in", 'change-wp-admin-login' ),
						'successMessage'   => __( "You're signed in. Redirecting you to your dashboard…", 'change-wp-admin-login' ),
						'successTitle2fa'  => __( 'Verification successful', 'change-wp-admin-login' ),
						'successMessage2fa' => __( 'Complete two-factor authentication to finish signing in…', 'change-wp-admin-login' ),
						'continueDash'     => __( 'Continue to Dashboard', 'change-wp-admin-login' ),
						'emailPlaceholder' => __( 'Email address', 'change-wp-admin-login' ),
						'phonePlaceholder' => __( 'Phone number', 'change-wp-admin-login' ),
						'otpLabel'         => __( 'Verification code', 'change-wp-admin-login' ),
						'noSmsCountries'   => __( 'SMS login is not available for your region. Please contact the site administrator.', 'change-wp-admin-login' ),
						'invalidPhone'     => __( 'Please enter a valid phone number.', 'change-wp-admin-login' ),
						'registerLink'     => __( 'Register', 'change-wp-admin-login' ),
					),
					'registerUrl'       => $this->get_registration_url(),
				)
			);
		}

		public function render_launcher_buttons() {
			$email = OTP_Settings::is_channel_enabled( 'email' );
			$sms   = OTP_Settings::is_sms_login_available();

			if ( ! $email && ! $sms ) {
				return;
			}

			echo '<div class="aio-login-otp-buttons-wrapper" role="group" aria-label="' . esc_attr__( 'Passwordless login', 'change-wp-admin-login' ) . '">';

			if ( $email ) {
				printf(
					'<button type="button" class="aio-login-otp-launcher" data-channel="email" aria-label="%1$s"><span class="aio-login-otp-launcher__icon" aria-hidden="true">%2$s</span><span>%1$s</span></button>',
					esc_html__( 'Continue with Email', 'change-wp-admin-login' ),
					self::get_email_launcher_icon_svg() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
			}

			if ( $sms ) {
				printf(
					'<button type="button" class="aio-login-otp-launcher" data-channel="sms" aria-label="%1$s"><span class="aio-login-otp-launcher__icon" aria-hidden="true">%2$s</span><span>%1$s</span></button>',
					esc_html__( 'Continue with SMS', 'change-wp-admin-login' ),
					self::get_sms_launcher_icon_svg() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
			}

			echo '</div>';
		}

		public function render_otp_inline_panel() {
			include AIO_LOGIN__DIR_PATH . 'includes/passwordless-otp/views/otp-inline-panel.php';
		}

		public function ajax_send() {
			$this->verify_ajax_nonce();

			$captcha = OTP_Captcha::verify_request();
			if ( is_wp_error( $captcha ) ) {
				wp_send_json_error( array( 'message' => $captcha->get_error_message() ) );
			}

			$channel = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( 'email' === $channel ) {
				$this->handle_send_email();
			} elseif ( 'sms' === $channel ) {
				$this->handle_send_sms();
			} else {
				wp_send_json_error( array( 'message' => __( 'Invalid request.', 'change-wp-admin-login' ) ) );
			}
		}

		public function ajax_resend() {
			$this->verify_ajax_nonce();

			$token = isset( $_POST['token'] ) ? OTP_Service::normalize_session_token( wp_unslash( $_POST['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$result = OTP_Service::resend_challenge( $token );

			if ( is_wp_error( $result ) ) {
				$data = array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				);
				if ( 'resend_wait' === $result->get_error_code() ) {
					$err_data = $result->get_error_data();
					if ( is_array( $err_data ) && isset( $err_data['resend_in'] ) ) {
						$data['resend_in'] = (int) $err_data['resend_in'];
					}
				}
				if ( 'verify_blocked' === $result->get_error_code() ) {
					$data['code'] = 'verify_blocked';
					$err_data     = $result->get_error_data();
					if ( is_array( $err_data ) && isset( $err_data['length'] ) ) {
						$data['length'] = (int) $err_data['length'];
					}
				}
				wp_send_json_error( $data );
			}

			$session = OTP_Service::get_session( $token );
			if ( is_wp_error( $session ) ) {
				wp_send_json_error( array( 'message' => $session->get_error_message() ) );
			}

			$deliver = $this->deliver_otp(
				$session['channel'],
				(int) $session['user_id'],
				$session['identifier'],
				$result['otp']
			);
			if ( is_wp_error( $deliver ) ) {
				wp_send_json_error( array( 'message' => $deliver->get_error_message() ) );
			}

			wp_send_json_success(
				array(
					'message'   => __( 'A new verification code has been sent.', 'change-wp-admin-login' ),
					'resend_in' => $result['resend_in'],
				)
			);
		}

		public function ajax_verify() {
			$this->verify_ajax_nonce();

			$token = isset( $_POST['token'] ) ? OTP_Service::normalize_session_token( wp_unslash( $_POST['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$otp   = isset( $_POST['otp'] ) ? sanitize_text_field( wp_unslash( $_POST['otp'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

			$result = OTP_Service::verify_challenge( $token, $otp );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error(
					array(
						'message' => $result->get_error_message(),
						'code'    => $result->get_error_code(),
					)
				);
			}

			$user_id = (int) $result['user_id'];
			$channel = isset( $result['channel'] ) ? sanitize_key( (string) $result['channel'] ) : 'email';
			$user    = get_user_by( 'id', $user_id );
			if ( ! ( $user instanceof \WP_User ) ) {
				wp_send_json_error( array( 'message' => __( 'Unable to sign you in.', 'change-wp-admin-login' ) ) );
			}

			if ( ! OTP_Settings::should_skip_two_factor( $channel ) ) {
				wp_clear_auth_cookie();
				wp_set_current_user( $user_id );
				wp_set_auth_cookie( $user_id, true );

				/** Same hook as username/password login — required for 2FA policy redirects. */
				do_action( 'wp_login', $user->user_login, $user );

				// Drop prior 2FA completion cookie so Skip-2FA-off always re-challenges.
				if ( class_exists( '\AIO_Login_Pro\Two_Factor\Two_Factor_Auth' ) ) {
					$tfa = \AIO_Login_Pro\Two_Factor\Two_Factor_Auth::get_instance();
					if ( $tfa && method_exists( $tfa, 'clear_login_challenge_cookie' ) ) {
						$tfa->clear_login_challenge_cookie();
					}
				}

				if ( $this->passwordless_login_requires_2fa( $user ) ) {
					$default_redirect = admin_url();
					self::$passwordless_login_channel = $channel;
					$redirect                       = apply_filters( 'login_redirect', $default_redirect, '', $user );
					self::$passwordless_login_channel = null;
					$redirect                       = wp_validate_redirect( (string) $redirect, $default_redirect );

					wp_send_json_success(
						array(
							'message'      => __( 'Complete two-factor authentication to finish signing in…', 'change-wp-admin-login' ),
							'redirect'     => esc_url_raw( $redirect ),
							'requires_2fa' => true,
						)
					);
				}

				wp_clear_auth_cookie();
			}

			wp_send_json_success(
				array(
					'message'      => __( "You're signed in. Redirecting you to your dashboard…", 'change-wp-admin-login' ),
					'redirect'     => esc_url_raw( $this->build_passwordless_completion_url( $user_id, $channel ) ),
					'requires_2fa'  => false,
				)
			);
		}

		private function handle_send_email() {
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$user_id = OTP_Service::find_user_id_by_email( $email );
			if ( is_wp_error( $user_id ) ) {
				wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
			}
			if ( (int) $user_id <= 0 ) {
				$this->reject_unregistered_otp_user( 'email' );
			}

			$challenge = OTP_Service::create_challenge( 'email', $email, (int) $user_id );
			if ( is_wp_error( $challenge ) ) {
				$this->send_challenge_error( $challenge );
			}

			$sent = OTP_Email_Sender::send( (int) $user_id, $challenge['otp'], $email );
			if ( is_wp_error( $sent ) ) {
				wp_send_json_error( array( 'message' => $sent->get_error_message() ) );
			}

			wp_send_json_success(
				array(
					'token'     => $challenge['token'],
					'length'    => OTP_Settings::get_otp_length( 'email' ),
					'resend_in' => $challenge['resend_in'],
					'message'   => __( 'Verification code sent to your email.', 'change-wp-admin-login' ),
				)
			);
		}

		private function handle_send_sms() {
			if ( ! AIO_Login::has_pro() ) {
				wp_send_json_error( array( 'message' => __( 'SMS login requires AIO Login Pro.', 'change-wp-admin-login' ) ) );
			}

			$cc     = isset( $_POST['country_code'] ) ? sanitize_text_field( wp_unslash( $_POST['country_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$number = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$iso    = isset( $_POST['country_iso'] ) ? sanitize_text_field( wp_unslash( $_POST['country_iso'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

			$phone = OTP_Service::normalize_phone( $cc, $number, $iso );
			if ( is_wp_error( $phone ) ) {
				wp_send_json_error( array( 'message' => $phone->get_error_message() ) );
			}

			$user_id = OTP_Service::find_user_id_by_phone( $cc, $number, $iso );
			if ( is_wp_error( $user_id ) ) {
				wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
			}
			if ( (int) $user_id <= 0 ) {
				$this->reject_unregistered_otp_user( 'sms' );
			}

			$challenge = OTP_Service::create_challenge( 'sms', $phone, (int) $user_id );
			if ( is_wp_error( $challenge ) ) {
				$this->send_challenge_error( $challenge );
			}

			$deliver = $this->deliver_otp( 'sms', $user_id, $phone, $challenge['otp'] );
			if ( is_wp_error( $deliver ) ) {
				wp_send_json_error( array( 'message' => $deliver->get_error_message() ) );
			}

			wp_send_json_success(
				array(
					'token'     => $challenge['token'],
					'length'    => OTP_Settings::get_otp_length( 'sms' ),
					'resend_in' => $challenge['resend_in'],
					'message'   => __( 'Verification code sent to your phone.', 'change-wp-admin-login' ),
				)
			);
		}

		/**
		 * @param string $channel   Channel.
		 * @param int    $user_id   User.
		 * @param string $identifier Identifier.
		 * @param string $otp       OTP.
		 * @return true|\WP_Error
		 */
		private function deliver_otp( $channel, $user_id, $identifier, $otp ) {
			if ( 'email' === $channel ) {
				return OTP_Email_Sender::send( (int) $user_id, $otp, $identifier );
			}
			if ( 'sms' === $channel && class_exists( '\AIO_Login_Pro\Passwordless_Otp\OTP_Sms_Sender' ) ) {
				return \AIO_Login_Pro\Passwordless_Otp\OTP_Sms_Sender::send( $identifier, $otp );
			}
			return new \WP_Error( 'delivery_failed', __( 'Unable to send verification code.', 'change-wp-admin-login' ) );
		}

		/**
		 * Whether this user must complete AIO Login 2FA after passwordless OTP (Skip 2FA off).
		 *
		 * @param \WP_User $user User.
		 * @return bool
		 */
		private function passwordless_login_requires_2fa( $user ) {
			if ( ! ( $user instanceof \WP_User ) ) {
				return false;
			}
			if ( ! class_exists( '\AIO_Login_Pro\Two_Factor\Two_Factor_Auth' ) ) {
				return false;
			}
			$tfa = \AIO_Login_Pro\Two_Factor\Two_Factor_Auth::get_instance();
			if ( $tfa && method_exists( $tfa, 'user_has_pending_login_challenge' ) ) {
				return (bool) $tfa->user_has_pending_login_challenge( $user );
			}
			return false;
		}

		/**
		 * Mark 2FA complete so OTP passwordless login is not challenged again.
		 *
		 * @param \WP_User $user    User.
		 * @param string   $channel email|sms.
		 */
		private function skip_two_factor_for_passwordless( $user, $channel = 'email' ) {
			if ( ! OTP_Settings::should_skip_two_factor( $channel ) ) {
				return;
			}
			if ( ! class_exists( '\AIO_Login_Pro\Two_Factor\Two_Factor_Auth' ) ) {
				return;
			}
			$tfa = \AIO_Login_Pro\Two_Factor\Two_Factor_Auth::get_instance();
			if ( $tfa && method_exists( $tfa, 'login_user' ) ) {
				$tfa->login_user( $user );
			}
		}

		/**
		 * Correct login_redirect after passwordless OTP verify (runs at priority 102).
		 *
		 * @param string             $redirect_to           Redirect URL.
		 * @param string             $requested_redirect_to Requested redirect.
		 * @param \WP_User|\WP_Error $user                  User.
		 * @return string
		 */
		public function finalize_passwordless_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
			if ( null === self::$passwordless_login_channel || ! ( $user instanceof \WP_User ) ) {
				return $redirect_to;
			}

			$channel  = self::$passwordless_login_channel;
			$skip_2fa = OTP_Settings::should_skip_two_factor( $channel );

			if ( $skip_2fa ) {
				return $this->resolve_passwordless_dashboard_redirect( $user );
			}

			if ( $this->passwordless_login_requires_2fa( $user ) ) {
				if ( class_exists( '\AIO_Login_Pro\Two_Factor\Two_Factor_Auth' ) ) {
					return \AIO_Login_Pro\Two_Factor\Two_Factor_Auth::get_verification_url( $redirect_to );
				}
			}

			return $redirect_to;
		}

		/**
		 * Dashboard (or login redirection rules) after passwordless login with Skip 2FA enabled.
		 *
		 * @param \WP_User $user User.
		 * @return string
		 */
		private function resolve_passwordless_dashboard_redirect( $user ) {
			$redirect = admin_url();
			if ( class_exists( Login_Redirection::class ) ) {
				$redirect = Login_Redirection::get_instance()->apply_login_redirection( $redirect, '', $user );
			}
			return wp_validate_redirect( (string) $redirect, admin_url() );
		}

		/**
		 * One-time wp-login.php URL used when Skip 2FA is enabled (sets cookies via full page load).
		 *
		 * @param int    $user_id User ID.
		 * @param string $channel email|sms.
		 * @return string
		 */
		private function build_passwordless_completion_url( $user_id, $channel ) {
			$key = wp_generate_password( 32, false, false );
			set_transient(
				$this->get_completion_transient_name( $key ),
				array(
					'user_id' => (int) $user_id,
					'channel' => 'sms' === $channel ? 'sms' : 'email',
				),
				5 * MINUTE_IN_SECONDS
			);

			return add_query_arg(
				array(
					'action' => self::COMPLETE_LOGIN_ACTION,
					'key'    => $key,
				),
				wp_login_url()
			);
		}

		/**
		 * @param string $key Completion key from query string.
		 * @return string
		 */
		private function get_completion_transient_name( $key ) {
			return self::COMPLETE_TRANSIENT_PREFIX . hash_hmac( 'sha256', (string) $key, wp_salt( 'aio_login_otp_complete' ) );
		}

		/**
		 * Finish passwordless login on wp-login.php (Skip 2FA on).
		 */
		public function maybe_complete_passwordless_login() {
			if ( empty( $_GET['action'] ) || self::COMPLETE_LOGIN_ACTION !== sanitize_key( wp_unslash( $_GET['action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}

			$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( '' === $key ) {
				wp_safe_redirect( wp_login_url() );
				exit;
			}

			$transient_name = $this->get_completion_transient_name( $key );
			$payload        = get_transient( $transient_name );

			if ( ! is_array( $payload ) || empty( $payload['user_id'] ) ) {
				if ( is_user_logged_in() ) {
					wp_safe_redirect( $this->resolve_passwordless_dashboard_redirect( wp_get_current_user() ) );
					exit;
				}

				wp_safe_redirect( wp_login_url() );
				exit;
			}

			$user = get_user_by( 'id', (int) $payload['user_id'] );
			if ( ! ( $user instanceof \WP_User ) ) {
				delete_transient( $transient_name );
				wp_safe_redirect( wp_login_url() );
				exit;
			}

			$channel = isset( $payload['channel'] ) ? sanitize_key( (string) $payload['channel'] ) : 'email';

			wp_clear_auth_cookie();
			wp_set_current_user( $user->ID, $user->user_login );
			wp_set_auth_cookie( $user->ID, true );
			do_action( 'wp_login', $user->user_login, $user );

			if ( OTP_Settings::should_skip_two_factor( $channel ) ) {
				$this->skip_two_factor_for_passwordless( $user, $channel );
			}

			delete_transient( $transient_name );

			self::$passwordless_login_channel = $channel;
			$redirect                         = apply_filters( 'login_redirect', admin_url(), '', $user );
			self::$passwordless_login_channel = null;

			wp_safe_redirect( wp_validate_redirect( (string) $redirect, admin_url() ) );
			exit;
		}

		/**
		 * Envelope icon for Continue with Email (matches admin OTP settings card).
		 *
		 * @return string SVG markup.
		 */
		private static function get_email_launcher_icon_svg() {
			return '<svg class="aio-login-otp-launcher__svg" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">'
				. '<path d="M4 6.5h16c.83 0 1.5.67 1.5 1.5v8c0 .83-.67 1.5-1.5 1.5H4A1.5 1.5 0 012.5 16V8c0-.83.67-1.5 1.5-1.5z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>'
				. '<path d="M3 7.5l8.65 5.77a1.5 1.5 0 001.7 0L22 7.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>'
				. '</svg>';
		}

		/**
		 * Message bubble icon for Continue with SMS (matches admin OTP settings card).
		 *
		 * @return string SVG markup.
		 */
		private static function get_sms_launcher_icon_svg() {
			return '<svg class="aio-login-otp-launcher__svg" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">'
				. '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>'
				. '</svg>';
		}

		/**
		 * @param \WP_Error $error Challenge error.
		 */
		private function send_challenge_error( $error ) {
			$data = array( 'message' => $error->get_error_message() );
			if ( 'verify_blocked' === $error->get_error_code() ) {
				$data['code'] = 'verify_blocked';
				$err_data     = $error->get_error_data();
				if ( is_array( $err_data ) && isset( $err_data['length'] ) ) {
					$data['length'] = (int) $err_data['length'];
				}
			}
			wp_send_json_error( $data );
		}

		/**
		 * @param string $channel email|sms.
		 */
		private function reject_unregistered_otp_user( $channel ) {
			wp_send_json_error(
				array(
					'message'      => $this->get_not_registered_message( $channel ),
					'code'         => 'not_registered',
					'register_url' => $this->get_registration_url(),
				)
			);
		}

		/**
		 * @param string $channel email|sms.
		 * @return string
		 */
		private function get_not_registered_message( $channel ) {
			$register_url = $this->get_registration_url();

			if ( 'sms' === $channel ) {
				return __( 'No account is linked to this phone number. If you already have an account, add your mobile number in your profile. Otherwise, please register first.', 'change-wp-admin-login' );
			}

			if ( $register_url ) {
				return __( 'You do not have an account yet. Please register first, then use Continue with Email.', 'change-wp-admin-login' );
			}

			return __( 'You do not have an account yet. Please register first or contact the site administrator.', 'change-wp-admin-login' );
		}

		/**
		 * @return string Registration URL or empty when registration is disabled.
		 */
		private function get_registration_url() {
			if ( ! get_option( 'users_can_register' ) ) {
				return '';
			}

			return (string) wp_registration_url();
		}

		private function verify_ajax_nonce() {
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), self::AJAX_NONCE ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'change-wp-admin-login' ) ), 403 );
			}
		}
	}
}
