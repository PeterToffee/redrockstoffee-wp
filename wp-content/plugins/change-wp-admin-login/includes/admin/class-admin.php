<?php
/**
 * Class AIO_Login
 *
 * @package All In One Login
 */

namespace AIO_Login\Admin;

use AIO_Login\Helper\Helper;
use AIO_Login\Login_Controller\Failed_Logins;

defined('ABSPATH') || exit;

if (!class_exists('AIO_Login\Admin\Admin')) {
	/**
	 * Class Admin
	 */
	class Admin
	{

		/**
		 * Settings tabs.
		 *
		 * @var array $settings_tabs Settings tabs.
		 */
		private $settings_tabs = array();

		/**
		 * Admin constructor.
		 */
		private function __construct()
		{
			add_action('init', array($this, 'register_settings_tabs'), -3, 0);
			add_action('admin_init', array($this, 'redirect_login_customizer_on_admin_init'), 0);
			add_action('admin_menu', array($this, 'fix_customize_submenu_link' ), 999 );
			add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
			add_action('admin_enqueue_scripts', array($this, 'admin_mount_script'), 20);
			add_action('admin_menu', array($this, 'admin_menu'));
			add_filter('login_redirect', array($this, 'fix_subscriber_login_redirect'), 99, 3);
			add_filter('woocommerce_prevent_admin_access', array($this, 'allow_subscriber_admin_access'), 99);

			add_action('rest_api_init', array($this, 'rest_api_init'));

			$plugin_basename = plugin_basename(AIO_LOGIN__FILE);
			add_filter('plugin_action_links_' . $plugin_basename, array($this, 'plugin_action_links'), 99);
			add_filter('network_admin_plugin_action_links_' . $plugin_basename, array($this, 'plugin_action_links'), 99);
		}

		/**
		 * Plugins list "Get Pro" link (AIOL-695).
		 *
		 * @param array $links Existing action links.
		 * @return array
		 */
		public function plugin_action_links($links)
		{
			if (!is_array($links)) {
				$links = array();
			}

			$get_pro_html = '<a href="' . esc_url($this->get_pro_pricing_url()) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Get Pro', 'change-wp-admin-login') . '</a>';

			unset($links['upgrade'], $links['get-pro']);

			foreach ($links as $key => $link) {
				if (!is_string($link)) {
					continue;
				}

				$text = trim(wp_strip_all_tags($link));
				if (
					false !== stripos($text, 'Get Pro Bundle')
					|| 0 === strcasecmp($text, 'Upgrade')
					|| 0 === strcasecmp($text, 'Get Pro')
				) {
					unset($links[$key]);
				}
			}

			if ($this->should_show_get_pro_tab()) {
				$links['get-pro'] = $get_pro_html;
			}

			return $links;
		}

		/**
		 * Pricing URL used by the Get Pro plugin action link.
		 *
		 * @return string
		 */
		private function get_pro_pricing_url()
		{
			if (defined('AIO_LOGIN_GET_PRO_URL') && '' !== AIO_LOGIN_GET_PRO_URL) {
				return AIO_LOGIN_GET_PRO_URL;
			}

			return 'https://aiologin.com/pricing/?utm_source=plugn-redirect&utm_medium=upgrade-to-pro&utm_campaign=get-pro&utm_id=plugin';
		}

		/**
		 * Register_settings tabs.
		 */
		public function register_settings_tabs()
		{
			if (is_admin()) {
				// Tab `plan` keys match Pro: \AIO_Login_Pro\Plan\Plan_Features::meets_plan( 'basic'|'professional'|'business' ).
				$this->settings_tabs = array(
					'dashboard' => array(
						'title' => __('Dashboard', 'change-wp-admin-login'),
						'slug' => 'dashboard',
						'icon' => 'dashboard',
					),
					'login-protection' => array(
						'title' => __('Login Protection', 'change-wp-admin-login'),
						'slug' => 'login-protection',
						'icon' => 'login-protection',
						'sub-tabs' => array(
							'change-login-url' => array(
								'title' => __('Change Login URL', 'change-wp-admin-login'),
								'slug' => 'change-login-url',
							),
							'login-redirection' => array(
								'title' => __('Login Redirection', 'change-wp-admin-login'),
								'slug' => 'login-redirection',
							),
							'limit-login-attempts' => array(
								'title' => __('Limit Login Attempts', 'change-wp-admin-login'),
								'slug' => 'limit-login-attempts',
								'is-pro' => true,
								'plan' => 'professional',
							),
							'disable-common-usernames' => array(
								'title' => __('Disable Common Usernames', 'change-wp-admin-login'),
								'slug' => 'disable-common-usernames',
								'is-pro' => true,
								'plan' => 'basic',
							),
							'password-strenght-checker' => array(
								'title' => __('Password Strength Checker', 'change-wp-admin-login'),
								'slug' => 'password-strenght-checker',
								'is-pro' => true,
								'plan' => 'basic',
							),
						),
					),
					'2fa' => array(
						'title' => __('2FA', 'change-wp-admin-login'),
						'slug' => '2fa',
						'icon' => '2fa-icon',
						'is-pro' => true,
						'plan' => 'basic',
						'sub-tabs' => array(
							'authentication-methods' => array(
								'title' => __('Authentication Methods', 'change-wp-admin-login'),
								'slug' => 'authentication-methods',
							),
							'2fa-policies' => array(
								'title' => __('2FA Policies', 'change-wp-admin-login'),
								'slug' => '2fa-policies',
								'is-pro' => true,
								'plan' => 'professional',
							),
							'advanced-settings' => array(
								'title' => __('Advanced Settings', 'change-wp-admin-login'),
								'slug' => 'advanced-settings',
								'is-pro' => true,
								'plan' => 'professional',
							),
						),
					),
					'passwordless-authentication' => array(
						'title' => __('Passwordless Authentication', 'change-wp-admin-login'),
						'slug' => 'passwordless-authentication',
						'icon' => 'passwordless-authetication-icon',
						'sub-tabs' => array(
							'otp-login' => array(
								'title' => __('OTP Login', 'change-wp-admin-login'),
								'slug' => 'otp-login',
							),
							'login-link' => array(
								'title' => __('Login Link', 'change-wp-admin-login'),
								'slug' => 'login-link',
								'is-pro' => true,
								'plan' => 'business',
							),
						),
					),
					'activity-log' => array(
						'title' => __('Activity Log', 'change-wp-admin-login'),
						'slug' => 'activity-log',
						'icon' => 'activity-log',
						'sub-tabs' => array(
							'lockouts' => array(
								'title' => __('Lockouts', 'change-wp-admin-login'),
								'slug' => 'lockouts',
							),
							'failed-logins' => array(
								'title' => __('Failed Logins', 'change-wp-admin-login'),
								'slug' => 'failed-logins',
							),
							'enumeration-protection-logs' => array(
								'title' => __('Enumeration Protection Logs', 'change-wp-admin-login'),
								'slug' => 'enumeration-protection-logs',
								'is-pro' => true,
								'plan' => 'professional',
							),
							'notifications' => array(
								'title' => __('Notifications', 'change-wp-admin-login'),
								'slug' => 'notifications',
								'is-pro' => true,
								'plan' => 'professional',
							),
						),
					),
					'security' => array(
						'title' => __('Security', 'change-wp-admin-login'),
						'slug' => 'security',
						'icon' => 'security',
						'sub-tabs' => array(
							'captcha' => array(
								'title' => __('CAPTCHA', 'change-wp-admin-login'),
								'slug' => 'captcha',
							),
							'block-ip-addresses' => array(
								'title' => __('Ban / Whitelist IP Addresses', 'change-wp-admin-login'),
								'slug' => 'block-ip-addresses',
								'is-pro' => true,
								'plan' => 'basic',
							),
							'user-enumeration-protection' => array(
								'title' => __('User Enumeration Protection', 'change-wp-admin-login'),
								'slug' => 'user-enumeration-protection',
							),
						),
					),
					'temp-access' => array(
						'title' => __('Temporary Access', 'change-wp-admin-login'),
						'slug' => 'temp-access',
						'icon' => 'temp-access',
						'is-pro' => true,
						'plan' => 'business',
					),
					'social-login' => array(
						'title' => __('Social Login', 'change-wp-admin-login'),
						'slug' => 'social-login',
						'icon' => 'social-login',
						'is-pro' => true,
						'plan' => 'business',
					),
					'integrations' => array(
						'title' => __('Integrations', 'change-wp-admin-login'),
						'slug' => 'integrations',
						'icon' => 'integrations',
						'is-pro' => true,
						'plan' => 'business',
					),
				);

				$this->settings_tabs = apply_filters('aio_login__register_settings_tabs', $this->settings_tabs);

				if ($this->should_show_get_pro_tab()) {
					$this->settings_tabs['getpro'] = array(
						'title' => __('Get Pro', 'change-wp-admin-login'),
						'slug' => 'getpro',
						'icon' => 'getpro-icon',
					);
				}
			}
		}

		/**
		 * Admin_enqueue_scripts.
		 *
		 * @param string $hook Hook name.
		 */
		public function admin_enqueue_scripts($hook)
		{
			$is_limited_user = ! current_user_can('manage_options');

			$slack_oauth_return = add_query_arg(
				array(
					'page' => 'aio-login',
					'tab' => 'activity-log',
					'aio_slack_callback' => '1',
				),
				admin_url('admin.php')
			);
			$slack_oauth_base = apply_filters('aio_login_slack_oauth_base_url', 'https://slack.aiologin.com');
			$slack_connect_url = '';
			if (is_string($slack_oauth_base) && '' !== trim($slack_oauth_base)) {
				$slack_connect_url = add_query_arg(
					array(
						'install_application' => 'true',
						'redirect_to' => rawurlencode($slack_oauth_return),
					),
					$slack_oauth_base
				);
			}

			$login_customizer_url = admin_url( 'admin.php?page=aio-login' );
			if ( current_user_can( 'manage_options' ) ) {
				$login_customizer_url = $this->get_login_customizer_target_url();
			}

			$l10n = array(
				'tabs' => $this->settings_tabs,
				'version' => AIO_LOGIN__VERSION,
				'pro_version' => defined( 'AIO_LOGIN_PRO__VERSION' ) ? AIO_LOGIN_PRO__VERSION : '',
				'assets_url' => AIO_LOGIN__DIR_URL . 'assets/',
				'slack_connect_url' => $slack_connect_url,
				'admin_url' => add_query_arg(
					array(
						'page' => 'aio-login',
					),
					admin_url('admin.php')
				),
				'login_customizer_url' => $login_customizer_url,
				'nonce' => wp_create_nonce('wp_rest'),
				'rest_url' => is_ssl() ? set_url_scheme( rest_url(), 'https' ) : rest_url(),
				'ajax_url' => admin_url('admin-ajax.php'),
				'has_pro' => (\AIO_Login\Aio_Login::has_pro()) ? 'true' : 'false',
				'site_url' => site_url(),
				'is_limited_user' => $is_limited_user ? 'true' : 'false',
				'2fa_authenticator_allowed' => 'false',
				'2fa_professional_features_allowed' => 'false',
				'captcha_hcaptcha_plan_unlocked' => 'false',
				'captcha_turnstile_plan_unlocked' => 'false',
				'templates_premium_plan_unlocked' => 'false',
				'elements_google_fonts_plan_unlocked' => 'false',
				'upgrade_popup_variant' => ( function_exists( 'aiologin_pro_is_custom_license_runtime' ) && aiologin_pro_is_custom_license_runtime() )
					? 'appsumo'
					: 'freemius',
				'appsumo_deal_url'      => 'https://appsumo.8odi.net/DWbJmq',
				'show_appsumo_hello_bar' => 'false',
				'hello_bar_headline'          => __( 'Good News!', 'change-wp-admin-login' ),
				'hello_bar_message_before'    => __( 'All In One Login is live on ', 'change-wp-admin-login' ),
				'hello_bar_message_highlight' => __( 'AppSumo', 'change-wp-admin-login' ),
				'hello_bar_cta_label'         => __( 'Get Lifetime Deal Now', 'change-wp-admin-login' ),
				'hello_bar_aria_label'        => __( 'AppSumo promotion', 'change-wp-admin-login' ),
				'hello_bar_dismiss_label'     => __( 'Dismiss promotion', 'change-wp-admin-login' ),
				'i18n'                        => class_exists( '\\AIO_Login\\Admin\\Admin_I18n' ) ? \AIO_Login\Admin\Admin_I18n::strings() : array(),
			);

			if ($is_limited_user) {
				// Limited UI: only 2FA tab; do not set is-pro here (JS uses has_pro; is-pro forced the pro blur for non-admins).
				$l10n['tabs'] = array(
					'2fa' => array(
						'title' => __('2FA', 'change-wp-admin-login'),
						'slug'  => '2fa',
						'icon'  => '2fa-icon',
						'sub-tabs' => array(
							'authentication-methods' => array(
								'title' => __('Authentication Methods', 'change-wp-admin-login'),
								'slug'  => 'authentication-methods',
							),
						),
					),
				);
			}

			$l10n = apply_filters('aio_login__app_object_l10n', $l10n);

			if (get_option('permalink_structure')) {
				$l10n['site_link_login_url'] = trailingslashit(home_url());
			} else {
				$l10n['site_link_login_url'] = trailingslashit(home_url()) . '?';
			}
			$l10n['site_link_redirect_url'] = trailingslashit(home_url());
			$l10n['use_trailing_slashes'] = str_ends_with(get_option('permalink_structure'), '/') ? 'true' : 'false';


			$dependencies = array('wp-color-picker', 'wp-i18n', 'jquery');
			// Load Pro bundle first so window.aio_login_pro exists before the free app mounts (Pro components override demos).
			if (wp_script_is('aio-login-pro__app', 'registered')) {
				$dependencies[] = 'aio-login-pro__app';
			}

			wp_register_style('aio-login__app', AIO_LOGIN__DIR_URL . 'assets/css/app.css', array('wp-color-picker', 'dashicons'), ( file_exists( AIO_LOGIN__DIR_PATH . 'assets/css/app.css' ) ? (string) filemtime( AIO_LOGIN__DIR_PATH . 'assets/css/app.css' ) : AIO_LOGIN__VERSION ), 'all');
			$app_js_path = AIO_LOGIN__DIR_PATH . 'assets/js/app.js';
			$app_js_ver  = file_exists( $app_js_path ) ? (string) filemtime( $app_js_path ) : AIO_LOGIN__VERSION;
			wp_register_script('aio-login__app', AIO_LOGIN__DIR_URL . 'assets/js/app.js', $dependencies, $app_js_ver, true);

			if ('toplevel_page_aio-login' === $hook) {
				wp_register_script(
					'aio-login-turnstile-explicit',
					'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
					array(),
					null,
					true
				);
				wp_enqueue_script('aio-login-turnstile-explicit');
				wp_enqueue_media();

				wp_enqueue_style('aio-login__app');
				wp_enqueue_script('aio-login__app');
				wp_set_script_translations('aio-login__app', 'change-wp-admin-login', AIO_LOGIN__DIR_PATH . 'languages');
				wp_localize_script('aio-login__app', 'aio_login__app_object', $l10n);

			}
			if ($this->should_show_get_pro_tab()) {
				echo '<style type="text/css" id="aio-login__submenu-handler-styles">
					#toplevel_page_aio-login > ul li a[href*="tab=getpro"] {
						display: flex;
						align-items: center;
						gap: 8px;
						background-image: linear-gradient(270deg, #9516DF 0%, #510C79 100%);
						color: #fff !important;
						font-weight: 600;
					}
					#toplevel_page_aio-login > ul li a[href*="tab=getpro"]::before {
						content: "";
						display: inline-block;
						width: 17px;
						height: 12px;
						background: url("' . esc_url( AIO_LOGIN__DIR_URL . 'assets/images/pro-crown.svg' ) . '") center/contain no-repeat;
					}
				</style>';
			}

			// Ensure AIO Login menu icon matches WP dashicon size (fix alignment).
			echo '<style type="text/css" id="aio-login__menu-icon-alignment">
				#adminmenu #toplevel_page_aio-login .wp-menu-image {
					display: flex;
					align-items: center;
					justify-content: center;
				}
				#adminmenu #toplevel_page_aio-login .wp-menu-image img {
					display: block !important;
					margin-top: -8px !important;
					width: 24px !important;
					height: 24px !important;
					object-fit: contain !important;
				}
				/* Customize submenu: highlight row (icon + tint + bar) so it stands out without purple-only text. */
				#adminmenu #toplevel_page_aio-login .wp-submenu li a[href*="customize.php"] {
					display: flex;
					align-items: center;
					gap: 6px;
					margin: 5px 8px 5px 0;
					padding: 6px 10px 6px 10px !important;
					font-weight: 600;
					color: #f0f0f1 !important;
					background: linear-gradient(90deg, rgba(110, 22, 223, 0.35) 0%, rgba(110, 22, 223, 0.12) 55%, transparent 100%) !important;
					border: 1px solid rgba(192, 132, 252, 0.45);
					border-radius: 4px;
					box-shadow: inset 3px 0 0 #c084fc;
				}
				#adminmenu #toplevel_page_aio-login .wp-submenu li a[href*="customize.php"]::before {
					content: "\f540";
					font-family: dashicons;
					font-size: 17px;
					line-height: 1;
					width: 20px;
					text-align: center;
					color: #e9d5ff;
					-webkit-font-smoothing: antialiased;
					-moz-osx-font-smoothing: grayscale;
				}
				#adminmenu #toplevel_page_aio-login .wp-submenu li a[href*="customize.php"]:hover,
				#adminmenu #toplevel_page_aio-login .wp-submenu li a[href*="customize.php"]:focus {
					color: #fff !important;
					background: linear-gradient(90deg, rgba(110, 22, 223, 0.5) 0%, rgba(110, 22, 223, 0.22) 55%, rgba(110, 22, 223, 0.08) 100%) !important;
					border-color: rgba(233, 213, 255, 0.65);
				}
				#adminmenu #toplevel_page_aio-login.wp-has-current-submenu .wp-submenu li.current a[href*="customize.php"],
				#adminmenu #toplevel_page_aio-login .wp-submenu li.current a[href*="customize.php"] {
					color: #fff !important;
					background: linear-gradient(90deg, rgba(110, 22, 223, 0.55) 0%, rgba(110, 22, 223, 0.2) 100%) !important;
					border-color: rgba(233, 213, 255, 0.75);
				}
			</style>';
			echo '<script>
				document.addEventListener("DOMContentLoaded", function () {
					var li = document.getElementById("toplevel_page_aio-login");
					if (!li) return;
					var isCurrent = li.classList.contains("current") || li.classList.contains("wp-has-current-submenu");
					var img = li.querySelector(".wp-menu-image img");
					if (!img) return;
					img.src = isCurrent
						? "' . esc_js( AIO_LOGIN__DIR_URL . 'assets/images/icons/aio-login-menu.svg' ) . '"
						: "' . esc_js( AIO_LOGIN__DIR_URL . 'assets/images/icons/aio-login-menu-grey.svg' ) . '";
				});
			</script>';
		}

		/**
		 * Admin enqueue scripts
		 *
		 * @param string $hook Page hook.
		 */
		public function admin_mount_script($hook)
		{
			if ('toplevel_page_aio-login' === $hook) {
				wp_add_inline_script(
					'aio-login-dist',
					'window.$aioLogin.aioLoginApp.mount( "#aio-login__app" )',
					'after'
				);
			}
		}

		/**
		 * Whether to hide the AIO Login admin menu for users without manage_options.
		 * Show only when Pro is active, premium license is valid, and the 2FA module master switch is on.
		 *
		 * @return bool
		 */
		private function should_hide_aio_menu_for_limited_user()
		{
			if (!class_exists('\AIO_Login\Aio_Login') || !\AIO_Login\Aio_Login::has_pro()) {
				return true;
			}
			if (function_exists('aiologin_has_active_license') && !aiologin_has_active_license()) {
				return true;
			}
			if (!class_exists('\AIO_Login_Pro\Two_Factor\Two_Factor')) {
				return true;
			}
			return !\AIO_Login_Pro\Two_Factor\Two_Factor::is_2fa_module_master_enabled();
		}

		/**
		 * Admin_menu.
		 */
		public function admin_menu()
		{
			$is_limited_user = ! current_user_can('manage_options');

			if ($is_limited_user && $this->should_hide_aio_menu_for_limited_user()) {
				return;
			}

			add_menu_page(
				__('All in One Login', 'change-wp-admin-login'),
				__('AIO Login', 'change-wp-admin-login'),
				'read',
				'aio-login',
				array($this, 'admin_page'),
				AIO_LOGIN__DIR_URL . 'assets/images/icons/aio-login-menu-grey.svg'
			);

			if ($is_limited_user) {
				// For limited users, make the only submenu be the parent slug (no query args here).
				// admin_page() already forces tab=2fa for limited users.
				add_submenu_page(
					'aio-login',
					__('2FA', 'change-wp-admin-login'),
					__('2FA', 'change-wp-admin-login'),
					'read',
					'aio-login',
					array($this, 'admin_page')
				);
				return;
			}

			add_submenu_page(
				'aio-login',
				__('Dashboard', 'change-wp-admin-login'),
				__('Dashboard', 'change-wp-admin-login'),
				'read',
				'aio-login',
				array($this, 'admin_page')
			);

			$tabs = $this->settings_tabs;
			$customize_added = false;

			foreach ($tabs as $tab) {
				if ('dashboard' === $tab['slug']) {
					continue;
				}

				add_submenu_page(
					'aio-login',
					$tab['title'],
					$tab['title'],
					'read',
					'admin.php?page=aio-login&tab=' . $tab['slug']
				);

				// Keep Customize directly below Integrations in WP admin submenu.
				if ('integrations' === $tab['slug']) {
					$this->register_login_customize_submenu();
					$customize_added = true;
				}
			}

			// Fallback if Integrations tab is unavailable via filters/plan conditions.
			if (!$customize_added) {
				$this->register_login_customize_submenu();
			}
		}

		/**
		 * WP admin submenu: open login page in Customizer (handler enforces Pro / caps).
		 */
		private function register_login_customize_submenu() {
			add_submenu_page(
				'aio-login',
				__( 'Customize', 'change-wp-admin-login' ),
				__( 'Customize', 'change-wp-admin-login' ),
				'read',
				'aio-login-customize',
				array( $this, 'open_login_customizer' )
			);
		}

		/**
		 * Point AIO Login → Customize submenu at customize.php directly (opens AIO Login Customize panel).
		 * Avoids stopping on admin.php?page=aio-login-customize; WP treats full http(s) URLs as link hrefs.
		 */
		public function fix_customize_submenu_link() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			global $submenu;
			if ( empty( $submenu['aio-login'] ) || ! is_array( $submenu['aio-login'] ) ) {
				return;
			}
			foreach ( $submenu['aio-login'] as $i => $item ) {
				if ( isset( $item[2] ) && 'aio-login-customize' === $item[2] ) {
					$submenu['aio-login'][ $i ][2] = $this->get_login_customizer_target_url();
					break;
				}
			}
		}

		/**
		 * Full customize.php URL for wp-login preview + AIO Login panel (same for redirect and submenu link).
		 *
		 * @return string
		 */
		private function get_login_customizer_target_url() {
			$preview_url = add_query_arg(
				array(
					'aio_login_customizer_preview' => '1',
				),
				site_url( 'wp-login.php' )
			);

			$return_after_close = admin_url( 'admin.php?page=aio-login' );

			// Nested `autofocus` so customize.php receives $_REQUEST['autofocus'] as an array. Open the
			// AIO Login Customize panel only (not a subsection like Themes).
			return add_query_arg(
				array(
					'aio_login_customizer' => '1',
					'url'                  => $preview_url,
					'return'               => $return_after_close,
					'autofocus'            => array(
						'panel' => 'aio_login_customizer',
					),
				),
				admin_url( 'customize.php' )
			);
		}

		/**
		 * Redirect before any admin HTML (fonts, notices) so wp_safe_redirect() succeeds.
		 * The submenu callback runs too late and triggers "headers already sent" on some setups.
		 */
		public function redirect_login_customizer_on_admin_init() {
			if ( ! isset( $_GET['page'] ) || 'aio-login-customize' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}

			$this->do_login_customizer_redirect();
		}

		/**
		 * Open WP Customizer for wp-login.php preview (Pro feature).
		 * Fallback only if admin_init did not run first; normally redirects from admin_init.
		 */
		public function open_login_customizer() {
			$this->do_login_customizer_redirect();
		}

		/**
		 * Perform redirect for the Customize submenu (login Customizer ships with the free plugin).
		 */
		private function do_login_customizer_redirect() {
			if ( ! current_user_can( 'read' ) ) {
				wp_die( esc_html__( 'You do not have permission to access this page.', 'change-wp-admin-login' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				$this->safe_redirect_exit( admin_url( 'admin.php?page=aio-login' ) );
			}

			$this->safe_redirect_exit( $this->get_login_customizer_target_url() );
		}

		/**
		 * Redirect or JS fallback when headers are already sent.
		 *
		 * @param string $url Destination URL.
		 */
		private function safe_redirect_exit( $url ) {
			if ( headers_sent() ) {
				echo '<script>window.location.href=' . wp_json_encode( $url ) . ';</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				exit;
			}

			wp_safe_redirect( $url );
			exit;
		}

		/**
		 * Show "Get Pro" for free installs, or Pro without an active license.
		 * Hide when Pro is active and any valid license is active (Freemius or custom).
		 *
		 * @return bool
		 */
		private function should_show_get_pro_tab()
		{
			if ( ! \AIO_Login\Aio_Login::has_pro() ) {
				return true;
			}

			if ( function_exists( 'aiologin_has_active_license' ) && aiologin_has_active_license() ) {
				return false;
			}

			return true;
		}

		/**
		 * Admin_page.
		 */
		public function admin_page()
		{
			// Old in-app Customize tab removed — send bookmarks to Dashboard.
			if ( isset( $_GET['tab'] ) && 'customization' === $_GET['tab'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$target = admin_url( 'admin.php?page=aio-login' );
				if ( headers_sent() ) {
					echo '<script>window.location.href=' . wp_json_encode( $target ) . ';</script>';
					exit;
				}
				wp_safe_redirect( $target );
				exit;
			}

			$is_limited_user = ! current_user_can('manage_options');

			if ($is_limited_user && $this->should_hide_aio_menu_for_limited_user()) {
				$target = admin_url('index.php');
				if (headers_sent()) {
					echo '<script>window.location.href=' . wp_json_encode($target) . ';</script>';
					exit;
				}
				wp_safe_redirect($target);
				exit;
			}

			// For limited users, always force URL to 2FA so Vue doesn't default to Dashboard.
			if ($is_limited_user) {
				$requested_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';

				if ('2fa' !== $requested_tab) {
					$target = admin_url('admin.php?page=aio-login&tab=2fa');
					if (headers_sent()) {
						echo '<script>window.location.href=' . wp_json_encode($target) . ';</script>';
						exit;
					}
					wp_safe_redirect($target);
					exit;
				}
			}

			$settings_tab = $this->settings_tabs;
			$setting_tab_slug = $is_limited_user ? '2fa' : 'dashboard';
			$settings_sub_tab = array();
			$setting_sub_tab_slug = '';

			if (isset($_GET['tab']) && !empty($_GET['tab'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Arrays.ArrayKeySpacingRestrictions.SpacesAroundArrayKeys
				$setting_tab_slug = sanitize_text_field(wp_unslash($_GET['tab'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			if ($is_limited_user && '2fa' !== $setting_tab_slug) {
				$setting_tab_slug = '2fa';
			}

			if ( ! $is_limited_user && 'getpro' === $setting_tab_slug && ! $this->should_show_get_pro_tab() ) {
				$target = admin_url( 'admin.php?page=aio-login' );
				if ( headers_sent() ) {
					echo '<script>window.location.href=' . wp_json_encode( $target ) . ';</script>';
					exit;
				}
				wp_safe_redirect( $target );
				exit;
			}

			$tabs_source = $this->settings_tabs;
			if ($is_limited_user) {
				$tabs_source = array(
					'2fa' => array(
						'title' => __('2FA', 'change-wp-admin-login'),
						'slug'  => '2fa',
						'icon'  => '2fa-icon',
						'sub-tabs' => array(
							'authentication-methods' => array(
								'title' => __('Authentication Methods', 'change-wp-admin-login'),
								'slug'  => 'authentication-methods',
							),
						),
					),
				);
			}
			if (isset($tabs_source[$setting_tab_slug]['sub-tabs']) && !empty($tabs_source[$setting_tab_slug]['sub-tabs'])) {
				$setting_sub_tab_slug = array_key_first($tabs_source[$setting_tab_slug]['sub-tabs']);
				$settings_sub_tab = $tabs_source[$setting_tab_slug]['sub-tabs'];
			}

			if (isset($_GET['sub-tab']) && !empty($_GET['sub-tab'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$setting_sub_tab_slug = sanitize_text_field(wp_unslash($_GET['sub-tab'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			if ($is_limited_user && 'authentication-methods' !== $setting_sub_tab_slug) {
				$setting_sub_tab_slug = 'authentication-methods';
			}

			require_once AIO_LOGIN__DIR_PATH . 'includes/admin/settings/admin.php';
		}

		public function rest_api_init()
		{
			register_rest_route(
				'aio-login/dashboard',
				'/get-settings',
				array(
					'methods' => 'GET',
					'callback' => array($this, 'get_settings'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			register_rest_route(
				'aio-login/dashboard',
				'/get-counts',
				array(
					'methods' => 'GET',
					'callback' => array($this, 'get_counts'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			register_rest_route(
				'aio-login/dashboard/update',
				'/limit-login-attempts',
				array(
					'methods' => 'POST',
					'callback' => array($this, 'update_limit_login_attempts'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			register_rest_route(
				'aio-login/dashboard/update',
				'/two-factor-authentication',
				array(
					'methods' => 'POST',
					'callback' => array($this, 'update_two_factor_authentication'),
					'permission_callback' => array($this, 'check_2fa_permission'),
				)
			);

			// Limited 2FA status endpoint for logged-in users.
			register_rest_route(
				'aio-login/security',
				'/2fa-status',
				array(
					'methods' => 'GET',
					'callback' => array($this, 'get_2fa_status'),
					'permission_callback' => array($this, 'check_2fa_permission'),
				)
			);

			register_rest_route(
				'aio-login/dashboard/update',
				'/block-ip-address',
				array(
					'methods' => 'POST',
					'callback' => array($this, 'update_block_ip_address'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			register_rest_route(
				'aio-login/dashboard/logs',
				'/lockouts',
				array(
					'methods' => 'GET',
					'callback' => array($this, 'get_lockouts'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			register_rest_route(
				'aio-login/dashboard/logs',
				'/failed-logins',
				array(
					'methods' => 'GET',
					'callback' => array($this, 'get_failed_logins'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			// User Enumeration Protection endpoints
			register_rest_route(
				'aio-login/dashboard',
				'/user-enumeration-settings',
				array(
					'methods' => 'GET',
					'callback' => array($this, 'get_user_enumeration_settings'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			register_rest_route(
				'aio-login/dashboard/update',
				'/user-enumeration-settings',
				array(
					'methods' => 'POST',
					'callback' => array($this, 'update_user_enumeration_settings'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			// Activity Log Settings endpoints
			register_rest_route(
				'aio-login/dashboard',
				'/activity-log-settings',
				array(
					'methods' => 'GET',
					'callback' => array($this, 'get_activity_log_settings'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			register_rest_route(
				'aio-login/dashboard/update',
				'/activity-log-settings',
				array(
					'methods' => 'POST',
					'callback' => array($this, 'update_activity_log_settings'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);
		}

		public function get_settings()
		{
			$is_admin = current_user_can('manage_options');
			$current_user_id = get_current_user_id();

			$limit_login = get_option('aio_login_limit_attempts_enable', 'off');
			if ( class_exists( '\AIO_Login_Pro\Plan\Plan_Features' ) && ! \AIO_Login_Pro\Plan\Plan_Features::can( 'limit_login_attempts' ) ) {
				$limit_login = 'off';
			}

			$block_ip = get_option('aio_login_block_ip_address_enable', 'off');
			if ( class_exists( '\AIO_Login_Pro\Plan\Plan_Features' ) && ! \AIO_Login_Pro\Plan\Plan_Features::can( 'security_ip_whitelist' ) ) {
				$block_ip = 'off';
			}

			$settings = array(
				'limit_login_attempts' => $limit_login,
				// Admin controls global 2FA module; other users control their own 2FA.
				'two_factor_auth' => $is_admin ? get_option('aio_login_pro__two_factor_auth_enable', 'off') : get_user_meta($current_user_id, 'aio_login_pro__two_factor_auth_enable', true),
				'block_ip_address' => $block_ip,
			);

			return rest_ensure_response($settings);
		}

		public function get_lockouts()
		{
			$logs = Helper::get_logs('lockout');

			$logs = array_slice($logs, 0, 5);

			$logs = array_map(
				function ($log) {
					$log['time'] = date('F j, Y, g:i a', $log['time']);
					return $log;
				},
				$logs
			);

			return rest_ensure_response($logs);
		}

		public function get_failed_logins()
		{
			$logs = Helper::get_logs('failed');

			$logs = array_slice($logs, 0, 5);

			$logs = array_map(
				function ($log) {
					$log['time'] = date('F j, Y, g:i a', $log['time']);
					return $log;
				},
				$logs
			);

			return rest_ensure_response($logs);
		}

		public function get_counts($request)
		{
			$type = 'success';
			$duration = 'today';

			if (isset($request['type']) && !empty($request['type'])) {
				$type = sanitize_text_field(wp_unslash($request['type']));
			}

			if (isset($request['duration']) && !empty($request['duration'])) {
				$duration = sanitize_text_field(wp_unslash($request['duration']));
			}

			if ('lockouts' === $type) {
				$count = Failed_Logins::get_lockout_attempts_count($duration);
			} else {
				$count = Failed_Logins::get_attempts_count($type, $duration);
			}

			return rest_ensure_response(array('count' => absint($count)));
		}

		public function update_limit_login_attempts($request)
		{
			$params = $request->get_params();

			if ( class_exists( '\AIO_Login_Pro\Plan\Plan_Features' ) && ! \AIO_Login_Pro\Plan\Plan_Features::can( 'limit_login_attempts' ) ) {
				return new \WP_Error(
					'plan_restricted',
					__( 'Your plan does not include Limit Login Attempts.', 'change-wp-admin-login' ),
					array( 'status' => 403 )
				);
			}

			if (isset($params['value'])) {
				$value = sanitize_text_field(wp_unslash($params['value']));
				$value = 'on' === $value ? 'on' : 'off';

				update_option('aio_login_limit_attempts_enable', $value);

				return rest_ensure_response(
					array(
						'status' => 'success',
						'message' => __('Limit login attempts updated successfully', 'change-wp-admin-login'),
					)
				);
			}

			return new \WP_Error(
				'invalid_value',
				__('Invalid value', 'change-wp-admin-login'),
				array('status' => 400)
			);
		}

		public function update_two_factor_authentication($request)
		{
			$params = $request->get_params();

			if (isset($params['value'])) {
				$value = sanitize_text_field(wp_unslash($params['value']));
				$value = 'on' === $value ? 'on' : 'off';

				if (current_user_can('manage_options')) {
					update_option('aio_login_pro__two_factor_auth_enable', $value);
				} else {
					update_user_meta(get_current_user_id(), 'aio_login_pro__two_factor_auth_enable', $value);
				}

				return rest_ensure_response(
					array(
						'status' => 'success',
						'message' => __('Two factor authentication updated successfully', 'change-wp-admin-login'),
					)
				);
			}

			return new \WP_Error(
				'invalid_value',
				__('Invalid value', 'change-wp-admin-login'),
				array('status' => 400)
			);
		}

		public function check_2fa_permission() {
			return is_user_logged_in();
		}

		public function get_2fa_status() {
			$user_id = get_current_user_id();

			return rest_ensure_response(array(
				'global_enabled' => get_option('aio_login_pro__two_factor_auth_enable', 'off'),
				'user_enabled'   => get_user_meta($user_id, 'aio_login_pro__two_factor_auth_enable', true),
			));
		}

		public function update_block_ip_address($request)
		{
			$params = $request->get_params();

			if ( class_exists( '\AIO_Login_Pro\Plan\Plan_Features' ) && ! \AIO_Login_Pro\Plan\Plan_Features::can( 'security_ip_whitelist' ) ) {
				return new \WP_Error(
					'plan_restricted',
					__( 'Your plan does not include Ban / Whitelist IP Addresses.', 'change-wp-admin-login' ),
					array( 'status' => 403 )
				);
			}

			if (isset($params['value'])) {
				$value = sanitize_text_field(wp_unslash($params['value']));
				$value = 'on' === $value ? 'on' : 'off';

				update_option('aio_login_block_ip_address_enable', $value);

				return rest_ensure_response(
					array(
						'status' => 'success',
						'message' => __('Block IP address updated successfully', 'change-wp-admin-login'),
					)
				);
			}

			return new \WP_Error(
				'invalid_value',
				__('Invalid value', 'change-wp-admin-login'),
				array('status' => 400)
			);
		}

		/**
		 * Get user enumeration protection settings
		 */
		public function get_user_enumeration_settings()
		{
			// Direct check for pro plugin
			$has_pro = false;

			// Check if pro plugin is active
			if (function_exists('is_plugin_active')) {
				$has_pro = is_plugin_active('aio-login-pro/aio-login-pro.php');
			}

			// If not found via is_plugin_active, check if class exists
			if (!$has_pro && class_exists('AIO_Login_Pro\\AIO_Login_Pro')) {
				$has_pro = true;
			}

			// Also check the filter
			if (!$has_pro) {
				$has_pro = apply_filters('aio_login_has_pro', false);
			}

			$log_duration = get_option('aio_login_user_enumeration_duration', 30);

			$settings = array(
				'enable_protection' => get_option('aio_login_user_enumeration_enable', 'off'),
				'stop_oembed_calls' => get_option('aio_login_user_enumeration_oembed', 'off'),
				'disable_author_sitemaps' => get_option('aio_login_user_enumeration_sitemaps', 'off'),
				'remove_comment_numbers' => get_option('aio_login_user_enumeration_comments', 'off'),
				'protect_rest_api' => get_option('aio_login_user_enumeration_rest_api', 'off'),
				'login_registration_errors' => get_option('aio_login_user_enumeration_login_registration', 'off'),
				'log_attempts' => get_option('aio_login_user_enumeration_log', 'off'),
				'log_duration' => $log_duration,
				'has_pro' => $has_pro ? 'true' : 'false',
			);

			return rest_ensure_response(array('success' => true, 'data' => $settings));
		}

		/**
		 * Update user enumeration protection settings
		 */
		public function update_user_enumeration_settings($request)
		{
			$params = $request->get_params();
			$settings = isset($params['settings']) ? $params['settings'] : array();

			// Update settings
			$enable_protection = isset($settings['enable_protection']) && ($settings['enable_protection'] === true || $settings['enable_protection'] === 'on') ? 'on' : 'off';
			$stop_oembed_calls = isset($settings['stop_oembed_calls']) && ($settings['stop_oembed_calls'] === true || $settings['stop_oembed_calls'] === 'on') ? 'on' : 'off';
			$disable_author_sitemaps = isset($settings['disable_author_sitemaps']) && ($settings['disable_author_sitemaps'] === true || $settings['disable_author_sitemaps'] === 'on') ? 'on' : 'off';
			$remove_comment_numbers = isset($settings['remove_comment_numbers']) && ($settings['remove_comment_numbers'] === true || $settings['remove_comment_numbers'] === 'on') ? 'on' : 'off';
			$protect_rest_api = isset($settings['protect_rest_api']) && ($settings['protect_rest_api'] === true || $settings['protect_rest_api'] === 'on') ? 'on' : 'off';
			$login_registration_errors = isset($settings['login_registration_errors']) && ($settings['login_registration_errors'] === true || $settings['login_registration_errors'] === 'on') ? 'on' : 'off';
			// Only update log settings if they are provided, otherwise preserve existing values
			$log_attempts = isset($settings['log_attempts']) ?
				(($settings['log_attempts'] === true || $settings['log_attempts'] === 'on') ? 'on' : 'off') :
				get_option('aio_login_user_enumeration_log', 'off');
			$log_duration = isset($settings['log_duration']) ?
				intval($settings['log_duration']) :
				get_option('aio_login_user_enumeration_duration', 30);

			update_option('aio_login_user_enumeration_enable', $enable_protection);
			update_option('aio_login_user_enumeration_oembed', $stop_oembed_calls);
			update_option('aio_login_user_enumeration_sitemaps', $disable_author_sitemaps);
			update_option('aio_login_user_enumeration_comments', $remove_comment_numbers);
			update_option('aio_login_user_enumeration_rest_api', $protect_rest_api);
			update_option('aio_login_user_enumeration_login_registration', $login_registration_errors);
			update_option('aio_login_user_enumeration_log', $log_attempts);
			update_option('aio_login_user_enumeration_duration', $log_duration);

			return rest_ensure_response(array('success' => true));
		}

		/**
		 * Get activity log settings
		 */
		public function get_activity_log_settings()
		{
			$settings = array(
				'log_enumeration_attempts' => get_option('aio_login_user_enumeration_log', 'off'),
				'log_enumeration_duration' => get_option('aio_login_user_enumeration_duration', 30),
			);

			$response = array(
				'success' => true,
				'data' => $settings,
				'nonce' => wp_create_nonce('aio_login_activity_log_settings'),
			);

			return rest_ensure_response($response);
		}

		/**
		 * Update activity log settings
		 */
		public function update_activity_log_settings($request)
		{
			$params = $request->get_params();
			$settings = isset($params['settings']) ? $params['settings'] : array();

			// Update settings
			$log_enumeration_attempts = isset($settings['log_enumeration_attempts']) && ($settings['log_enumeration_attempts'] === true || $settings['log_enumeration_attempts'] === 'on') ? 'on' : 'off';
			$log_enumeration_duration = isset($settings['log_enumeration_duration']) ? intval($settings['log_enumeration_duration']) : 30;

			update_option('aio_login_user_enumeration_log', $log_enumeration_attempts);
			update_option('aio_login_user_enumeration_duration', $log_enumeration_duration);

			return rest_ensure_response(array('success' => true));
		}

		/**
		 * Default + normalized notification event flags (lockout is always on).
		 *
		 * @param string $option_name Option storing JSON.
		 * @return array{lockout:bool,failed_login:bool,user_enumeration:bool}
		 */
		/**
		 * Keep dashboard login redirect working for limited roles.
		 *
		 * @param string           $redirect_to Requested redirect URL.
		 * @param string           $requested_redirect_to Raw redirect request value.
		 * @param \WP_User|\WP_Error $user Logged in user object.
		 *
		 * @return string
		 */
		public function fix_subscriber_login_redirect($redirect_to, $requested_redirect_to, $user)
		{
			if (!($user instanceof \WP_User)) {
				return $redirect_to;
			}

			if (user_can($user, 'manage_options') || !user_can($user, 'read')) {
				return $redirect_to;
			}
			
			// Force limited roles to WP dashboard after login.
			return admin_url();
		}

		/**
		 * Prevent WooCommerce from forcing subscribers to My Account.
		 *
		 * @param bool $prevent_access Whether Woo should block wp-admin.
		 *
		 * @return bool
		 */
		public function allow_subscriber_admin_access($prevent_access)
		{
			if (!is_user_logged_in()) {
				return $prevent_access;
			}

			$current_user = wp_get_current_user();
			if (!($current_user instanceof \WP_User)) {
				return $prevent_access;
			}

			if (in_array('subscriber', (array) $current_user->roles, true)) {
				return false;
			}

			return $prevent_access;
		}

		/**
		 * Getting instance of class.
		 *
		 * @return Admin
		 */
		public static function get_instance()
		{
			static $instance = null;

			if (is_null($instance)) {
				$instance = new self();
			}

			return $instance;
		}
	}
}
