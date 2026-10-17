<?php
/**
 * Class Change_WP_Admin_Login
 *
 * @package AIO Login
 */

namespace AIO_Login\Change_WP_Admin_Login;

use AIO_Login\Helper\Helper;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Change_WP_Admin_Login\\Change_WP_Admin_Login' ) ) {
	/**
	 * Class Change_WP_Admin_Login
	 */
	class Change_WP_Admin_Login {
		/**
		 * WP login php.
		 *
		 * @var string $wp_login_php WP login php.
		 */
		private $wp_login_php;

		/**
		 * Get plugin base name.
		 *
		 * @return string
		 */
		private function basename() {
			return plugin_basename( __FILE__ );
		}

		/**
		 * Use trailing slashes.
		 *
		 * @return bool
		 */
		private function use_trailing_slashes() {
			return str_ends_with( get_option( 'permalink_structure' ), '/' );
		}

		/**
		 * User trailingslashit.
		 *
		 * @param string $str URL.
		 *
		 * @return string
		 */
		private function user_trailingslashit( $str ) {
			return $this->use_trailing_slashes() ? trailingslashit( $str ) : untrailingslashit( $str );
		}

		/**
		 * WP template loader.
		 */
		private function wp_template_loader() {
			global $pagenow;

			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$pagenow = 'index.php';

			if ( ! defined( 'WP_USE_THEMES' ) ) {
				define( 'WP_USE_THEMES', true );
			}

			wp();

			if ( $_SERVER['REQUEST_URI'] === $this->user_trailingslashit( str_repeat( '-/', 10 ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
				$_SERVER['REQUEST_URI'] = $this->user_trailingslashit( '/wp-login-php/' );
			}

			require_once ABSPATH . WPINC . '/template-loader.php';

			die;
		}

		/**
		 * New login slug.
		 *
		 * @return string
		 */
		private function new_login_slug() {
			if ( get_option( 'rwl_page' ) ) {
				$slug = get_option( 'rwl_page' );
			}

			if ( is_multisite() && is_plugin_active_for_network( $this->basename() ) ) {
				$slug = get_site_option( 'rwl_page', 'login' );
			}

			if ( empty( $slug ) ) {
				$slug = 'login';
			}

			return $slug;
		}

		/**
		 * New login url.
		 *
		 * @param string $scheme Scheme.
		 *
		 * @return string
		 */
		public function new_login_url( $scheme = null ) {
			if ( get_option( 'permalink_structure' ) ) {
				return $this->user_trailingslashit( home_url( '/', $scheme ) . $this->new_login_slug() );
			} else {
				return home_url( '/', $scheme ) . '?' . $this->new_login_slug();
			}
		}

		/**
		 * Change_WP_Admin_Login constructor.
		 */
		public function __construct() {
			global $wp_version;

			if ( version_compare( $wp_version, '4.0-RC1-src', '<' ) ) {
				add_action( 'admin_notices', array( $this, 'admin_notices_incompatible' ) );
				add_action( 'network_admin_notices', array( $this, 'admin_notices_incompatible' ) );

				return;
			}

			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'network_admin_notices', array( $this, 'admin_notices' ) );

			if ( is_multisite() && ! function_exists( 'is_plugin_active_for_network' ) ) {
				require_once ABSPATH . '/wp-admin/includes/plugin.php';
			}

			if ( is_multisite() && is_plugin_active_for_network( $this->basename() ) ) {

				add_action( 'wpmu_options', array( $this, 'wpmu_options' ) );
				add_action( 'update_wpmu_options', array( $this, 'update_wpmu_options' ) );
			}

			// we are adding a toggle switch to the aio-login settings page.
			if ( $this->is_cwpal_enabled() ) {
				add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 1 );
				add_action( 'wp_loaded', array( $this, 'wp_loaded' ) );
				add_filter( 'site_url', array( $this, 'site_url' ), 10, 4 );
				add_filter( 'network_site_url', array( $this, 'network_site_url' ), 10, 3 );
				add_filter( 'wp_redirect', array( $this, 'wp_redirect' ), 10, 2 );
				add_filter( 'site_option_welcome_email', array( $this, 'welcome_email' ) );
				remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
			}

			add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		}

		/**
		 * Is cwpal enabled.
		 *
		 * @return bool
		 */
		private function is_cwpal_enabled() {
			$enabled = get_option( 'aio_login__cwpal_enable' );

			return 'on' === $enabled;
		}

		/**
		 * Admin notices incompatible.
		 */
		public function admin_notices_incompatible() {
			echo '<div class="error">
				<p>'
					. sprintf(
						// translators: %1$s: plugin name.
						wp_kses_post( __( 'Please upgrade to the latest version of WordPress to activate %1$s.', 'change-wp-admin-login' ) ),
						'<strong>' . wp_kses_post( __( 'Change wp-admin login', 'change-wp-admin-login' ) ) . '</strong>'
					)
				. '</p>
			</div>';
		}

		/**
		 * Wpmu options.
		 */
		public function wpmu_options() {
			$out  = '<h3>' . __( 'Change wp-admin login', 'change-wp-admin-login' ) . '</h3>';
			$out .= '<p>' . __( 'This option allows you to set a networkwide default, which can be overridden by individual sites. Simply go to to the siteâ€™s permalink settings to change the url.', 'change-wp-admin-login' ) . '</p>';
			$out .= '<table class="form-table">';
			$out .= '<tr valign="top">';
			$out .= '<th scope="row">' . __( 'Networkwide default', 'change-wp-admin-login' ) . '</th>';
			$out .= '<td><input id="rwl-page-input" type="text" name="rwl_page" value="' . get_site_option( 'rwl_page', 'login' ) . '"></td>';
			$out .= '</tr>';
			$out .= '</table>';

			echo wp_kses_post( $out );
		}

		/**
		 * Update wpmu options.
		 */
		public function update_wpmu_options() {
			if ( isset( $_POST['rwl_page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$rwl_page = sanitize_title_with_dashes( wp_unslash( $_POST['rwl_page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
				if ( ! str_contains( $rwl_page, 'wp-login' ) && ! in_array( $rwl_page, $this->forbidden_slugs(), true ) ) {
					update_site_option( 'rwl_page', $rwl_page );
				}
			}
		}

		/**
		 * Admin init.
		 */
		public function admin_init() {
			global $pagenow;

			add_settings_section(
				'change-wp-admin-login-section',
				__( 'Change wp-admin login', 'change-wp-admin-login' ),
				array( $this, 'rwl_section_desc' ),
				'permalink'
			);

			add_settings_section(
				'aio_login__cwpal_sections',
				__( 'Change wp-admin login', 'change-wp-admin-login' ),
				array( $this, 'rwl_section_desc' ),
				'page=aio-login&tab=general'
			);

			add_settings_field(
				'aio_login__cwpal_enable',
				__( 'Enable', 'change-wp-admin-login' ),
				array( $this, 'rwl_enable_func' ),
				'page=aio-login&tab=general',
				'aio_login__cwpal_sections',
				array( 'label_for' => 'aio_login__cwpal_enable' )
			);

			add_settings_field(
				'rwl-page',
				'<label for="rwl-page">' . __( 'Login URL', 'change-wp-admin-login' ) . '</label>',
				array( $this, 'rwl_page_input' ),
				'permalink',
				'change-wp-admin-login-section'
			);

			// Add redirect field.
			add_settings_field(
				'rwl_redirect_field',
				__( 'Redirect URL', 'change-wp-admin-login' ),
				array( $this, 'rwl_redirect_func' ),
				'permalink',
				'change-wp-admin-login-section'
			);

			add_settings_field(
				'rwl-page',
				'<label for="rwl-page">' . __( 'Login URL', 'change-wp-admin-login' ) . '</label>',
				array( $this, 'rwl_page_input' ),
				'page=aio-login&tab=general',
				'aio_login__cwpal_sections'
			);

			add_settings_field(
				'rwl_redirect_field',
				__( 'Redirect URL', 'change-wp-admin-login' ),
				array( $this, 'rwl_redirect_func' ),
				'page=aio-login&tab=general',
				'aio_login__cwpal_sections'
			);

			register_setting( 'permalink', 'rwl_page_input' );
			register_setting( 'permalink', 'rwl_redirect_field' );

			register_setting( 'aio_login__cwpal_settings', 'aio_login__cwpal_enable', array( $this, 'sanitize_toggle_switch' ) );
			register_setting( 'aio_login__cwpal_settings', 'rwl_page_input', array( $this, 'sanitize_page_input' ) );
			register_setting( 'aio_login__cwpal_settings', 'rwl_redirect_field', array( $this, 'sanitize_redirect_field' ) );
			register_setting( 'aio_login__cwpal_settings', 'rwl_page', array( $this, 'sanitize_page_url' ) );

			if ( current_user_can( 'manage_options' ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-permalink' ) ) {
				if ( isset( $_POST['permalink_structure'] ) && isset( $_POST['rwl_redirect_field'] ) ) {
					$short_domain = sanitize_title_with_dashes( wp_unslash( $_POST['rwl_redirect_field'] ) );
					update_option( 'rwl_redirect_field', $short_domain );
				}

				if ( isset( $_POST['permalink_structure'] ) && isset( $_POST['rwl_page'] ) ) {
					$rwl_page = sanitize_title_with_dashes( wp_unslash( $_POST['rwl_page'] ) );
					if ( ! str_contains( $rwl_page, 'wp-login' ) && ! in_array( $rwl_page, $this->forbidden_slugs(), true ) ) {
						if ( is_multisite() && get_site_option( 'rwl_page', 'login' ) === $rwl_page ) {
							delete_option( 'rwl_page' );
						} else {
							update_option( 'rwl_page', $rwl_page );
						}
					}
				}

				if ( get_option( 'rwl_redirect' ) ) {
					delete_option( 'rwl_redirect' );

					if ( is_multisite() && is_super_admin() && is_plugin_active_for_network( $this->basename() ) ) {
						$redirect = network_admin_url( 'settings.php#rwl-page-input' );
					} else {
						$redirect = admin_url( 'options-permalink.php#rwl-page-input' );
					}

					wp_safe_redirect( $redirect );

					die;
				}
			}
		}

		/**
		 * Sanitize urls.
		 *
		 * @param string $fields Fields.
		 *
		 * @return string
		 */
		public function sanitize_page_url( $fields ) {
			$rwl_page = sanitize_title_with_dashes( wp_unslash( $fields ) );
			if ( ! str_contains( $rwl_page, 'wp-login' ) && ! in_array( $rwl_page, $this->forbidden_slugs(), true ) ) {
				if ( is_multisite() && get_site_option( 'rwl_page', 'login' ) === $rwl_page ) {
					return '';
				}
			}

			return $fields;
		}

		/**
		 * Sanitize redirect field.
		 *
		 * @param string $fields Fields.
		 *
		 * @return string
		 */
		public function sanitize_redirect_field( $fields ) {
			return sanitize_title_with_dashes( $fields );
		}

		/**
		 * Sanitize toggle switch.
		 *
		 * @param string $value Value.
		 *
		 * @return string
		 */
		public function sanitize_toggle_switch( $value ) {
			return 'on' === $value ? 'on' : 'off';
		}

		/**
		 * Sanitize page input.
		 *
		 * @param string $fields Fields.
		 *
		 * @return string
		 */
		public function sanitize_page_input( $fields ) {

			return $fields;
		}

		/**
		 * RWL section desc.
		 */
		public function rwl_section_desc() {
			$out = '';

			if ( is_multisite() && is_super_admin() && is_plugin_active_for_network( $this->basename() ) ) {
				$out .= '<p>'
					. sprintf(
						// translators: %1$s: network settings url.
						wp_kses_post( __( 'To set a networkwide default, go to %1$s.', 'change-wp-admin-login' ) ),
						'<a href="' . network_admin_url( 'settings.php#rwl-page-input' ) . '">' . __( 'Network Settings', 'change-wp-admin-login' ) . '</a>'
					)
				. '</p>';
			}

			echo wp_kses_post( $out );
		}

		/**
		 * RWL enable func.
		 */
		public function rwl_enable_func() {
			$value = get_option( 'aio_login__cwpal_enable' );

			echo '<div class="aio-login__toggle-switch-wrapper">
				<input class="aio-login__toggle-field" type="checkbox" id="aio_login__cwpal_enable" name="aio_login__cwpal_enable" value="on" ' . checked( 'on', $value, false ) . '>
				<label class="aio-login__toggle-switch" for="aio_login__cwpal_enable">
					<span class="aio-login__toggle-indicator"></span>
				</label>
			</div>';

			echo '<p class="description">
				<strong>'
					. esc_attr__( 'Enable to customize your admin login URL.', 'change-wp-admin-login' )
				. '</strong>
			</p>';
		}

		/**
		 * RWL redirect func.
		 */
		public function rwl_redirect_func() {
			$value = get_option( 'rwl_redirect_field' );
			echo '<code>' . esc_url( trailingslashit( home_url() ) ) . '</code> <input type="text" value="' . esc_attr( $value ) . '" name="rwl_redirect_field" id="rwl_redirect_field" class="regular-text" /> <code>/</code>';
			echo '<p class="description">
				<strong>'
					. esc_attr__( 'Specify URL where attempts to access wp-login or wp-admin should be redirected to. If custom URL is set above, By default, this will redirect to your site\'s Home page unless you set it to something else.', 'change-wp-admin-login' )
				. '</strong>
			</p>';
		}

		/**
		 * RWL page input.
		 */
		public function rwl_page_input() {
			if ( get_option( 'permalink_structure' ) ) {
				echo '<code>' . esc_url( trailingslashit( home_url() ) ) . '</code> <input id="rwl-page-input" type="text" name="rwl_page" value="' . esc_attr( $this->new_login_slug() ) . '">' . ( $this->use_trailing_slashes() ? ' <code>/</code>' : '' );
			} else {
				echo '<code>' . esc_url( trailingslashit( home_url() ) ) . '?</code> <input id="rwl-page-input" type="text" name="rwl_page" value="' . esc_attr( $this->new_login_slug() ) . '">';
			}

			echo '<p class="description">
				<strong>'
					. esc_attr__( 'Protect your website by changing the login page URL.', 'change-wp-admin-login' )
				. '</strong>';
		}

		/**
		 * Admin notices.
		 */
		public function admin_notices() {
			global $pagenow;

			if ( ! is_network_admin() && 'options-permalink.php' === $pagenow && isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				echo '<div class="updated">
					<p>'
						. sprintf(
							// translators: %1$s: new login url, %2$s: new login url.
							wp_kses_post( __( 'Your login page is now here: %1$s. Bookmark this page!', 'change-wp-admin-login' ) ),
							'<strong><a href="' . esc_url( $this->new_login_url() ) . '">' . esc_url( $this->new_login_url() ) . '</a></strong>'
						)
					. '</p>
				</div>';
			}
		}

		/**
		 * Plugins loaded.
		 */
		public function plugins_loaded() {

			global $pagenow;
			if ( isset( $_SERVER['REQUEST_URI'] ) ) {
				$request_url = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

				if ( ! is_multisite() && ( str_contains( rawurldecode( $request_url ), 'wp-signup' ) || str_contains( rawurldecode( $request_url ), 'wp-activate' ) ) ) {

					wp_die( esc_attr__( 'This feature is not enabled.', 'change-wp-admin-login' ) );

				}

				$request = wp_parse_url( rawurldecode( $request_url ) );

				if ( ( str_contains( rawurldecode( $request_url ), 'wp-login.php' ) || ( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === site_url( 'wp-login', 'relative' ) ) ) && ! is_admin() ) {

					$this->wp_login_php = true;

					$_SERVER['REQUEST_URI'] = $this->user_trailingslashit( '/' . str_repeat( '-/', 10 ) );

					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					$pagenow = 'index.php';

				} elseif ( ( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === home_url( $this->new_login_slug(), 'relative' ) ) || ( ! get_option( 'permalink_structure' ) && isset( $_GET[ $this->new_login_slug() ] ) && empty( $_GET[ $this->new_login_slug() ] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					$pagenow = 'wp-login.php';

				} elseif ( ( str_contains( rawurldecode( $request_url ), 'wp-register.php' ) || ( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === site_url( 'wp-register', 'relative' ) ) ) && ! is_admin() ) {

					$this->wp_login_php = true;

					$_SERVER['REQUEST_URI'] = $this->user_trailingslashit( '/' . str_repeat( '-/', 10 ) );

					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					$pagenow = 'index.php';
				}
			}
		}

		/**
		 * WP loaded.
		 */
		public function wp_loaded() {
			global $pagenow;

			if ( is_admin() && ! is_user_logged_in() && ! defined( 'DOING_AJAX' ) ) {
				if ( 'false' === get_option( 'rwl_redirect_field' ) ) {
					wp_safe_redirect( '/' );
				} else {
					wp_safe_redirect( '/' . get_option( 'rwl_redirect_field' ) );
				}
				die;
			}

			if ( isset( $_SERVER['REQUEST_URI'] ) ) {
				$request = wp_parse_url( rawurldecode( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) );

				if ( 'wp-login.php' === $pagenow && $request['path'] !== $this->user_trailingslashit( $request['path'] ) && get_option( 'permalink_structure' ) ) {
					$query_string = '';
					if ( isset( $_SERVER['QUERY_STRING'] ) && ! empty( $_SERVER['QUERY_STRING'] ) ) {
						$query_string = '?' . sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) );
					}

					wp_safe_redirect( $this->user_trailingslashit( $request['path'] ) . $query_string );
					die;
				} elseif ( $this->wp_login_php ) {
					$referer = wp_get_referer();
					if ( $referer && str_contains( $referer, 'wp-activate.php' ) ) {
						$referer = wp_parse_url( $referer );
						if ( $referer && ! empty( $referer['query'] ) ) {
							parse_str( $referer['query'], $referer );
							if ( ! empty( $referer['key'] ) ) {
								$result = wpmu_activate_signup( $referer['key'] );
								if ( is_wp_error( $result ) && in_array( $result->get_error_code(), array( 'already_active', 'blog_taken' ), true ) ) {
									$query_string = '';
									if ( isset( $_SERVER['QUERY_STRING'] ) && ! empty( $_SERVER['QUERY_STRING'] ) ) {
										$query_string = '?' . sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) );
									}
									wp_safe_redirect( $this->new_login_url() . $query_string );
									die;
								}
							}
						}
					}

					$this->wp_template_loader();
				} elseif ( 'wp-login.php' === $pagenow ) {
					global $error, $interim_login, $action, $user_login;

					require_once ABSPATH . 'wp-login.php';

					die;
				}
			}
		}

		/**
		 * Site url.
		 *
		 * @param string $url URL.
		 * @param string $path Path.
		 * @param string $scheme Scheme.
		 *
		 * @return string
		 */
		public function site_url( $url, $path, $scheme ) {
			return $this->filter_wp_login_php( $url, $scheme );
		}

		/**
		 * Network site url.
		 *
		 * @param string $url URL.
		 * @param string $path Path.
		 * @param string $scheme Scheme.
		 *
		 * @return string
		 */
		public function network_site_url( $url, $path, $scheme ) {
			return $this->filter_wp_login_php( $url, $scheme );
		}

		/**
		 * Modify wp redirect.
		 *
		 * @param string $location Location.
		 *
		 * @return string
		 */
		public function wp_redirect( $location ) {
			return $this->filter_wp_login_php( $location );
		}

		/**
		 * Filter wp login php.
		 *
		 * @param string $url URL.
		 * @param string $scheme Scheme.
		 *
		 * @return string
		 */
		public function filter_wp_login_php( $url, $scheme = null ) {
			$current_url = isset( $_SERVER['PHP_SELF'] ) ? sanitize_text_field( wp_unslash( $_SERVER['PHP_SELF'] ) ) : '';
			if ( is_int( strpos( $url, 'wp-login.php' ) ) || is_int( strpos( $url, 'wp-login' ) ) ) {
				if ( is_ssl() ) {
					$scheme = 'https';
				}
				$args = explode( '?', $url );
				if ( isset( $args[1] ) ) {
					wp_parse_str( $args[1], $args );

					// Fixed support ticket: https://wp.org/support/topic/plugin-causes-password-related-emails-to-fail/.
					$args = $this->rawurlencode_nested_array( $args );

					$url = add_query_arg( $args, $this->new_login_url( $scheme ) );
				} else {
					$url = $this->new_login_url( $scheme );
				}
			}

			if ( ! is_int( strpos( $current_url, 'wp-admin' ) ) ) {
				return $url;
			}

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				return $url;
			}

			if ( ! function_exists( 'is_user_logged_in' ) ) {
				return $url;
			}

			if ( ! is_user_logged_in() ) {
				$redirect_url = get_option( 'rwl_redirect_field' );
				if ( is_null( $redirect_url ) ) {
					$redirect_url = '';
				}

				return '/' . $redirect_url;
			}

			return $url;
		}

		/**
		 * Raw url encode nested array.
		 *
		 * @param array $args Args.
		 *
		 * @fixed https://wp.org/support/topic/plugin-causes-password-related-emails-to-fail/
		 *
		 * @return array
		 */
		private function rawurlencode_nested_array( $args ) {

			foreach ( $args as $k => $v ) {
				if ( is_array( $v ) ) {
					$args[ $k ] = $this->rawurlencode_nested_array( $v );
				} else {
					$args[ $k ] = rawurlencode( $v );
				}
			}

			return $args;
		}

		/**
		 * Modify welcome email.
		 *
		 * @param string $value Value.
		 *
		 * @return string
		 */
		public function welcome_email( $value ) {
			return str_replace( 'wp-login.php', trailingslashit( get_site_option( 'rwl_page', 'login' ) ), $value );
		}

		/**
		 * Forbidden slugs.
		 *
		 * @return array
		 */
		public function forbidden_slugs() {
			$wp = new \WP();

			return array_merge( $wp->public_query_vars, $wp->private_query_vars );
		}

		public function rest_api_init() {
			register_rest_route(
				'aio-login/change-wp-admin-login',
				'/get-settings',
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get_settings' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);

			register_rest_route(
				'aio-login/change-wp-admin-login',
				'/save-settings',
				array(
					'methods' => 'POST',
					'callback' => array( $this, 'save_settings' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);
		}

		public function get_settings() {
			$settings = array(
				'login_url'    => $this->new_login_slug(),
				'redirect_url' => get_option( 'rwl_redirect_field', '' ),
				'enabled'      => $this->is_cwpal_enabled(),
				'nonce'        => wp_create_nonce( 'change-wp-admin-login' ),
			);

			return rest_ensure_response( array(
				'success' => true,
				'data' => $settings,
			) );
		}

		public function save_settings( \WP_REST_Request $request ) {
			$params = $request->get_params();

			if ( isset( $params['_wpnonce'] ) && wp_verify_nonce( $params['_wpnonce'], 'change-wp-admin-login' ) ) {
				$enable       = 'off';
				$login_url    = sanitize_text_field( $params['login_url'] );
				$redirect_url = sanitize_text_field( $params['redirect_url'] );
				
				if ( isset( $params['enabled'] ) && true === $params['enabled'] ) {
					$enable = 'on';

					update_option( 'rwl_page', sanitize_title_with_dashes( wp_unslash( $login_url ) ) );
					update_option( 'rwl_redirect_field', sanitize_title_with_dashes( wp_unslash( $redirect_url ) ) );
				}

				update_option( 'aio_login__cwpal_enable', $enable );

				return rest_ensure_response(
					array(
						'success' => true,
						'message' => __( 'Settings saved successfully', 'change-wp-admin-login' ),
					)
				);
			}

			return new \WP_Error(
				'invalid_nonce',
				__( 'Nonce verification failed', 'change-wp-admin-login' ),
				array(
					'status' => 403,
				)
			);
		}

		/**
		 * Get instance.
		 *
		 * @return Change_WP_Admin_Login
		 */
		public static function get_instance() {
			static $instance = null;

			if ( is_null( $instance ) ) {
				$instance = new self();
			}

			return $instance;
		}
	}
}
