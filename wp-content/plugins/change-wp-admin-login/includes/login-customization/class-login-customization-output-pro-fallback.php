<?php
/**
 * Class Login_Customization_Output
 *
 * @package AIO_Login
 *
 * Loaded only when AIO Login Pro is inactive so free templates (Modern Center, Future Tech)
 * still render on wp-login.php and in the Customizer preview.
 */

namespace AIO_Login\Login_Customization;

defined('ABSPATH') || exit;

if (!class_exists('AIO_Login\\Login_Customization\\Login_Customization_Output_Pro_Fallback')) {
	/**
	 * Class Login_Customization_Output_Pro_Fallback
	 */
	class Login_Customization_Output_Pro_Fallback
	{

		/**
		 * Template.
		 *
		 * @var string $tamplate Template.
		 */
		private $tamplate;

		/**
		 * Login_Customization_Output constructor.
		 */
		private function __construct()
		{
			// Do NOT resolve template in constructor.
			// During Customizer preview, option values are overridden via preview filters, which may be added
			// after this class is constructed. Resolve template lazily on each hook call.
			$this->tamplate = null;

			add_action('login_enqueue_scripts', array($this, 'login_enqueue_scripts'));
			add_filter('login_body_class', array($this, 'body_class'));
			add_filter('aio_login__custom_css', array($this, 'custom_css'));
			add_action( 'login_form', array( $this, 'template_09_form_footer_links' ), 99 );
			add_action( 'lostpassword_form', array( $this, 'template_09_form_footer_links' ), 99 );
			add_action('register_form', array($this, 'template_09_register_footer_links'), 99);
			add_action('login_footer', array($this, 'login_footer'));
			add_filter( 'wp_login_errors', array( $this, 'filter_login_errors_in_customizer_preview' ), 999, 2 );
			add_filter( 'login_headerurl', array( $this, 'filter_login_header_url' ) );
			add_filter( 'login_headertext', array( $this, 'filter_login_header_text' ) );
			add_filter( 'login_title', array( $this, 'filter_login_page_title' ), 10, 2 );
			add_action( 'login_head', array( $this, 'output_login_favicon' ) );
			add_action( 'login_footer', array( $this, 'output_login_logo_link_tooltip_attributes' ), 5 );
		}

		/**
		 * Resolve current template (supports Customizer preview overrides).
		 *
		 * @return array{class:string,stylesheet:string}
		 */
		private function get_template() {
			$templates = array(
				'template-1' => array(
					'class'      => 'aio-login__template-01',
					'stylesheet' => AIO_LOGIN__DIR_URL . 'assets/css/templates/login-designer-template-01.css',
				),
				'template-2' => array(
					'class'      => 'aio-login__template-02',
					'stylesheet' => AIO_LOGIN__DIR_URL . 'assets/css/templates/login-designer-template-02.css',
				),
				'default' => array(
					'class'      => 'aio-login__template-03',
					'stylesheet' => AIO_LOGIN__DIR_URL . 'assets/css/templates/login-designer-template-03.css',
				),
				'template-3' => array(
					'class'      => 'aio-login__template-04',
					'stylesheet' => AIO_LOGIN__DIR_URL . 'assets/css/templates/login-designer-template-04.css',
				),
				'template-4' => array(
					'class'      => 'aio-login__template-05',
					'stylesheet' => AIO_LOGIN__DIR_URL . 'assets/css/templates/login-designer-template-05.css',
				),
				'template-5' => array(
					'class'      => 'aio-login__template-06',
					'stylesheet' => AIO_LOGIN__DIR_URL . 'assets/css/templates/login-designer-template-06.css',
				),
				'template-6' => array(
					'class'      => 'aio-login__template-07',
					'stylesheet' => AIO_LOGIN__DIR_URL . 'assets/css/templates/login-designer-template-07.css',
				),
				'template-7' => array(
					'class'      => 'aio-login__template-08',
					'stylesheet' => AIO_LOGIN__DIR_URL . 'assets/css/templates/login-designer-template-08.css',
				),
				'template-8' => array(
					'class'      => 'aio-login__template-09',
					'stylesheet' => AIO_LOGIN__DIR_URL . 'assets/css/templates/login-designer-template-09.css',
				),
			);

			$key = get_option( 'aio_login__customization_templates', 'default' );
			$free_slugs = array( 'default', 'template-8' );
			if ( class_exists( '\AIO_Login\AIO_Login' ) && ! \AIO_Login\AIO_Login::has_pro() ) {
				if ( ! in_array( (string) $key, $free_slugs, true ) ) {
					$key = 'default';
				}
			}
			if ( empty( $key ) || ! isset( $templates[ $key ] ) ) {
				$key = 'default';
			}
			return $templates[ $key ];
		}

		/**
		 * Legacy `aio_login_elements_settings` merged with Customizer options.
		 * Customizer saves `aio_login_el_*` options; sync to the array can lag or Vue REST save can overwrite the array.
		 * Also copies `text_font_family` into input/button when those are empty/Inherit so CSS matches live preview.
		 *
		 * @return array<string, mixed>
		 */
		private function get_effective_elements_settings() {
			$settings = get_option( 'aio_login_elements_settings', array() );
			if ( ! is_array( $settings ) ) {
				$settings = array();
			}
			// Same map as Login_Customizer::customize_save_after — options always win so output matches Customizer even if the legacy array is stale.
			$el_option_map = array(
				'text_font_family'     => 'aio_login_el_text_font_family',
				'background_repeat'    => 'aio_login_el_background_repeat',
				'background_position'  => 'aio_login_el_background_position',
				'background_size'      => 'aio_login_el_background_size',
				'form_transparent'     => 'aio_login_el_form_transparent',
				'form_width'           => 'aio_login_el_form_width',
				'form_min_height'      => 'aio_login_el_form_min_height',
				'form_border_radius'   => 'aio_login_el_form_border_radius',
				'form_shadow'          => 'aio_login_el_form_shadow',
				'form_shadow_opacity'  => 'aio_login_el_form_shadow_opacity',
				'form_padding'         => 'aio_login_el_form_padding',
				'form_border'          => 'aio_login_el_form_border',
				'btn_bg_color'         => 'aio_login_el_btn_bg_color',
				'btn_hover_color'      => 'aio_login_el_btn_hover_color',
				'btn_text_color'       => 'aio_login_el_btn_text_color',
				'btn_border_color'     => 'aio_login_el_btn_border_color',
				'btn_size'             => 'aio_login_el_btn_size',
				'btn_padding'          => 'aio_login_el_btn_padding',
				'btn_padding_tb'       => 'aio_login_el_btn_padding_tb',
				'btn_border_radius'    => 'aio_login_el_btn_border_radius',
				'btn_shadow'           => 'aio_login_el_btn_shadow',
				'btn_shadow_opacity'   => 'aio_login_el_btn_shadow_opacity',
				'btn_text_size'        => 'aio_login_el_btn_text_size',
				'label_color'          => 'aio_login_el_label_color',
				'remember_label_color' => 'aio_login_el_remember_label_color',
				'label_font_size'      => 'aio_login_el_label_font_size',
				'remember_font_size'   => 'aio_login_el_remember_font_size',
				'input_margin'         => 'aio_login_el_input_margin',
				'input_bg_color'       => 'aio_login_el_input_bg_color',
				'input_text_color'     => 'aio_login_el_input_text_color',
				'input_width'          => 'aio_login_el_input_width',
				'link_color'           => 'aio_login_el_link_color',
			);
			foreach ( $el_option_map as $legacy_key => $opt_key ) {
				$val = get_option( $opt_key, null );
				if ( null !== $val ) {
					$settings[ $legacy_key ] = $val;
				}
			}
			if ( ! empty( $settings['text_font_family'] ) && 'Inherit' !== $settings['text_font_family'] ) {
				if ( empty( $settings['input_font_family'] ) || 'Inherit' === $settings['input_font_family'] ) {
					$settings['input_font_family'] = $settings['text_font_family'];
				}
				if ( empty( $settings['btn_font_family'] ) || 'Inherit' === $settings['btn_font_family'] ) {
					$settings['btn_font_family'] = $settings['text_font_family'];
				}
			}
			return $settings;
		}

		/**
		 * Whether "Disable logo" is on (Customizer option aio_login_disable_logo).
		 *
		 * @return bool
		 */
		private function is_logo_disabled() {
			$v = get_option( 'aio_login_disable_logo', false );
			return ( true === $v || 1 === $v || '1' === $v || 'on' === strtolower( (string) $v ) );
		}

		/**
		 * Whether this request is the login screen inside the Customizer preview iframe.
		 *
		 * @return bool
		 */
		private function is_login_customizer_preview_request() {
			if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
				return true;
			}
			if ( ! empty( $_GET['customize_changeset_uuid'] ) || ! empty( $_GET['customize_messenger_channel'] ) || ! empty( $_GET['aio_login_customizer_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return true;
			}
			if ( ! empty( $_REQUEST['aio_login_customizer'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return true;
			}
			return false;
		}

		/**
		 * Avoid stale validation errors / duplicate notices in the Customizer preview (iframe is not a real login POST).
		 *
		 * @param \WP_Error $errors       Errors.
		 * @param string    $redirect_to Redirect.
		 * @return \WP_Error
		 */
		public function filter_login_errors_in_customizer_preview( $errors, $redirect_to ) {
			unset( $redirect_to );
			if ( ! $this->is_login_customizer_preview_request() ) {
				return $errors;
			}
			if ( ! is_wp_error( $errors ) ) {
				return $errors;
			}
			return new \WP_Error();
		}

		/**
		 * Primary nav row for template-09 (Register | Lost password, etc.) — mirrors wp-login.php #nav.
		 *
		 * @return string
		 */
		private function get_template_09_primary_nav_markup() {
			$action = '';
			if ( isset( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$action = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) );
			}

			$parts = array();
			$sep   = apply_filters( 'login_link_separator', ' | ' );

			if ( 'lostpassword' === $action ) {
				$parts[] = sprintf(
					'<a href="%1$s">%2$s</a>',
					esc_url( wp_login_url() ),
					esc_html__( 'Log in', 'default' )
				);
				if ( get_option( 'users_can_register' ) ) {
					$parts[] = sprintf(
						'<a class="wp-login-register" href="%1$s">%2$s</a>',
						esc_url( wp_registration_url() ),
						esc_html__( 'Register', 'default' )
					);
				}
			} elseif ( 'register' === $action ) {
				$parts[] = sprintf(
					'<a href="%1$s">%2$s</a>',
					esc_url( wp_login_url() ),
					esc_html__( 'Log in', 'default' )
				);
			} else {
				if ( get_option( 'users_can_register' ) ) {
					$parts[] = sprintf(
						'<a class="wp-login-register" href="%1$s">%2$s</a>',
						esc_url( wp_registration_url() ),
						esc_html__( 'Register', 'default' )
					);
				}
				$parts[] = sprintf(
					'<a class="wp-login-lost-password" href="%1$s">%2$s</a>',
					esc_url( wp_lostpassword_url() ),
					esc_html__( 'Lost your password?', 'default' )
				);
			}

			if ( empty( $parts ) ) {
				return '';
			}

			return implode(
				'<span class="aio-login__template-09-nav-sep">' . esc_html( $sep ) . '</span>',
				$parts
			);
		}

		public function template_09_form_footer_links() {
			if ( class_exists( '\\AIO_Login_Pro\\Login_Customization\\Login_Customization_Output' ) ) {
				return;
			}
			$tpl = $this->get_template();
			if ( empty( $tpl['class'] ) || 'aio-login__template-09' !== $tpl['class'] ) {
				return;
			}

			$login_link_separator = apply_filters( 'login_link_separator', ' | ' );

			echo '<div class="aio-login__template-09-footer-links">';

			echo '<div class="aio-login__template-09-footer-link aio-login__template-09-nav-row">';

			if ( get_option( 'users_can_register' ) ) {
				$registration_url = sprintf(
					'<a class="wp-login-register" href="%s">%s</a>',
					esc_url( wp_registration_url() ),
					esc_html__( 'Register', 'default' )
				);
				echo apply_filters( 'register', $registration_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<span class="aio-login__template-09-nav-sep" aria-hidden="true">&nbsp;' . esc_html( trim( $login_link_separator ) ) . '&nbsp;</span>';
			}

			$lost_password_link = sprintf(
				'<a class="wp-login-lost-password" href="%s">%s</a>',
				esc_url( wp_lostpassword_url() ),
				esc_html__( 'Lost your password?', 'default' )
			);
			echo apply_filters( 'lost_password_html_link', $lost_password_link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			echo '</div>';

			echo '<div class="aio-login__template-09-footer-link aio-login__template-09-back-to-site"><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( sprintf( _x( '&larr; Go to %s', 'site' ), get_bloginfo( 'title', 'display' ) ) ) . '</a></div>';
			echo '</div>';
		}

		public function template_09_register_footer_links() {
			if ( class_exists( '\\AIO_Login_Pro\\Login_Customization\\Login_Customization_Output' ) ) {
				return;
			}
			$tpl = $this->get_template();
			if ( empty( $tpl['class'] ) || 'aio-login__template-09' !== $tpl['class'] ) {
				return;
			}

			$login_link_separator = apply_filters( 'login_link_separator', ' | ' );

			echo '<div class="aio-login__template-09-footer-links">';

			echo '<div class="aio-login__template-09-footer-link aio-login__template-09-nav-row">';

			$login_link = sprintf(
				'<a class="wp-login-log-in" href="%s">%s</a>',
				esc_url( wp_login_url() ),
				esc_html__( 'Log in', 'default' )
			);
			echo $login_link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			echo '<span class="aio-login__template-09-nav-sep" aria-hidden="true">&nbsp;' . esc_html( trim( $login_link_separator ) ) . '&nbsp;</span>';

			$lost_password_link = sprintf(
				'<a class="wp-login-lost-password" href="%s">%s</a>',
				esc_url( wp_lostpassword_url() ),
				esc_html__( 'Lost your password?', 'default' )
			);
			echo apply_filters( 'lost_password_html_link', $lost_password_link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			echo '</div>';

			echo '<div class="aio-login__template-09-footer-link aio-login__template-09-back-to-site"><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( sprintf( _x( '&larr; Go to %s', 'site' ), get_bloginfo( 'title', 'display' ) ) ) . '</a></div>';
			echo '</div>';
		}

		/**
		 * Login enqueue scripts
		 */
		public function login_enqueue_scripts()
		{
			$tpl = $this->get_template();
			if ( is_array( $tpl ) && isset( $tpl['stylesheet'] ) ) {
				wp_enqueue_style( 'aio-login-free-template', $tpl['stylesheet'], array( 'login' ), AIO_LOGIN__VERSION );
			}

			// Ensure Customizer live preview JS loads on wp-login.php preview frame.
			$looks_like_customizer_preview = (
				( function_exists( 'is_customize_preview' ) && is_customize_preview() )
				|| ! empty( $_GET['customize_changeset_uuid'] )
				|| ! empty( $_GET['customize_messenger_channel'] )
				|| ! empty( $_GET['aio_login_customizer_preview'] )
			);

			if ( $looks_like_customizer_preview ) {
				// No dependency on customize-preview — wp-login iframe does not need Core preview runtime; our script is postMessage-only.
				wp_enqueue_script(
					'aio-login-free-login-customizer-preview',
					AIO_LOGIN__DIR_URL . 'assets/js/login-customizer-preview.js',
					array(),
					AIO_LOGIN__VERSION,
					true
				);
			}

			$settings = $this->get_effective_elements_settings();
			$fonts = array();
			if (!empty($settings['input_font_family']) && $settings['input_font_family'] !== 'Inherit') {
				$fonts[] = $settings['input_font_family'];
			}
			if (!empty($settings['btn_font_family']) && $settings['btn_font_family'] !== 'Inherit') {
				$fonts[] = $settings['btn_font_family'];
			}
			if (!empty($settings['text_font_family']) && $settings['text_font_family'] !== 'Inherit') {
				$fonts[] = $settings['text_font_family'];
			}

			if (!empty($fonts)) {
				$fonts = array_unique($fonts);
				$font_families = array();
				foreach ($fonts as $font) {
					$font_families[] = str_replace(' ', '+', $font) . ':wght@300;400;500;600;700';
				}
				$fonts_url = 'https://fonts.googleapis.com/css2?family=' . implode('&family=', $font_families) . '&display=swap';
				wp_enqueue_style('aio-login-elements-fonts', $fonts_url, array(), null);
			}

			// Hide logo AFTER template CSS: inline on `login` prints before aio-login-free-template.css, so template
			// rules like `#login h1 { display: block !important; }` were overriding display:none from the filter.
			if ( $this->is_logo_disabled() && is_array( $tpl ) && isset( $tpl['class'] ) ) {
				$tc       = (string) $tpl['class'];
				$specific = 'body.login.' . $tc;
				$hide_css = $specific . ' #login h1.wp-login-logo, ' . $specific . ' #login h1, ' . $specific . ' #login .wp-login-logo, ' . $specific . ' .wp-login-logo { display: none !important; visibility: hidden !important; height: 0 !important; width: 0 !important; margin: 0 !important; padding: 0 !important; overflow: hidden !important; clip: rect(0,0,0,0) !important; position: absolute !important; pointer-events: none !important; }';
				wp_add_inline_style( 'aio-login-free-template', $hide_css );
			}
		}

		/**
		 * @param string $url Default logo URL.
		 * @return string
		 */
		public function filter_login_header_url( $url ) {
			$custom = get_option( 'aio_login_logo_url', '' );
			return ! empty( $custom ) ? esc_url( $custom ) : $url;
		}

		/**
		 * @param string $text Default title attribute.
		 * @return string
		 */
		public function filter_login_header_text( $text ) {
			$custom = get_option( 'aio_login_logo_title', '' );
			return ! empty( $custom ) ? sanitize_text_field( $custom ) : $text;
		}

		/**
		 * Match Customizer preview: set title/aria-label on the logo link so the browser shows a hover tooltip on the front-end login screen.
		 *
		 * @return void
		 */
		public function output_login_logo_link_tooltip_attributes() {
			$title = get_option( 'aio_login_logo_title', '' );
			if ( ! is_string( $title ) ) {
				return;
			}
			$title = sanitize_text_field( $title );
			if ( '' === $title ) {
				return;
			}
			$title_json = wp_json_encode( $title );
			if ( false === $title_json ) {
				return;
			}
			$script = '(function(){var t=' . $title_json . ';var a=document.querySelector("#login h1.wp-login-logo a,#login h1 a,.wp-login-logo a");if(a&&t){a.setAttribute("title",t);a.setAttribute("aria-label",t);}})();';
			if ( function_exists( 'wp_print_inline_script_tag' ) ) {
				wp_print_inline_script_tag( $script );
				return;
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-encoded string only.
			echo '<script>' . $script . '</script>';
		}

		/**
		 * @param string $login_title Title.
		 * @param string $title       Original title.
		 * @return string
		 */
		public function filter_login_page_title( $login_title, $title ) {
			$custom = get_option( 'aio_login_login_page_title', '' );
			if ( ! empty( $custom ) ) {
				return sanitize_text_field( $custom );
			}
			return $login_title;
		}

		/**
		 * Output favicon link tag (login only).
		 */
		public function output_login_favicon() {
			$favicon_id = get_option( 'aio_login_favicon', 0 );
			if ( empty( $favicon_id ) ) {
				return;
			}
			$url = wp_get_attachment_url( (int) $favicon_id );
			if ( empty( $url ) ) {
				return;
			}
			echo '<link rel="icon" href="' . esc_url( $url ) . '" />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		/**
		 * Body class
		 *
		 * @param array $classes Classes.
		 *
		 * @return array
		 */
		public function body_class($classes)
		{
			$tpl = $this->get_template();
			if ( is_array( $tpl ) && isset( $tpl['class'] ) ) {
				$classes[] = $tpl['class'];
			}

			return $classes;
		}

		/**
		 * Custom CSS
		 *
		 * @param string $css CSS.
		 *
		 * @return string
		 */
		public function custom_css($css)
		{

			$tpl              = $this->get_template();
			$template_class   = ( is_array( $tpl ) && isset( $tpl['class'] ) ) ? $tpl['class'] : '';
			$specific = !empty($template_class) ? "body.login.{$template_class}" : 'body.login';
			$strong_specific = !empty($template_class) ? "body.login.{$template_class}.{$template_class}" : $specific;

			// Template metadata for smart customization
			$template_meta = array(
				'aio-login__template-01' => array('form' => '#login form', 'bg_type' => 'split'),
				'aio-login__template-02' => array('form' => '#login form', 'bg_type' => 'simple'),
				'aio-login__template-03' => array('form' => '#login form', 'bg_type' => 'simple'),
				'aio-login__template-04' => array('form' => '#login form', 'bg_type' => 'split'),
				'aio-login__template-05' => array('form' => '#login form', 'bg_type' => 'complex'),
				'aio-login__template-06' => array('form' => '#login', 'bg_type' => 'complex'),
				'aio-login__template-07' => array('form' => '#login', 'bg_type' => 'simple'),
				'aio-login__template-08' => array('form' => '#login', 'bg_type' => 'complex'),
				'aio-login__template-09' => array( 'form' => '#login form', 'bg_type' => 'complex' ),
			);

			$meta = isset($template_meta[$template_class]) ? $template_meta[$template_class] : array('form' => '#login form', 'bg_type' => 'simple');

			// Logo dimensions / image (hide rule is printed in login_enqueue_scripts on aio-login-free-template so it wins over template CSS).
			if ( ! $this->is_logo_disabled() ) {
				// Logo dimensions/margin options should also affect Pro templates.
				$logo_width        = get_option('aio_login_logo_width', '');
				$logo_height       = get_option('aio_login_logo_height', '');
				$logo_margin_bottom = get_option('aio_login_margin_bottom', '');

				if (!empty($logo_width)) {
					$css .= $specific . ' #login h1 a, ' . $specific . ' .wp-login-logo a {
					width: ' . absint($logo_width) . 'px !important;
				}';
					if ( 'aio-login__template-09' === $template_class ) {
						$css .= $specific . ' #login h1.wp-login-logo, ' . $specific . ' #login h1 {
					min-width: 0 !important;
					max-width: none !important;
					width: ' . absint($logo_width) . 'px !important;
				}';
					}
				}

				if (!empty($logo_height)) {
					$css .= $specific . ' #login h1 a, ' . $specific . ' .wp-login-logo a {
					height: ' . absint($logo_height) . 'px !important;
				}';
					if ( 'aio-login__template-09' === $template_class ) {
						$css .= $specific . ' #login h1.wp-login-logo, ' . $specific . ' #login h1 {
					height: ' . absint($logo_height) . 'px !important;
				}';
					}
				}

				if (!empty($logo_margin_bottom)) {
					$css .= $specific . ' #login h1, ' . $specific . ' .wp-login-logo {
					margin-bottom: ' . absint($logo_margin_bottom) . 'px !important;
				}';
				}

				// Ensure custom logo renders consistently across templates (some templates apply filters/overrides).
				$logo_id = get_option('aio_login_logo', false);
				if (!empty($logo_id)) {
					$logo_url = wp_get_attachment_url($logo_id);
					if (!empty($logo_url)) {
						$css .= $specific . ' #login h1 a, ' . $specific . ' .wp-login-logo a {
						background-image: url(' . esc_url($logo_url) . ') !important;
						background-repeat: no-repeat !important;
						background-position: center !important;
						background-size: contain !important;
						text-indent: -9999px !important;
						overflow: hidden !important;
						filter: none !important;
						-webkit-filter: none !important;
						opacity: 1 !important;
						display: block !important;
					}';
					}
				}
			}

			// Background settings should reflect across all templates.
			$background_image = get_option('aio_login_background_image', '');
			$background_color = get_option('aio_login_background_color', '');
			$background_image_url = '';
			if (!empty($background_image)) {
				$background_image_url = wp_get_attachment_url($background_image);
			}
			$bg_repeat   = '';
			$bg_position = '';
			$bg_size     = '';
			$elements_settings = $this->get_effective_elements_settings();
			if ( is_array( $elements_settings ) ) {
				$bg_repeat   = isset( $elements_settings['background_repeat'] ) ? (string) $elements_settings['background_repeat'] : '';
				$bg_position = isset( $elements_settings['background_position'] ) ? (string) $elements_settings['background_position'] : '';
				$bg_size     = isset( $elements_settings['background_size'] ) ? (string) $elements_settings['background_size'] : '';
			}

			// Default #f1f1f1: skip only on dark body templates; other templates still get the standard gray override.
			$dark_body_template_classes = array( 'aio-login__template-02', 'aio-login__template-05', 'aio-login__template-08' );
			$is_dark_body_template      = in_array( $template_class, $dark_body_template_classes, true );
			$bg_trim                    = trim( (string) $background_color );
			$is_default_wp_gray         = ( '' !== $bg_trim && 0 === strcasecmp( $bg_trim, '#f1f1f1' ) );
			if ( ! empty( $background_color ) && ( ! $is_default_wp_gray || ! $is_dark_body_template ) ) {
				$css .= $strong_specific . ' {
					background-color: ' . esc_attr( $background_color ) . ' !important;
				}';
			}

			if (!empty($background_image_url)) {
				// Apply to page background for all templates.
				$css .= $strong_specific . ' {
					background-image: url(' . esc_url($background_image_url) . ') !important;
					background-size: ' . esc_attr( $bg_size ? $bg_size : 'cover' ) . ' !important;
					background-position: ' . esc_attr( $bg_position ? $bg_position : 'center' ) . ' !important;
					background-repeat: ' . esc_attr( $bg_repeat ? $bg_repeat : 'no-repeat' ) . ' !important;
					background-attachment: fixed !important;
				}';

				// Split templates background container.
				if ($meta['bg_type'] === 'split') {
					$css .= '#login-designer-background {
						background-image: url(' . esc_url($background_image_url) . ') !important;
						background-size: cover !important;
						background-position: center !important;
					}';
				}

				// Elegant Frost (template-08 class) uses strong !important body background in template CSS.
				if ('aio-login__template-08' === $template_class) {
					$css .= 'body.login.aio-login__template-08.aio-login__template-08 {
						background-image: url(' . esc_url($background_image_url) . ') !important;
						background-size: cover !important;
						background-position: center !important;
						background-repeat: no-repeat !important;
						background-attachment: fixed !important;
					}';
				}
			} else {
				// Fallback for split templates if no user image is set.
				if ($template_class === 'aio-login__template-01') {
					$css .= '#login-designer-background {
						background-image: url(' . esc_url(AIO_LOGIN__DIR_URL . 'assets/images/bg_09.jpg') . ') !important;
						background-size: cover !important;
					}';
				}
				if ($template_class === 'aio-login__template-04') {
					$css .= '#login-designer-background {
						background-image: url(' . esc_url(AIO_LOGIN__DIR_URL . 'assets/images/template-03-bg.png') . ') !important;
						background-size: cover !important;
					}';
				}
			}

			$settings = $this->get_effective_elements_settings();
			if (!empty($settings) && is_array($settings)) {
				// Form Box Customization ($strong_specific beats template CSS loaded after login inline).
				$form_selector = $strong_specific . ' ' . $meta['form'];
				$form_bg_color = isset( $settings['form_bg_color'] ) ? (string) $settings['form_bg_color'] : '';

				// Form transparency toggle.
				if ( ! empty( $settings['form_transparent'] ) ) {
					$css .= $form_selector . ' { background: transparent !important; background-color: transparent !important; }';
				}

				if (!empty($form_bg_color) || !empty($settings['form_border_radius']) || !empty($settings['form_width']) || !empty($settings['form_min_height']) || !empty($settings['form_padding']) || !empty($settings['form_border']) || !empty($settings['form_shadow'])) {
					// STRICT check: Only apply background if it's NOT white AND NOT transparent-theme default
					$is_default_white = ( $form_bg_color === '#ffffff' || '' === $form_bg_color );
					$is_transparent_theme = ($meta['bg_type'] === 'complex');

					$apply_bg = !$is_default_white;
					$apply_radius = !empty($settings['form_border_radius']);
					$apply_width = !empty( $settings['form_width'] );
					$apply_min_h = !empty( $settings['form_min_height'] );
					$apply_padding = !empty( $settings['form_padding'] );
					$apply_border = !empty( $settings['form_border'] );
					$apply_shadow = !empty( $settings['form_shadow'] );
					$shadow_opacity = isset( $settings['form_shadow_opacity'] ) ? (float) $settings['form_shadow_opacity'] : 0.15;
					if ( $shadow_opacity < 0 ) { $shadow_opacity = 0; }
					if ( $shadow_opacity > 1 ) { $shadow_opacity = 1; }

					if ($apply_bg || $apply_radius || $apply_width || $apply_min_h || $apply_padding || $apply_border || $apply_shadow) {
						$css .= $form_selector . ' {';
						if ($apply_bg) {
							$css .= 'background-color: ' . esc_attr( $form_bg_color ) . ' !important;';
							$css .= 'background: ' . esc_attr( $form_bg_color ) . ' !important;';
						}
						if ($apply_radius) {
							$css .= 'border-radius: ' . esc_attr($settings['form_border_radius']) . 'px !important;';
						}
						if ( $apply_width ) {
							$css .= 'width: ' . absint( $settings['form_width'] ) . 'px !important; max-width: none !important;';
						}
						if ( $apply_min_h ) {
							$css .= 'min-height: ' . absint( $settings['form_min_height'] ) . 'px !important;';
						}
						if ( $apply_padding ) {
							$css .= 'padding: ' . esc_attr( $settings['form_padding'] ) . ' !important;';
						}
						if ( $apply_border ) {
							$css .= 'border: ' . esc_attr( $settings['form_border'] ) . ' !important;';
						}
						if ( $apply_shadow ) {
							$css .= 'box-shadow: ' . esc_attr( $settings['form_shadow'] ) . ' rgba(0,0,0,' . esc_attr( (string) $shadow_opacity ) . ') !important;';
						}
						$css .= '}';
					}
				}

				// Input fields — #login form beats template rules like body.login.TPL input[type=text]
				$input_selector = $strong_specific . ' #login form input:not([type="submit"]):not([type="checkbox"]):not([type="radio"])';
				$input_styles = '';

				if (!empty($settings['input_bg_color']) && $settings['input_bg_color'] !== '#ffffff') {
					$input_styles .= 'background-color: ' . esc_attr($settings['input_bg_color']) . ' !important;';
				}
				if (!empty($settings['input_text_color']) && $settings['input_text_color'] !== '#2c3338') {
					$input_styles .= 'color: ' . esc_attr($settings['input_text_color']) . ' !important;';
				}
				if (!empty($settings['input_border_color']) && $settings['input_border_color'] !== '#dcdcde') {
					$input_styles .= 'border-color: ' . esc_attr($settings['input_border_color']) . ' !important;';
				}
				if (!empty($settings['input_border_radius']) && $settings['input_border_radius'] != 4) {
					$input_styles .= 'border-radius: ' . esc_attr($settings['input_border_radius']) . 'px !important;';
				}
				if (!empty($settings['input_font_family']) && $settings['input_font_family'] !== 'Inherit') {
					$input_styles .= 'font-family: "' . esc_attr($settings['input_font_family']) . '", sans-serif !important;';
				}
				if (!empty($settings['input_font_size'])) {
					$input_styles .= 'font-size: ' . esc_attr($settings['input_font_size']) . 'px !important;';
				}
				if ( ! empty( $settings['input_margin'] ) ) {
					$input_styles .= 'margin: ' . esc_attr( $settings['input_margin'] ) . ' !important;';
				}
				if ( ! empty( $settings['input_width'] ) ) {
					$input_styles .= 'width: ' . esc_attr( $settings['input_width'] ) . ' !important;';
				}

				if (!empty($input_styles)) {
					$css .= $input_selector . ' {' . $input_styles . '}';
				}

				// Focus color
				if (!empty($settings['input_focus_color']) && $settings['input_focus_color'] !== '#2271b1') {
					$css .= $input_selector . ':focus { border-color: ' . esc_attr($settings['input_focus_color']) . ' !important; box-shadow: 0 0 0 1px ' . esc_attr($settings['input_focus_color']) . ' !important; }';
				}

				// Buttons
				$btn_selector = $strong_specific . ' #login form input[type="submit"], ' . $strong_specific . ' #loginform input[type="submit"], ' . $strong_specific . ' #login .button-primary';
				$btn_styles = '';

				if (!empty($settings['btn_bg_color']) && $settings['btn_bg_color'] !== '#2271b1') {
					$btn_styles .= 'background-color: ' . esc_attr($settings['btn_bg_color']) . ' !important; border-color: ' . esc_attr($settings['btn_bg_color']) . ' !important;';
				}
				if ( ! empty( $settings['btn_border_color'] ) ) {
					$btn_styles .= 'border-color: ' . esc_attr( $settings['btn_border_color'] ) . ' !important;';
				}
				if (!empty($settings['btn_text_color']) && $settings['btn_text_color'] !== '#ffffff') {
					$btn_styles .= 'color: ' . esc_attr($settings['btn_text_color']) . ' !important;';
				}
				if (!empty($settings['btn_border_radius']) && $settings['btn_border_radius'] != 4) {
					$btn_styles .= 'border-radius: ' . esc_attr($settings['btn_border_radius']) . 'px !important;';
				}
				if ( ! empty( $settings['btn_padding'] ) ) {
					$btn_styles .= 'padding: ' . esc_attr( $settings['btn_padding'] ) . ' !important;';
				} elseif ( ! empty( $settings['btn_padding_tb'] ) ) {
					$tb = absint( $settings['btn_padding_tb'] );
					$btn_styles .= 'padding-top: ' . $tb . 'px !important; padding-bottom: ' . $tb . 'px !important;';
				}
				if ( ! empty( $settings['btn_text_size'] ) ) {
					$btn_styles .= 'font-size: ' . absint( $settings['btn_text_size'] ) . 'px !important;';
				}
				if ( ! empty( $settings['btn_font_family'] ) && 'Inherit' !== $settings['btn_font_family'] ) {
					$btn_styles .= 'font-family: "' . esc_attr( $settings['btn_font_family'] ) . '", sans-serif !important;';
				}
				if ( ! empty( $settings['btn_shadow'] ) ) {
					$btn_shadow_opacity = isset( $settings['btn_shadow_opacity'] ) ? (float) $settings['btn_shadow_opacity'] : 0.2;
					if ( $btn_shadow_opacity < 0 ) { $btn_shadow_opacity = 0; }
					if ( $btn_shadow_opacity > 1 ) { $btn_shadow_opacity = 1; }
					$btn_styles .= 'box-shadow: ' . esc_attr( $settings['btn_shadow'] ) . ' rgba(0,0,0,' . esc_attr( (string) $btn_shadow_opacity ) . ') !important;';
				}
				if ( ! empty( $settings['btn_size'] ) ) {
					$h = absint( $settings['btn_size'] );
					$btn_styles .= 'min-height: ' . $h . 'px !important; box-sizing: border-box !important;';
				}

				if (!empty($btn_styles)) {
					$css .= $btn_selector . ' {' . $btn_styles . '}';
				}

				// Hover state — :hover must be on each comma-separated selector (otherwise only the last matches :hover).
				if (!empty($settings['btn_hover_color']) && $settings['btn_hover_color'] !== '#135e96') {
					$btn_hover_selector = implode(
						', ',
						array_map(
							static function ( $part ) {
								return trim( $part ) . ':hover';
							},
							explode( ',', $btn_selector )
						)
					);
					$css .= $btn_hover_selector . ' { background-color: ' . esc_attr( $settings['btn_hover_color'] ) . ' !important; border-color: ' . esc_attr( $settings['btn_hover_color'] ) . ' !important; }';
				}

				// Text & Links — main label color/size must not target Remember Me (use remember_* options or Additional CSS).
				$label_selector = $strong_specific . ' #login label:not([for="rememberme"])';
				$label_styles = '';
				if (!empty($settings['label_color']) && !in_array($settings['label_color'], ['#3c434a', '#3c434a', '#ffffff', '#000000'])) {
					// Be very careful overriding label colors as themes have carefully chosen white/black presets
					$label_styles .= 'color: ' . esc_attr($settings['label_color']) . ' !important;';
				}
				if (!empty($settings['text_font_family']) && $settings['text_font_family'] !== 'Inherit') {
					$label_styles .= 'font-family: "' . esc_attr($settings['text_font_family']) . '", sans-serif !important;';
				}
				if ( ! empty( $settings['label_font_size'] ) ) {
					$label_styles .= 'font-size: ' . absint( $settings['label_font_size'] ) . 'px !important;';
				}

				if (!empty($label_styles)) {
					$css .= $label_selector . ' {' . $label_styles . '}';
				}

				// Remember me label style.
				$remember_styles = '';
				if ( ! empty( $settings['remember_label_color'] ) ) {
					$remember_styles .= 'color: ' . esc_attr( $settings['remember_label_color'] ) . ' !important;';
				}
				if ( ! empty( $settings['remember_font_size'] ) ) {
					$remember_styles .= 'font-size: ' . absint( $settings['remember_font_size'] ) . 'px !important;';
				}
				if ( ! empty( $remember_styles ) ) {
					$remember_sel = $strong_specific . ' #loginform .forgetmenot label[for="rememberme"], ' . $strong_specific . ' #lostpasswordform .forgetmenot label[for="rememberme"]';
					$css .= $remember_sel . ' { ' . $remember_styles . ' }';
				}

				// Links — #nav a:not(.wp-login-lost-password) + separate .wp-login-lost-password so Additional CSS can override lost-password color (bodyStrong #nav a beats .wp-login-lost-password otherwise).
				$link_selector = $strong_specific . ' #nav a:not(.wp-login-lost-password), ' . $strong_specific . ' #backtoblog a, ' . $strong_specific . ' .privacy-policy-page-link a, ' . $strong_specific . ' .aio-login__template-09-footer-links a';
				$link_styles = '';
				if (!empty($settings['link_color']) && $settings['link_color'] !== '#2271b1') {
					$link_styles .= 'color: ' . esc_attr($settings['link_color']) . ' !important;';
				}
				if (!empty($link_styles)) {
					$css .= $link_selector . ' {' . $link_styles . '}';
					$css .= '.wp-login-lost-password { ' . $link_styles . ' }';
				}

				// Match Customizer preview: text font applies site-wide on the login screen (headings via ::before, links, etc.).
				// Labels alone are not enough (e.g. template-09 hides labels; title is #loginform > p::before).
				if ( ! empty( $settings['text_font_family'] ) && 'Inherit' !== $settings['text_font_family'] ) {
					$ff = esc_attr( $settings['text_font_family'] );
					$css .= $strong_specific . ', ' . $strong_specific . ' * { font-family: "' . $ff . '", sans-serif !important; }';
					$css .= $strong_specific . ' .dashicons, ' . $strong_specific . ' .dashicons:before, ' . $strong_specific . ' .dashicons-before:before { font-family: dashicons !important; }';
				}
			}

			// Forgot password screen (login-action-lostpassword).
			$forgot_bg_color = get_option( 'aio_login_forgot_background_color', '' );
			if ( ! empty( $forgot_bg_color ) ) {
				$css .= 'body.login.login-action-lostpassword { background-color: ' . esc_attr( $forgot_bg_color ) . ' !important; }';
			}

			$forgot_bg_image = get_option( 'aio_login_forgot_background_image', 0 );
			if ( ! empty( $forgot_bg_image ) ) {
				$forgot_url = wp_get_attachment_url( (int) $forgot_bg_image );
				if ( ! empty( $forgot_url ) ) {
					$css .= 'body.login.login-action-lostpassword { background-image: url(' . esc_url( $forgot_url ) . ') !important; background-size: cover !important; background-position: center !important; background-repeat: no-repeat !important; }';
				}
			}

			// Mobile background image override (same specificity as desktop $strong_specific, else desktop wins).
			$mobile_bg = get_option( 'aio_login_background_image_mobile', 0 );
			if ( ! empty( $mobile_bg ) ) {
				$mobile_url = wp_get_attachment_url( (int) $mobile_bg );
				if ( ! empty( $mobile_url ) ) {
					$mob_bg_size = $bg_size ? $bg_size : 'cover';
					$mob_bg_pos  = $bg_position ? $bg_position : 'center';
					$mob_bg_rep  = $bg_repeat ? $bg_repeat : 'no-repeat';
					$css .= '@media (max-width: 782px){ ' . $strong_specific . ' { background-image: url(' . esc_url( $mobile_url ) . ') !important; background-size: ' . esc_attr( $mob_bg_size ) . ' !important; background-position: ' . esc_attr( $mob_bg_pos ) . ' !important; background-repeat: ' . esc_attr( $mob_bg_rep ) . ' !important; background-attachment: scroll !important; }';
					if ( isset( $meta['bg_type'] ) && 'split' === $meta['bg_type'] ) {
						$css .= ' #login-designer-background { background-image: url(' . esc_url( $mobile_url ) . ') !important; background-size: cover !important; background-position: center !important; background-repeat: no-repeat !important; }';
					}
					$css .= ' }';
				}
			}

			// Define dark themes for adaptive UI
			$dark_themes = array('aio-login__template-02', 'aio-login__template-05', 'aio-login__template-08');
			$is_dark_theme = in_array($template_class, $dark_themes);

			// Global Login Improvements (Errors, Messages, Animations)
			$css .= '
			/* Premium Error & Message Styling */
			body.login #login_error, 
			body.login .message, 
			body.login .success {';

			if ($is_dark_theme) {
				$css .= '
				background: rgba(30, 41, 59, 0.8) !important;
				backdrop-filter: blur(10px);
				border: 1px solid rgba(255, 255, 255, 0.1) !important;
				border-left: 4px solid #ef4444 !important;
				color: #ffffff !important;';
			} else {
				$css .= '
				background: #ffffff !important;
				border: none !important;
				border-left: 4px solid #ef4444 !important;
				box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05) !important;
				color: #1e293b !important;';
			}

			$css .= '
				border-radius: 8px !important;
				padding: 15px 20px !important;
				margin-bottom: 25px !important;
				line-height: 1.5 !important;
				position: relative !important;
				width: 100% !important;
				box-sizing: border-box !important;
			}

			body.login .message, 
			body.login .success {
				border-left-color: #10b981 !important;
			}

			/* Template Specific Layout Fixes for Errors */
			
			/* Template 06 - Fixed Width Alignment */
			body.login.aio-login__template-06 #login_error,
			body.login.aio-login__template-06 .message {
				width: 350px !important;
				margin-left: 0 !important;
				margin-right: 0 !important;
				position: relative;
				z-index: 5;
			}

			/* Template 09 - Errors/messages in grid column 2 row 1 (above form), not over hero */
			body.login.aio-login__template-09 #login {
				position: relative !important;
			}
			body.login.aio-login__template-09 #login #login_error,
			body.login.aio-login__template-09 #login #login-message,
			body.login.aio-login__template-09 #login .message {
				position: relative !important;
				grid-column: 2 !important;
				grid-row: 1 !important;
				left: auto !important;
				top: auto !important;
				width: 100% !important;
				max-width: 380px !important;
				margin: 0 0 14px 0 !important;
				z-index: 200 !important;
				box-sizing: border-box !important;
			}

			/* Micro-Animations */
			@keyframes aioShake {
				0%, 100% { transform: translateX(0); }
				25% { transform: translateX(-5px); }
				75% { transform: translateX(5px); }
			}

			@keyframes aioFadeInUp {
				from { opacity: 0; transform: translateY(10px); }
				to { opacity: 1; transform: translateY(0); }
			}

			body.login #login_error {
				animation: aioShake 0.4s cubic-bezier(.36,.07,.19,.97) both !important;
			}

			body.login .message, 
			body.login .success {
				animation: aioFadeInUp 0.5s ease-out both !important;
			}

			/* Links inside errors */
			body.login #login_error a {
				color: #ef4444 !important;
				font-weight: 600 !important;
				text-decoration: underline !important;
			}
			';

			// Cross-template UI stability fixes (safe: layout only, no theme/color overrides).
			// Exclude full-width / custom submit layouts (Deep Glass, Corporate Pro, Vibrant Duo, Elegant Frost, Future Tech).
			$tpl_submit_compat = ':not(.aio-login__template-05):not(.aio-login__template-06):not(.aio-login__template-07):not(.aio-login__template-08):not(.aio-login__template-09)';
			$css .= '
			body.login[class*="aio-login__template-"] #login,
			body.login[class*="aio-login__template-"] #login * {
				box-sizing: border-box;
			}
			body.login[class*="aio-login__template-"] #login form input:not([type="checkbox"]):not([type="radio"]),
			body.login[class*="aio-login__template-"] #login form select,
			body.login[class*="aio-login__template-"] #login form textarea {
				max-width: 100% !important;
			}
			body.login[class*="aio-login__template-"]' . $tpl_submit_compat . ' #login form p.submit {
				display: flex !important;
				align-items: center !important;
				justify-content: flex-end !important;
				gap: 10px !important;
				flex-wrap: wrap !important;
			}
			body.login[class*="aio-login__template-"]' . $tpl_submit_compat . ' #login form p.submit input[type="submit"] {
				float: none !important;
				margin-left: auto !important;
			}
			body.login[class*="aio-login__template-"]' . $tpl_submit_compat . ' #login form p.forgetmenot {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				margin: 0;
			}
			body.login[class*="aio-login__template-"]' . $tpl_submit_compat . ' #login form .button.wp-hide-pw {
				min-height: 40px;
			}
			body.login[class*="aio-login__template-"] #login #login_error,
			body.login[class*="aio-login__template-"] #login .message,
			body.login[class*="aio-login__template-"] #login .success {
				max-width: 100% !important;
				overflow-wrap: break-word;
			}
			@media (max-width: 782px) {
				body.login[class*="aio-login__template-"] #login {
					width: calc(100vw - 32px) !important;
					max-width: 420px !important;
					margin-left: auto !important;
					margin-right: auto !important;
				}
				body.login[class*="aio-login__template-"] #login form {
					padding-left: 18px !important;
					padding-right: 18px !important;
				}
			}
			';

			return $css;
		}

		/**
		 * Login footer
		 */
		public function login_footer()
		{
			$tpl   = $this->get_template();
			$class = ( is_array( $tpl ) && isset( $tpl['class'] ) ) ? $tpl['class'] : '';

			if ( 'aio-login__template-01' === $class || 'aio-login__template-04' === $class ) {
				echo '<div id="login-designer-background"></div>';
			}

			if ( 'aio-login__template-09' === $class ) {
				$user_ph  = wp_json_encode( __( 'Username or Email Address', 'default' ) );
				$pass_ph  = wp_json_encode( __( 'Password', 'default' ) );
				$uname_ph = wp_json_encode( __( 'Username', 'default' ) );
				$email_ph = wp_json_encode( __( 'Email', 'default' ) );
				$script   = 'document.addEventListener("DOMContentLoaded",function(){var u=document.getElementById("user_login"),p=document.getElementById("user_pass"),em=document.getElementById("user_email"),f=document.getElementById("loginform"),lf=document.getElementById("lostpasswordform"),rf=document.getElementById("registerform");if(u&&!u.getAttribute("placeholder")){u.setAttribute("placeholder",rf?' . $uname_ph . ':' . $user_ph . ');}if(p&&!p.getAttribute("placeholder")){p.setAttribute("placeholder",' . $pass_ph . ');}if(em&&!em.getAttribute("placeholder")){em.setAttribute("placeholder",' . $email_ph . ');}function aioT09MoveHeaderIntoForm(form){if(!form){return;}var nodes=[],om=document.querySelector("#login > .message");if(om&&om.parentNode!==form){nodes.push(om);}var m=document.getElementById("login-message");if(m&&m.parentNode!==form){nodes.push(m);}var err=document.getElementById("login_error");if(err){err.classList.add("aio-login__template-09-login-error");if(err.parentNode!==form){nodes.push(err);}}var lh=document.querySelector("#login > h1");if(lh&&lh.parentNode&&lh.parentNode.id==="login"){nodes.push(lh);}for(var i=0;i<nodes.length;i++){form.insertBefore(nodes[i],form.firstChild);}var core=document.querySelectorAll("#login > #nav, #login > #backtoblog");for(var c=0;c<core.length;c++){core[c].remove();}var links=form.querySelector(".aio-login__template-09-footer-links"),submit=form.querySelector("p.submit");if(links&&submit&&submit.parentNode===form){submit.insertAdjacentElement("afterend",links);}var dup=form.querySelectorAll(".aio-login__template-09-footer-links");for(var d=dup.length-1;d>0;d--){dup[d].remove();}}if(f){aioT09MoveHeaderIntoForm(f);}else if(lf){aioT09MoveHeaderIntoForm(lf);}else if(rf){aioT09MoveHeaderIntoForm(rf);}});';
				if ( function_exists( 'wp_print_inline_script_tag' ) ) {
					wp_print_inline_script_tag( $script );
				} else {
					echo '<script>' . $script . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}

			if ( 'aio-login__template-06' === $class ) {
				$script = 'document.addEventListener("DOMContentLoaded",function(){var login=document.getElementById("login");if(!login||login.querySelector(".aio-login__template-06-scroll"))return;var pane=document.createElement("div");pane.className="aio-login__template-06-scroll";Array.prototype.slice.call(login.children).forEach(function(n){pane.appendChild(n);});login.insertBefore(pane,login.firstChild);var ls=document.querySelector("body.login .language-switcher");if(ls&&!login.contains(ls)){pane.appendChild(ls);}});';
				if ( function_exists( 'wp_print_inline_script_tag' ) ) {
					wp_print_inline_script_tag( $script );
				} else {
					echo '<script>' . $script . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}

			// Modern centered (default / template-03): match core login — labels only, no placeholders.
			if ( 'aio-login__template-03' === $class ) {
				echo '<script>document.addEventListener("DOMContentLoaded",function(){var u=document.getElementById("user_login"),p=document.getElementById("user_pass");if(u){u.removeAttribute("placeholder");}if(p){p.removeAttribute("placeholder");}});</script>';
			} else {
				// Other templates: optional placeholders (WP core doesn't set them).
				$user_ph = wp_json_encode(__('Username or Email Address', 'default'));
				$pass_ph = wp_json_encode(__('Password', 'default'));
				echo '<script>document.addEventListener("DOMContentLoaded",function(){var u=document.getElementById("user_login"),p=document.getElementById("user_pass");if(u&&!u.getAttribute("placeholder")){u.setAttribute("placeholder",' . $user_ph . ');}if(p&&!p.getAttribute("placeholder")){p.setAttribute("placeholder",' . $pass_ph . ');}});</script>';
			}
		}

		/**
		 * Get instance.
		 *
		 * @return Login_Customization_Output_Pro_Fallback
		 */
		public static function get_instance()
		{
			static $instance = null;

			if (null === $instance) {
				$instance = new self();
			}

			return $instance;
		}
	}
}
