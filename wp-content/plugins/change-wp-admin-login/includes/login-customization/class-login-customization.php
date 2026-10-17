<?php
/**
 * Class Login_Customization
 *
 * @package AIO Login
 */

namespace AIO_Login\Login_Customization;

use AIO_Login\Helper\Helper;
use WpOrg\Requests\Exception;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Login_Customization\\Login_Customization' ) ) {
	/**
	 * Class Login_Customization
	 */
	class Login_Customization {
		/**
		 * Login_Customization constructor.
		 */
		private function __construct() {
			add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		}

		/**
		 * Register REST API routes.
		 */
		public function rest_api_init() {
			register_rest_route(
				'aio-login/custom-css',
				'/get-settings',
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get_custom_css_settings' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);
			register_rest_route(
				'aio-login/custom-css',
				'save-custom-css-settings',
				array(
					'methods'  => 'POST',
					'callback' => array( $this, 'save_custom_css_settings' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				),
			);

			register_rest_route(
				'aio-login/background',
				'/get-settings',
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get_background_settings' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);
			register_rest_route(
				'aio-login/background',
				'/save-settings',
				array(
					'methods'  => 'POST',
					'callback' => array( $this, 'save_background_settings' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);

			register_rest_route(
				'aio-login/logo',
				'/get-settings',
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get_logo_settings' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);
			register_rest_route(
				'aio-login/logo',
				'/save-settings',
				array(
					'methods'  => 'POST',
					'callback' => array( $this, 'save_logo_settings' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);
		}

		public function get_custom_css_settings() {
			$option = get_option( 'aio_login_custom-css', '' );
			$nonce  = wp_create_nonce( 'aio-login-custom-css' );

			return rest_ensure_response( array(
				'custom_css' => $option,
				'nonce'      => $nonce,
			) );
		}

		public function save_custom_css_settings( $request ) {
			$params = $request->get_params();
			if ( isset( $params['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $params['_wpnonce'] ) ), 'aio-login-custom-css' ) ) {
				$custom_css = isset( $params['custom_css'] ) ? sanitize_text_field( wp_unslash( $params['custom_css'] ) ) : '';
				update_option( 'aio_login_custom-css', $custom_css );

				return rest_ensure_response(
					array(
						'success' => true,
						'message' => __( 'Custom CSS saved successfully', 'change-wp-admin-login' ),
					)
				);
			}

			return new \WP_Error(
				'unauthorized',
				array(
					'message' => __( 'Nonce verification failed', 'change-wp-admin-login' )
				),
				array(
					'status' => 401
				)
			);
		}

		public function get_background_settings() {
			$bg_color = get_option( 'aio_login_background_color', '#f1f1f1' );
			$bg_image_id = get_option( 'aio_login_background_image', 0 );
			$bg_image = self::file_exists( $bg_image_id );
			$nonce    = wp_create_nonce( 'aio-login-background' );

			return rest_ensure_response( array(
				'bg_color'    => $bg_color,
				'bg_image'    => $bg_image,
				'bg_image_id' => $bg_image_id,
				'nonce'       => $nonce,
			) );
		}

		public function save_background_settings( $request ) {
			$params = $request->get_params();
			if ( isset( $params['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $params['_wpnonce'] ) ), 'aio-login-background' ) ) {
				$bg_color = isset( $params['bg_color'] ) ? sanitize_text_field( wp_unslash( $params['bg_color'] ) ) : '#f1f1f1';
				$bg_image = isset( $params['bg_image'] ) ? sanitize_text_field( wp_unslash( $params['bg_image'] ) ) : 0;

				update_option( 'aio_login_background_color', $bg_color );
				update_option( 'aio_login_background_image', $bg_image );

				return rest_ensure_response(
					array(
						'success' => true,
						'message' => __( 'Settings saved successfully', 'change-wp-admin-login' ),
					)
				);
			}

			return new \WP_Error(
				'unauthorized',
				array(
					'message' => __( 'Nonce verification failed', 'change-wp-admin-login' )
				),
				array(
					'status' => 401
				)
			);
		}

		public function get_logo_settings() {
			$logo_id       = get_option( 'aio_login_logo', 0 );
			$url           = get_option( 'aio_login_logo_url', '' );
			$width         = get_option( 'aio_login_logo_width', 84 );
			$height        = get_option( 'aio_login_logo_height', 84 );
			$margin_bottom = get_option( 'aio_login_margin_bottom', 0 );

			$logo = self::file_exists( $logo_id, true );

			$nonce = wp_create_nonce( 'aio-login-logo' );

			return rest_ensure_response( array(
				'logo'          => $logo,
				'logo_id'       => $logo_id,
				'url'           => $url,
				'width'         => $width,
				'height'        => $height,
				'margin_bottom' => $margin_bottom,
				'nonce'         => $nonce,
				'default_image' => admin_url( 'images/wordpress-logo.svg' ),
			) );
		}

		public function save_logo_settings( $request ) {
			$params = $request->get_params();

			if ( isset( $params['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $params['_wpnonce'] ) ), 'aio-login-logo' ) ) {
				$logo_id       = 0;
				$redirect_url  = '';
				$logo_width    = 84;
				$logo_height   = 84;
				$margin_bottom = 0;

				if ( isset( $params['logo_id'] ) ) {
					$logo_id = sanitize_text_field( wp_unslash( $params['logo_id'] ) );
				}

				if ( isset( $params['redirect_url'] ) ) {
					$redirect_url = sanitize_text_field( wp_unslash( $params['redirect_url'] ) );
				}

				if ( isset( $params['logo_width'] ) ) {
					$logo_width = sanitize_text_field( wp_unslash( $params['logo_width'] ) );
				}

				if ( isset( $params['logo_height'] ) ) {
					$logo_height = sanitize_text_field( wp_unslash( $params['logo_height'] ) );
				}

				if ( isset( $params['margin_bottom'] ) ) {
					$margin_bottom = sanitize_text_field( wp_unslash( $params['margin_bottom'] ) );
				}

				update_option( 'aio_login_logo', $logo_id );
				update_option( 'aio_login_logo_url', $redirect_url );
				update_option( 'aio_login_logo_width', $logo_width );
				update_option( 'aio_login_logo_height', $logo_height );
				update_option( 'aio_login_margin_bottom', $margin_bottom );

				return rest_ensure_response( array(
					'success' => true,
					'message' => __( 'Settings saved successfully', 'change-wp-admin-login' ),
				) );
			}

			return new \WP_Error(
				'unauthorized',
				array(
					'message' => __( 'Nonce verification failed', 'change-wp-admin-login' )
				),
				array(
					'status' => 401
				)
			);
		}

		/**
		* Check if file exists.
		*
		* @param int  $file_id File.
		* @param bool $logo Logo.
		*
		* @return string
		*/
		public static function file_exists( $file_id, $logo = false ) {
			$file_path = get_attached_file( $file_id );
			if ( ! file_exists( $file_path ) ) {
				if ( $logo ) {
					return admin_url( 'images/wordpress-logo.svg' );
				}

				return '';
			}

			return wp_get_attachment_url( $file_id );
		}

		/**
		 * Get instance.
		 *
		 * @return Login_Customization
		 */
		public static function get_instance() {
			static $instance = null;

			if ( null === $instance ) {
				$instance = new Login_Customization();
			}

			return $instance;
		}
	}
}
