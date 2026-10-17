<?php
/**
 * Class Login_Customization_Output
 *
 * @package AIO Login
 */

namespace AIO_Login\Login_Customization;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Login_Customization\\Login_Customization_Output' ) ) {
	/**
	 * Class Login_Customization_Output
	 */
	class Login_Customization_Output {
		/**
		 * Logo.
		 *
		 * @var int $logo Logo.
		 */
		private $logo;

		/**
		 * Logo URL.
		 *
		 * @var string $logo_url Logo URL.
		 */
		private $logo_url;

		/**
		 * Logo width.
		 *
		 * @var int $logo_width Logo width.
		 */
		private $logo_width;

		/**
		 * Logo height.
		 *
		 * @var int $logo_height Logo height.
		 */
		private $logo_height;

		/**
		 * Logo margin bottom.
		 *
		 * @var int $logo_margin_bottom Logo margin bottom.
		 */
		private $logo_margin_bottom;

		/**
		 * Background color.
		 *
		 * @var string $background_color Background color.
		 */
		private $background_color;

		/**
		 * Background image.
		 *
		 * @var string $background_image Background image.
		 */
		private $background_image;

		/**
		 * Login_Customization_Output constructor.
		 */
		private function __construct() {

			$this->logo               = get_option( 'aio_login_logo', false );
			$this->logo_url           = get_option( 'aio_login_logo_url', '' );
			$this->logo_width         = get_option( 'aio_login_logo_width', '' );
			$this->logo_height        = get_option( 'aio_login_logo_height', '' );
			$this->logo_margin_bottom = get_option( 'aio_login_margin_bottom', '' );

			$this->background_color = get_option( 'aio_login_background_color', '' );
			$this->background_image = get_option( 'aio_login_background_image', false );

			$this->logo             = Login_Customization::file_exists( $this->logo, true );
			$this->background_image = Login_Customization::file_exists( $this->background_image );

			add_action( 'login_enqueue_scripts', array( $this, 'login_output' ), 15 );
			// After template CSS is registered so Additional CSS prints last and overrides template !important rules.
			add_action( 'login_enqueue_scripts', array( $this, 'output_additional_login_css_last' ), 999 );
			add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_language_switcher_layout' ), 1000 );
			add_action( 'login_footer', array( $this, 'print_login_language_switcher_move_script' ), 999 );
			add_filter( 'login_headerurl', array( $this, 'login_header_url' ) );
			add_filter( 'login_headertext', array( $this, 'login_header_text' ) );
		}

		/**
		 * Core prints the language switcher after #login closes. Move it into the login panel and keep one row (icon + select + button).
		 *
		 * @return void
		 */
		public function enqueue_login_language_switcher_layout() {
			$css = '
body.login:not(.interim-login) #login > .language-switcher,
body.login:not(.interim-login) #loginform > .language-switcher,
body.login:not(.interim-login) #lostpasswordform > .language-switcher,
body.login:not(.interim-login) #registerform > .language-switcher {
	margin: 0 auto !important;
	padding: 12px 0 8px !important;
	width: 100% !important;
	max-width: 100% !important;
	box-sizing: border-box !important;
}
body.login:not(.interim-login) #login > .language-switcher form#language-switcher,
body.login:not(.interim-login) #loginform > .language-switcher form#language-switcher,
body.login:not(.interim-login) #lostpasswordform > .language-switcher form#language-switcher,
body.login:not(.interim-login) #registerform > .language-switcher form#language-switcher {
	display: flex !important;
	flex-direction: row !important;
	flex-wrap: nowrap !important;
	align-items: center !important;
	justify-content: center !important;
	gap: 8px !important;
	margin: 0 auto !important;
	width: 100% !important;
	max-width: 100% !important;
	box-sizing: border-box !important;
}
body.login:not(.interim-login) #login > .language-switcher label,
body.login:not(.interim-login) #loginform > .language-switcher label,
body.login:not(.interim-login) #lostpasswordform > .language-switcher label,
body.login:not(.interim-login) #registerform > .language-switcher label {
	display: inline-flex !important;
	align-items: center !important;
	margin: 0 !important;
	flex: 0 0 auto !important;
}
body.login:not(.interim-login) form#language-switcher select,
body.login:not(.interim-login) #login > .language-switcher select,
body.login:not(.interim-login) #loginform > .language-switcher select,
body.login:not(.interim-login) #lostpasswordform > .language-switcher select,
body.login:not(.interim-login) #registerform > .language-switcher select {
	line-height: 1.8 !important;
	height: auto !important;
	vertical-align: middle !important;
	margin: 0 !important;
	flex: 1 1 12rem !important;
	min-width: 140px !important;
	width: auto !important;
	max-width: none !important;
	align-self: center !important;
	padding-right: 2.25rem !important;
	background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%2350575e%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Cpolyline points=%226,9 12,15 18,9%22/%3E%3C/svg%3E") !important;
	background-repeat: no-repeat !important;
	background-position: right 0.65rem center !important;
	background-size: 1rem !important;
	-webkit-appearance: none !important;
	appearance: none !important;
}
body.login:not(.interim-login) form#language-switcher input[type="submit"].button,
body.login:not(.interim-login) #login > .language-switcher .button,
body.login:not(.interim-login) #loginform > .language-switcher .button,
body.login:not(.interim-login) #lostpasswordform > .language-switcher .button,
body.login:not(.interim-login) #registerform > .language-switcher .button {
	width: auto !important;
	min-width: 0 !important;
	max-width: none !important;
	height: auto !important;
	min-height: 36px !important;
	margin: 0 !important;
	flex: 0 0 auto !important;
	flex-grow: 0 !important;
	align-self: center !important;
	display: inline-block !important;
	float: none !important;
	white-space: nowrap !important;
	padding: 0 14px !important;
	font-size: 13px !important;
	line-height: 2.15384615 !important;
	box-sizing: border-box !important;
	box-shadow: none !important;
	text-shadow: none !important;
}
@media screen and (max-width: 480px) {
	body.login:not(.interim-login) #login > .language-switcher form#language-switcher,
	body.login:not(.interim-login) #loginform > .language-switcher form#language-switcher,
	body.login:not(.interim-login) #lostpasswordform > .language-switcher form#language-switcher,
	body.login:not(.interim-login) #registerform > .language-switcher form#language-switcher {
		flex-wrap: wrap !important;
		justify-content: center !important;
	}
}
';
			wp_add_inline_style( 'login', $css );
		}

		/**
		 * Relocate .language-switcher into the login UI (after footer links, or inside the form for template-09 grid).
		 *
		 * @return void
		 */
		public function print_login_language_switcher_move_script() {
			if ( ! empty( $GLOBALS['interim_login'] ) ) {
				return;
			}
			$script = $this->get_login_language_switcher_move_script();
			if ( function_exists( 'wp_print_inline_script_tag' ) ) {
				wp_print_inline_script_tag( $script );
			} else {
				echo '<script>' . $script . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		/**
		 * Inline script: move language switcher node once DOM is ready.
		 *
		 * @return string
		 */
		private function get_login_language_switcher_move_script() {
			return <<<JS
document.addEventListener('DOMContentLoaded',function(){var login=document.getElementById('login');if(!login){return;}var ls=document.querySelector('body.login .language-switcher');var moveLs=!!ls&&!login.contains(ls);if(document.body.classList.contains('aio-login__template-01')){if(moveLs){var f01=document.getElementById('loginform')||document.getElementById('lostpasswordform')||document.getElementById('registerform');if(f01){var n01=document.getElementById('nav');var b01=document.getElementById('backtoblog');if(n01&&n01.parentNode===login){f01.appendChild(n01);}if(b01&&b01.parentNode===login){f01.appendChild(b01);}f01.appendChild(ls);}else{login.appendChild(ls);}}if(!login.querySelector('.aio-login__template-01-scroll')){var pane=document.createElement('div');pane.className='aio-login__template-01-scroll';Array.prototype.slice.call(login.children).forEach(function(n){pane.appendChild(n);});login.insertBefore(pane,login.firstChild);}return;}if(!moveLs){return;}if(document.body.classList.contains('aio-login__template-09')){var pf=document.getElementById('loginform')||document.getElementById('lostpasswordform')||document.getElementById('registerform');if(pf){pf.appendChild(ls);}else{login.appendChild(ls);}return;}var bb=document.getElementById('backtoblog');var nav=document.getElementById('nav');var anchor=(bb&&bb.parentNode===login)?bb:((nav&&nav.parentNode===login)?nav:null);if(anchor){if(anchor.nextSibling){anchor.parentNode.insertBefore(ls,anchor.nextSibling);}else{anchor.parentNode.appendChild(ls);}}else{login.appendChild(ls);}});
JS;
		}

		/**
		 * Login output.
		 */
		public function login_output() {
			$custom_css = '';

			$disable_logo = get_option( 'aio_login_disable_logo', false );
			$logo_hidden  = ( true === $disable_logo || 1 === $disable_logo || '1' === $disable_logo || 'on' === strtolower( (string) $disable_logo ) );

			if ( ! $logo_hidden && ! empty( $this->logo ) ) {
				$custom_css .= '
					.login .wp-login-logo a {
						background-image: url(' . esc_url( $this->logo ) . ');
						background-size: contain;
					}
				';
			}

			if ( ! $logo_hidden && ! empty( $this->logo_width ) ) {
				$custom_css .= '
					.login .wp-login-logo a {
						width: ' . esc_attr( $this->logo_width ) . 'px;
					}
				';
			}

			if ( ! $logo_hidden && ! empty( $this->logo_height ) ) {
				$custom_css .= '
					.login .wp-login-logo a {
						height: ' . esc_attr( $this->logo_height ) . 'px;
					}
				';
			}

			if ( ! $logo_hidden && ! empty( $this->logo_margin_bottom ) ) {
				$custom_css .= '
					.login .wp-login-logo a {
						margin-bottom: ' . esc_attr( $this->logo_margin_bottom ) . 'px;
					}
				';
			}

			// Skip default gray only when selected template uses a dark body (option slugs for template-02/05/08).
			$dark_template_slugs = array( 'template-2', 'template-4', 'template-7' );
			$tpl_key             = get_option( 'aio_login__customization_templates', 'default' );
			$is_dark_body        = in_array( (string) $tpl_key, $dark_template_slugs, true );
			$bg_trim             = trim( (string) $this->background_color );
			$is_default_wp_gray  = ( '' !== $bg_trim && 0 === strcasecmp( $bg_trim, '#f1f1f1' ) );
			if ( ! empty( $this->background_color ) && ( ! $is_default_wp_gray || ! $is_dark_body ) ) {
				$custom_css .= '
					body.login {
						background-color: ' . esc_attr( $this->background_color ) . ';
					}
				';
			}

			if ( ! empty( $this->background_image ) ) {
				$custom_css .= '
					body.login {
						background-image: url(' . esc_url( $this->background_image ) . ');
						background-size: cover;
						background-position: center;
						background-repeat: no-repeat;
						
					}
				';
			}

			$custom_css = apply_filters( 'aio_login__custom_css', $custom_css );

			if ( ! empty( $custom_css ) ) {
				// Print on the AIO template handle when present so rules win over template .css (e.g. template-09
				// logo 100px !important). Inline on `login` loads before aio-login-*-template and was overridden.
				$handle = 'login';
				if ( wp_style_is( 'aio-login-pro-template', 'enqueued' ) ) {
					$handle = 'aio-login-pro-template';
				} elseif ( wp_style_is( 'aio-login-free-template', 'enqueued' ) ) {
					$handle = 'aio-login-free-template';
				}
				/**
				 * Style handle for merged login customization CSS (free base + aio_login__custom_css filter).
				 *
				 * @param string $handle      Registered style handle.
				 * @param string $custom_css  Full CSS string about to be printed.
				 */
				$handle = apply_filters( 'aio_login__custom_css_style_handle', $handle, $custom_css );
				if ( is_string( $handle ) && '' !== $handle ) {
					wp_add_inline_style( $handle, $custom_css );
				}
			}
		}

		/**
		 * Output "Additional CSS" on the last AIO template stylesheet handle so it always wins over template files.
		 *
		 * When rules were inlined on `login`, the template file (`aio-login-*-template`) loaded after `login`
		 * and could override the same specificity / !important as user rules.
		 *
		 * @return void
		 */
		public function output_additional_login_css_last() {
			$css = get_option( 'aio_login_custom-css', '' );
			if ( ! is_string( $css ) || '' === trim( $css ) ) {
				return;
			}

			$handle = 'login';
			if ( wp_style_is( 'aio-login-pro-template', 'enqueued' ) ) {
				$handle = 'aio-login-pro-template';
			} elseif ( wp_style_is( 'aio-login-free-template', 'enqueued' ) ) {
				$handle = 'aio-login-free-template';
			}

			/**
			 * Which style handle Additional CSS is printed on (default: template handle, else `login`).
			 *
			 * @param string $handle Style handle.
			 * @param string $css    Raw Additional CSS.
			 */
			$handle = apply_filters( 'aio_login_additional_css_style_handle', $handle, $css );

			if ( ! is_string( $handle ) || '' === $handle ) {
				return;
			}

			wp_add_inline_style( $handle, trim( $css ) );
		}

		/**
		 * Login header URL.
		 *
		 * @param string $url URL.
		 *
		 * @return string
		 */
		public function login_header_url( $url ) {
			if ( ! empty( $this->logo_url ) ) {
				$url = $this->logo_url;
			}
			return $url;
		}

		/**
		 * Login logo link text (core prints this inside the anchor; also used for accessibility).
		 *
		 * @param string $text Default text from core.
		 * @return string
		 */
		public function login_header_text( $text ) {
			$custom = get_option( 'aio_login_logo_title', '' );
			return ! empty( $custom ) ? sanitize_text_field( $custom ) : $text;
		}

		/**
		 * Get instance.
		 *
		 * @return Login_Customization_Output
		 */
		public static function get_instance() {
			static $instance = null;

			if ( null === $instance ) {
				$instance = new Login_Customization_Output();
			}

			return $instance;
		}
	}
}
