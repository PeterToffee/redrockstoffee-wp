<?php
/**
 * REST API for magic link admin settings.
 *
 * @package AIO_Login
 */

namespace AIO_Login\Passwordless_Otp;

use AIO_Login\Helper\Helper;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Passwordless_Otp\\Magic_Link_Admin_Rest' ) ) {
	/**
	 * Magic_Link_Admin_Rest
	 */
	final class Magic_Link_Admin_Rest {

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
				'aio-login/magic-link',
				'/get-settings',
				array(
					'methods'             => 'GET',
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
					'callback'            => array( $this, 'get_settings' ),
				)
			);

			register_rest_route(
				'aio-login/magic-link',
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
			return rest_ensure_response( Magic_Link_Settings::get_admin_settings() );
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

			if ( empty( $params['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $params['_wpnonce'] ) ), Magic_Link_Settings::NONCE_ACTION ) ) {
				return new \WP_Error( 'forbidden', __( 'Invalid security token.', 'change-wp-admin-login' ), array( 'status' => 403 ) );
			}

			if ( ! \AIO_Login\AIO_Login::has_pro() ) {
				return new \WP_Error( 'pro_required', __( 'Login Link requires AIO Login Pro.', 'change-wp-admin-login' ), array( 'status' => 403 ) );
			}

			update_option( 'aio_login_magic_link_enable', ! empty( $params['magic_link_enable'] ) ? 'on' : 'off', false );

			$validity = isset( $params['magic_link_validity'] ) ? absint( $params['magic_link_validity'] ) : 10;
			update_option( 'aio_login_magic_link_validity_value', (string) max( 1, $validity ), false );

			update_option(
				'aio_login_magic_link_validity_unit',
				Magic_Link_Settings::sanitize_validity_unit( $params['magic_link_validity_unit'] ?? 'minutes' ),
				false
			);

			$max_requests = isset( $params['magic_link_max_requests'] ) ? absint( $params['magic_link_max_requests'] ) : 5;
			update_option( 'aio_login_magic_link_max_requests', (string) max( 1, min( 100, $max_requests ) ), false );

			update_option(
				'aio_login_magic_link_skip_2fa',
				! empty( $params['magic_link_skip_2fa'] ) ? 'on' : 'off',
				false
			);

			Magic_Link_Settings::sync_woocommerce_magic_link_with_global();

			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Settings saved successfully.', 'change-wp-admin-login' ),
					'data'    => Magic_Link_Settings::get_admin_settings(),
				)
			);
		}
	}
}
