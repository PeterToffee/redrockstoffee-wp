<?php
/**
 * Login link UI, AJAX send, and email link verification.
 *
 * @package AIO_Login
 */

namespace AIO_Login\Passwordless_Otp;

use AIO_Login\AIO_Login;
use AIO_Login\Helper\Helper;
use AIO_Login\Login_Controller\Login_Redirection;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Passwordless_Otp\\Magic_Link_Login' ) ) {
	/**
	 * Magic_Link_Login
	 */
	final class Magic_Link_Login {

		public const VERIFY_ACTION = 'aio_login_magic_link';

		public const ERROR_QUERY_ARG = 'aio_ml_error';

		private const AJAX_NONCE = 'aio_login_magic_link';

		/**
		 * @var bool|null
		 */
		private static $handling_login_redirect = null;

		/**
		 * Prevents duplicate launcher on checkout when login form already rendered one.
		 *
		 * @var bool
		 */
		private static $woocommerce_launcher_rendered = false;

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
			add_action( 'wp_ajax_nopriv_aio_login_magic_link_send', array( $this, 'ajax_send' ) );
			add_action( 'wp_ajax_aio_login_magic_link_send', array( $this, 'ajax_send' ) );
			add_action( 'login_init', array( $this, 'maybe_consume_magic_link' ), 1 );
			add_filter( 'login_redirect', array( $this, 'finalize_magic_link_login_redirect' ), 102, 3 );

			if ( Magic_Link_Settings::is_login_available() ) {
				add_action( 'login_enqueue_scripts', array( $this, 'enqueue_assets' ) );
				add_action( 'login_form', array( $this, 'render_launcher_button' ), 9 );
				add_action( 'login_footer', array( $this, 'render_inline_panel' ), 6 );
				add_filter( 'login_message', array( $this, 'login_error_message' ) );
			}

			add_action( 'wp', array( $this, 'maybe_boot_woocommerce' ) );
			add_filter( 'aio_login_otp_new_user_role', array( $this, 'filter_new_user_role' ), 10, 2 );
		}

		/**
		 * Register WooCommerce login, registration, and checkout UI.
		 */
		public function maybe_boot_woocommerce() {
			if ( is_user_logged_in() || ! Magic_Link_Settings::is_woocommerce_ui_available() ) {
				return;
			}

			$boot = false;

			if ( function_exists( 'is_account_page' ) && is_account_page() ) {
				if ( Magic_Link_Settings::is_woocommerce_context_enabled( 'login' ) ) {
					add_action( 'woocommerce_login_form', array( $this, 'maybe_render_woocommerce_login_button' ), 18 );
					$boot = true;
				}
				if ( Magic_Link_Settings::is_woocommerce_context_enabled( 'registration' ) ) {
					add_action( 'woocommerce_register_form', array( $this, 'maybe_render_woocommerce_register_button' ), 18 );
					$boot = true;
				}
			}

			if ( function_exists( 'is_checkout' ) && is_checkout() && Magic_Link_Settings::is_woocommerce_context_enabled( 'checkout' ) ) {
				add_action( 'woocommerce_login_form', array( $this, 'maybe_render_woocommerce_login_button' ), 18 );
				add_action( 'woocommerce_before_checkout_form', array( $this, 'maybe_render_checkout_magic_link_block' ), 11 );
				add_action( 'wp_footer', array( $this, 'inject_checkout_magic_link_footer' ), 1000 );
				$boot = true;
			}

			if ( ! $boot ) {
				return;
			}

			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'wp_footer', array( $this, 'render_inline_panel' ), 6 );
		}

		/**
		 * @return bool
		 */
		public function maybe_render_woocommerce_login_button() {
			if ( ! Magic_Link_Settings::is_woocommerce_context_enabled( 'login' ) ) {
				return false;
			}
			self::$woocommerce_launcher_rendered = true;
			$this->render_launcher_button();
			return true;
		}

		/**
		 * @return bool
		 */
		public function maybe_render_woocommerce_register_button() {
			if ( ! Magic_Link_Settings::is_woocommerce_context_enabled( 'registration' ) ) {
				return false;
			}
			$this->render_launcher_button();
			return true;
		}

		/**
		 * Block / classic checkout: inject login link into the social area or contact section.
		 */
		public function inject_checkout_magic_link_footer() {
			if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_user_logged_in() ) {
				return;
			}
			if ( ! Magic_Link_Settings::is_woocommerce_context_enabled( 'checkout' ) ) {
				return;
			}

			ob_start();
			?>
			<div id="aio-login-magic-link-checkout-footer-payload" style="display:none!important;visibility:hidden;" aria-hidden="true">
				<div class="woocommerce-checkout-social-login-block aio-login-magic-link-checkout-footer-block">
					<div class="woocommerce-checkout-social-login-block__inner">
						<h3 class="woocommerce-checkout-social-login-block__title aio-login-magic-link-checkout-footer-block__title">
							<?php esc_html_e( 'Or login with', 'change-wp-admin-login' ); ?>
						</h3>
						<div class="aio-login-social-login-buttons-wrapper woocommerce-social-login aio-login-magic-link-checkout-buttons">
							<?php $this->render_launcher_button(); ?>
						</div>
					</div>
				</div>
			</div>
			<script>
			(function () {
				var attempts = 0;
				var maxAttempts = 40;
				var done = false;

				function getPayload() {
					return document.getElementById('aio-login-magic-link-checkout-footer-payload');
				}

				function getLauncherWrapper(payload) {
					if (!payload) {
						return null;
					}
					return payload.querySelector('.aio-login-magic-link-buttons-wrapper');
				}

				function mergeIntoSocialBlock(payload) {
					var social = document.getElementById('aio-login-woocommerce-social-login-block-footer')
						|| document.getElementById('aio-login-woocommerce-social-login-block');
					if (!social) {
						return false;
					}
					var target = social.querySelector('.aio-login-social-login-buttons-wrapper');
					var launcher = getLauncherWrapper(payload);
					if (!target || !launcher) {
						return false;
					}
					if (target.querySelector('.aio-login-magic-link-launcher')) {
						payload.remove();
						done = true;
						return true;
					}
					if (target.firstElementChild) {
						target.insertBefore(launcher, target.firstElementChild);
					} else {
						target.appendChild(launcher);
					}
					payload.remove();
					done = true;
					return true;
				}

				function findCheckoutInsertTarget() {
					var contact = document.querySelector(
						'.wc-block-checkout__contact-fields, .wc-block-checkout-contact-information-block, #contact-fields, [class*="checkout-contact"]'
					);
					if (contact) {
						return contact;
					}
					var headings = document.querySelectorAll('h2, h3, h4');
					for (var i = 0; i < headings.length; i++) {
						if (/contact/i.test(headings[i].textContent || '')) {
							return headings[i].parentElement || headings[i];
						}
					}
					var billing = document.querySelector('.woocommerce-billing-fields, .wc-block-checkout__billing-fields');
					if (billing) {
						return billing.querySelector('h2, h3, h4') || billing;
					}
					var firstName = document.querySelector(
						'input[name="billing_first_name"], #billing_first_name_field, input#billing_first_name'
					);
					if (firstName) {
						var row = firstName.closest('.form-row, .wc-block-components-text-input, p, div');
						return row && row.parentNode ? row : firstName;
					}
					return null;
				}

				function injectStandaloneBlock(payload) {
					var block = payload.querySelector('.woocommerce-checkout-social-login-block');
					var target = findCheckoutInsertTarget();
					if (!block || !target || !target.parentNode) {
						return false;
					}
					var parent = target.parentNode;
					if (target.tagName === 'H2' || target.tagName === 'H3' || target.tagName === 'H4') {
						parent.insertBefore(block, target.nextSibling);
					} else {
						parent.insertBefore(block, target);
					}
					block.style.display = 'block';
					block.style.visibility = 'visible';
					payload.remove();
					done = true;
					return true;
				}

				function tick() {
					if (done) {
						return;
					}
					attempts++;
					var payload = getPayload();
					if (!payload) {
						return;
					}
					if (mergeIntoSocialBlock(payload)) {
						return;
					}
					if (injectStandaloneBlock(payload)) {
						return;
					}
					if (attempts < maxAttempts) {
						setTimeout(tick, 300);
					}
				}

				function start() {
					tick();
					setTimeout(tick, 800);
					setTimeout(tick, 2000);
				}

				if (document.readyState === 'loading') {
					document.addEventListener('DOMContentLoaded', start);
				} else {
					start();
				}
			})();
			</script>
			<?php
			echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		/**
		 * Checkout block when the standard login form is not shown (classic shortcode checkout).
		 */
		public function maybe_render_checkout_magic_link_block() {
			if ( ! Magic_Link_Settings::is_woocommerce_context_enabled( 'checkout' ) ) {
				return;
			}
			if ( self::$woocommerce_launcher_rendered ) {
				return;
			}
			echo '<div class="aio-login-woocommerce-magic-link-checkout" role="region" aria-label="' . esc_attr__( 'Login link', 'change-wp-admin-login' ) . '">';
			echo '<p class="aio-login-woocommerce-magic-link-checkout__title">' . esc_html__( 'Email Me a Login Link', 'change-wp-admin-login' ) . '</p>';
			$this->render_launcher_button();
			echo '</div>';
		}

		/**
		 * @param string $hook_suffix Script hook suffix.
		 */
		public function enqueue_assets( $hook_suffix = '' ) {
			if ( ! wp_style_is( 'aio-login-passwordless-otp', 'registered' ) ) {
				wp_register_style(
					'aio-login-passwordless-otp',
					AIO_LOGIN__DIR_URL . 'assets/css/passwordless-otp-login.css',
					array(),
					AIO_LOGIN__VERSION
				);
			}
			wp_enqueue_style( 'aio-login-passwordless-otp' );

			wp_enqueue_style(
				'aio-login-magic-link',
				AIO_LOGIN__DIR_URL . 'assets/css/passwordless-magic-link-login.css',
				array( 'aio-login-passwordless-otp' ),
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
				'aio-login-form-captcha-position',
				AIO_LOGIN__DIR_URL . 'assets/js/login-form-captcha-position.js',
				array(),
				AIO_LOGIN__VERSION,
				true
			);

			wp_enqueue_script(
				'aio-login-magic-link',
				AIO_LOGIN__DIR_URL . 'assets/js/passwordless-magic-link-login.js',
				array( 'jquery', 'aio-login-passwordless-captcha', 'aio-login-form-captcha-position' ),
				AIO_LOGIN__VERSION,
				true
			);

			$is_checkout_page = function_exists( 'is_checkout' ) && is_checkout();
			$captcha_required = OTP_Captcha::is_required() && ! $is_checkout_page;

			$captcha = OTP_Captcha::get_frontend_config();
			if ( $captcha_required && ! empty( $captcha['provider'] ) && 'recaptcha' === $captcha['provider'] ) {
				$version = $captcha['version'] ?? 'v2';
				if ( 'v3' === $version ) {
					wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $captcha['site_key'] ), array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				} else {
					wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				}
			} elseif ( $captcha_required && ! empty( $captcha['provider'] ) && 'hcaptcha' === $captcha['provider'] ) {
				wp_enqueue_script( 'hcaptcha', 'https://js.hcaptcha.com/1/api.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			} elseif ( $captcha_required && ! empty( $captcha['provider'] ) && 'turnstile' === $captcha['provider'] ) {
				if ( wp_script_is( 'aio-login-turnstile', 'registered' ) ) {
					wp_enqueue_script( 'aio-login-turnstile' );
				} else {
					wp_enqueue_script( 'cloudflare-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				}
			}

			$is_woocommerce = ( function_exists( 'is_account_page' ) && is_account_page() )
				|| $is_checkout_page;

			$checkout_url = '';
			if ( $is_checkout_page && function_exists( 'wc_get_checkout_url' ) ) {
				$checkout_url = Magic_Link_Service::sanitize_redirect_to( wc_get_checkout_url() );
			}

			wp_localize_script(
				'aio-login-magic-link',
				'aioLoginMagicLink',
				array(
					'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
					'nonce'        => wp_create_nonce( self::AJAX_NONCE ),
					'isWooCommerce' => $is_woocommerce,
					'isCheckout'   => $is_checkout_page,
					'checkoutUrl'  => $checkout_url,
					'captcha'      => $captcha,
					'captchaRequired' => $captcha_required,
					'i18n'         => array(
						'launcher'       => __( 'Email Me a Login Link', 'change-wp-admin-login' ),
						'title'          => __( 'Email Me a Login Link', 'change-wp-admin-login' ),
						'sendLink'       => __( 'Send Link', 'change-wp-admin-login' ),
						'backToLogin'    => __( 'Back to login', 'change-wp-admin-login' ),
						'sentTitle'      => __( 'Check your email', 'change-wp-admin-login' ),
						'sentMessage'    => __( 'We sent a secure login link to your email. Open the link to sign in.', 'change-wp-admin-login' ),
						'emailLabel'     => __( 'Email address', 'change-wp-admin-login' ),
						'registerLink'   => __( 'Register', 'change-wp-admin-login' ),
					),
					'registerUrl'  => $this->get_registration_url(),
				)
			);
		}

		public function render_launcher_button() {
			echo '<div class="aio-login-magic-link-buttons-wrapper" role="group" aria-label="' . esc_attr__( 'Login link', 'change-wp-admin-login' ) . '">';
			printf(
				'<button type="button" class="aio-login-otp-launcher aio-login-magic-link-launcher" aria-label="%1$s"><span class="aio-login-otp-launcher__icon" aria-hidden="true">%2$s</span><span>%1$s</span></button>',
				esc_html__( 'Email Me a Login Link', 'change-wp-admin-login' ),
				self::get_mail_icon_svg() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
			echo '</div>';
		}

		public function render_inline_panel() {
			include AIO_LOGIN__DIR_PATH . 'includes/passwordless-otp/views/magic-link-inline-panel.php';
		}

		public function ajax_send() {
			$this->verify_ajax_nonce();

			if ( ! AIO_Login::has_pro() || ! Magic_Link_Settings::is_enabled() ) {
				wp_send_json_error( array( 'message' => __( 'Login link sign-in is not available.', 'change-wp-admin-login' ) ) );
			}

			$context = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$is_checkout_context = ( 'checkout' === $context );

			if ( ! $is_checkout_context ) {
				$captcha = OTP_Captcha::verify_request();
				if ( is_wp_error( $captcha ) ) {
					wp_send_json_error( array( 'message' => $captcha->get_error_message() ) );
				}
			}

			$redirect_to = '';
			if ( $is_checkout_context && function_exists( 'wc_get_checkout_url' ) ) {
				$redirect_to = Magic_Link_Service::sanitize_redirect_to( wc_get_checkout_url() );
			}

			$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$user_id = OTP_Service::find_user_id_by_email( $email );
			if ( is_wp_error( $user_id ) ) {
				wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
			}
			if ( (int) $user_id <= 0 ) {
				if ( ! $this->can_auto_create_account() ) {
					wp_send_json_error(
						array(
							'message'      => $this->get_not_registered_message(),
							'code'         => 'not_registered',
							'register_url' => $this->get_registration_url(),
						)
					);
				}
				$user_id = OTP_Service::create_user_for_email( $email );
				if ( is_wp_error( $user_id ) ) {
					wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
				}
			}

			$link = Magic_Link_Service::create_link( $email, (int) $user_id, $redirect_to );
			if ( is_wp_error( $link ) ) {
				$code = $link->get_error_code();
				wp_send_json_error(
					array(
						'message' => $link->get_error_message(),
						'code'    => $code,
					)
				);
			}

			$sent = Magic_Link_Email_Sender::send( (int) $user_id, $link['url'] );
			if ( is_wp_error( $sent ) ) {
				wp_send_json_error( array( 'message' => $sent->get_error_message() ) );
			}

			wp_send_json_success(
				array(
					'message' => __( 'Login link sent to your email.', 'change-wp-admin-login' ),
				)
			);
		}

		/**
		 * Verify login link from email and sign the user in.
		 */
		public function maybe_consume_magic_link() {
			if ( empty( $_GET['action'] ) || self::VERIFY_ACTION !== sanitize_key( wp_unslash( $_GET['action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}

			$token  = isset( $_GET['t'] ) ? wp_unslash( $_GET['t'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$secret = isset( $_GET['k'] ) ? wp_unslash( $_GET['k'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$logged_in_reuse = Magic_Link_Service::resolve_for_logged_in_user( $token, $secret );
			if ( is_array( $logged_in_reuse ) ) {
				$user = get_user_by( 'id', (int) $logged_in_reuse['user_id'] );
				if ( $user instanceof \WP_User ) {
					$magic_redirect = $logged_in_reuse['redirect_to'];
					if ( '' === $magic_redirect && ! empty( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						$magic_redirect = Magic_Link_Service::sanitize_redirect_to( wp_unslash( $_GET['redirect_to'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					}
					$this->redirect_magic_link_user( $user, $magic_redirect );
					exit;
				}
			}

			$result = Magic_Link_Service::consume_link( $token, $secret );
			if ( is_wp_error( $result ) ) {
				wp_safe_redirect(
					add_query_arg(
						self::ERROR_QUERY_ARG,
						$result->get_error_code(),
						wp_login_url()
					)
				);
				exit;
			}

			$magic_redirect = '';
			$user_id        = 0;
			if ( is_array( $result ) ) {
				$user_id        = isset( $result['user_id'] ) ? (int) $result['user_id'] : 0;
				$magic_redirect = isset( $result['redirect_to'] ) ? (string) $result['redirect_to'] : '';
			} else {
				$user_id = (int) $result;
			}

			if ( '' === $magic_redirect && ! empty( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$magic_redirect = Magic_Link_Service::sanitize_redirect_to( wp_unslash( $_GET['redirect_to'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			$user = get_user_by( 'id', $user_id );
			if ( ! ( $user instanceof \WP_User ) ) {
				wp_safe_redirect(
					add_query_arg( self::ERROR_QUERY_ARG, 'invalid_link', wp_login_url() )
				);
				exit;
			}

			$user_email = strtolower( sanitize_email( $user->user_email ) );
			if ( OTP_Lockout::is_passwordless_verify_blocked( Helper::get_ip(), $user_email, 'email' ) ) {
				wp_safe_redirect(
					add_query_arg( self::ERROR_QUERY_ARG, 'verify_blocked', wp_login_url() )
				);
				exit;
			}

			$skip_2fa = Magic_Link_Settings::should_skip_two_factor();

			wp_clear_auth_cookie();
			wp_set_current_user( $user->ID, $user->user_login );
			wp_set_auth_cookie( $user->ID, true );
			do_action( 'wp_login', $user->user_login, $user );

			OTP_Lockout::clear_verify_lockout( Helper::get_ip() );
			OTP_Lockout::clear_account_lockout( (int) $user->ID, $user_email, 'email' );

			if ( $skip_2fa ) {
				$this->skip_two_factor_for_magic_link( $user );
				$this->redirect_magic_link_user( $user, $magic_redirect );
				exit;
			}

			// Skip 2FA is off: drop any prior "challenge completed" cookie (e.g. from setup)
			// so login_redirect / pending checks re-require OTP for this passwordless login.
			$this->clear_two_factor_challenge_cookie();

			$this->redirect_magic_link_user( $user, $magic_redirect );
			exit;
		}

		/**
		 * Clear AIO Login 2FA completion cookie for the current browser request.
		 */
		private function clear_two_factor_challenge_cookie() {
			if ( ! class_exists( '\AIO_Login_Pro\Two_Factor\Two_Factor_Auth' ) ) {
				return;
			}
			$tfa = \AIO_Login_Pro\Two_Factor\Two_Factor_Auth::get_instance();
			if ( $tfa && method_exists( $tfa, 'clear_login_challenge_cookie' ) ) {
				$tfa->clear_login_challenge_cookie();
			}
		}

		/**
		 * @param \WP_User $user           User.
		 * @param string   $magic_redirect Redirect URL from link.
		 */
		private function redirect_magic_link_user( $user, $magic_redirect = '' ) {
			self::$handling_login_redirect = true;
			$default_redirect              = $magic_redirect ? $magic_redirect : admin_url();
			$redirect                      = apply_filters( 'login_redirect', $default_redirect, $magic_redirect, $user );
			self::$handling_login_redirect = null;

			$redirect = wp_validate_redirect( (string) $redirect, $magic_redirect ? $magic_redirect : admin_url() );
			wp_safe_redirect( $redirect );
			exit;
		}

		/**
		 * @param string             $redirect_to           Redirect URL.
		 * @param string             $requested_redirect_to Requested redirect.
		 * @param \WP_User|\WP_Error $user                  User.
		 * @return string
		 */
		public function finalize_magic_link_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
			if ( ! self::$handling_login_redirect || ! ( $user instanceof \WP_User ) ) {
				return $redirect_to;
			}

			$checkout_redirect = Magic_Link_Service::sanitize_redirect_to( (string) $requested_redirect_to );
			if ( '' !== $checkout_redirect ) {
				if ( ! Magic_Link_Settings::should_skip_two_factor()
					&& $this->magic_link_login_requires_2fa( $user )
					&& class_exists( '\AIO_Login_Pro\Two_Factor\Two_Factor_Auth' ) ) {
					return \AIO_Login_Pro\Two_Factor\Two_Factor_Auth::get_verification_url( $checkout_redirect );
				}
				return $checkout_redirect;
			}

			if ( Magic_Link_Settings::should_skip_two_factor() ) {
				return $this->resolve_dashboard_redirect( $user );
			}

			// Always land on the 2FA challenge when the user has 2FA configured and Skip 2FA is off.
			if ( $this->magic_link_login_requires_2fa( $user ) && class_exists( '\AIO_Login_Pro\Two_Factor\Two_Factor_Auth' ) ) {
				$post_2fa_dest = $redirect_to;
				if ( is_string( $post_2fa_dest ) && false !== strpos( $post_2fa_dest, 'aio_login_pro__two_factor_auth' ) ) {
					// Already a challenge URL from maybe_redirect_to_tfa — keep it.
					return $post_2fa_dest;
				}
				return \AIO_Login_Pro\Two_Factor\Two_Factor_Auth::get_verification_url( $post_2fa_dest );
			}

			return $redirect_to;
		}

		/**
		 * @param string $message Existing login message.
		 * @return string
		 */
		public function login_error_message( $message ) {
			if ( empty( $_GET[ self::ERROR_QUERY_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return $message;
			}

			$code = sanitize_key( wp_unslash( $_GET[ self::ERROR_QUERY_ARG ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$text = Magic_Link_Service::get_error_message( $code );

			return $message . '<p class="message aio-login-magic-link-notice">' . esc_html( $text ) . '</p>';
		}

		/**
		 * @param \WP_User $user User.
		 * @return bool
		 */
		private function magic_link_login_requires_2fa( $user ) {
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
		 * @param \WP_User $user User.
		 */
		private function skip_two_factor_for_magic_link( $user ) {
			if ( ! Magic_Link_Settings::should_skip_two_factor() ) {
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
		 * @param \WP_User $user User.
		 * @return string
		 */
		private function resolve_dashboard_redirect( $user ) {
			$redirect = admin_url();
			if ( class_exists( Login_Redirection::class ) ) {
				$redirect = Login_Redirection::get_instance()->apply_login_redirection( $redirect, '', $user );
			}
			return wp_validate_redirect( (string) $redirect, admin_url() );
		}

		/**
		 * @return string
		 */
		/**
		 * Whether a new WP user may be created when requesting a login link.
		 *
		 * @return bool
		 */
		private function can_auto_create_account() {
			if ( ! Magic_Link_Settings::is_login_available() ) {
				return false;
			}
			/**
			 * Login link may create a new account for unknown emails (passwordless sign-up).
			 *
			 * @param bool   $allow Default true.
			 * @param string $email Email (empty during capability check).
			 */
			return (bool) apply_filters( 'aio_login_magic_link_allow_auto_register', true, '' );
		}

		/**
		 * @param string $role  Default role.
		 * @param string $email Email.
		 * @return string
		 */
		public function filter_new_user_role( $role, $email ) {
			unset( $email );
			if ( class_exists( 'WooCommerce' ) ) {
				return 'customer';
			}
			return $role;
		}

		private function get_not_registered_message() {
			$register_url = $this->get_registration_url();
			if ( $register_url ) {
				return __( 'You do not have an account yet. Please register first, then request a login link.', 'change-wp-admin-login' );
			}
			return __( 'You do not have an account yet. Please register first or contact the site administrator.', 'change-wp-admin-login' );
		}

		/**
		 * @return string
		 */
		private function get_registration_url() {
			if ( ! get_option( 'users_can_register' ) ) {
				return '';
			}
			if ( function_exists( 'wc_get_page_permalink' ) ) {
				$account = wc_get_page_permalink( 'myaccount' );
				if ( $account ) {
					return (string) $account;
				}
			}
			return (string) wp_registration_url();
		}

		private function verify_ajax_nonce() {
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), self::AJAX_NONCE ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'change-wp-admin-login' ) ), 403 );
			}
		}

		/**
		 * @return string SVG markup.
		 */
		private static function get_mail_icon_svg() {
			return '<svg class="aio-login-otp-launcher__svg" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">'
				. '<path d="M4 6.5h16c.83 0 1.5.67 1.5 1.5v8c0 .83-.67 1.5-1.5 1.5H4A1.5 1.5 0 012.5 16V8c0-.83.67-1.5 1.5-1.5z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>'
				. '<path d="M3 7.5l8.65 5.77a1.5 1.5 0 001.7 0L22 7.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>'
				. '</svg>';
		}
	}
}
