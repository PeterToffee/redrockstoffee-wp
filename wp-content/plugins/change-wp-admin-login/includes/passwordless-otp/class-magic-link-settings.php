<?php
/**
 * Magic link (passwordless email link) admin settings.
 *
 * @package AIO_Login
 */

namespace AIO_Login\Passwordless_Otp;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Passwordless_Otp\\Magic_Link_Settings' ) ) {
	/**
	 * Magic_Link_Settings
	 */
	final class Magic_Link_Settings {

		public const NONCE_ACTION = 'aio-login-magic-link';

		/**
		 * Stores login-link enabled state while Pro is inactive.
		 */
		public const STASH_OPTION = 'aio_login_magic_link_enable_stashed';

		/**
		 * Activation defaults.
		 */
		public static function set_defaults() {
			$defaults = array(
				'aio_login_magic_link_enable'                    => 'off',
				'aio_login_magic_link_validity_value'            => '10',
				'aio_login_magic_link_validity_unit'             => 'minutes',
				'aio_login_magic_link_max_requests'              => '5',
				'aio_login_magic_link_skip_2fa'                  => 'on',
				'aio_login_woocommerce_magic_link_enabled'       => 'off',
				'aio_login_woocommerce_magic_link_login'         => 'on',
				'aio_login_woocommerce_magic_link_registration'  => 'on',
				'aio_login_woocommerce_magic_link_checkout'      => 'on',
			);

			foreach ( $defaults as $key => $value ) {
				if ( false === get_option( $key, false ) ) {
					add_option( $key, $value, '', false );
				}
			}
		}

		/**
		 * @return bool
		 */
		public static function is_enabled() {
			return 'on' === get_option( 'aio_login_magic_link_enable', 'off' );
		}

		/**
		 * Pro plugin basename values (standard + distribution copies).
		 *
		 * @return string[]
		 */
		public static function get_pro_plugin_basenames() {
			return array( 'aio-login-pro/aio-login-pro.php' );
		}

		/**
		 * Turn off login link when Pro is not available; stash prior enabled state.
		 */
		public static function disable_when_pro_unavailable() {
			if ( self::is_enabled() ) {
				update_option( self::STASH_OPTION, 'on', false );
				update_option( 'aio_login_magic_link_enable', 'off', false );
				self::sync_woocommerce_magic_link_with_global();
			}
		}

		/**
		 * Restore login link enabled state after Pro becomes available again.
		 */
		public static function restore_when_pro_available() {
			if ( ! \AIO_Login\AIO_Login::has_pro() ) {
				return;
			}

			if ( 'on' !== get_option( self::STASH_OPTION, 'off' ) ) {
				return;
			}

			update_option( 'aio_login_magic_link_enable', 'on', false );
			delete_option( self::STASH_OPTION );
			self::clear_woocommerce_settings_cache();
		}

		/**
		 * When Pro is deactivated, do not leave login link enabled in free.
		 *
		 * @param string $plugin               Plugin basename.
		 * @param bool   $network_deactivating Network context.
		 */
		public static function handle_pro_plugin_deactivated( $plugin, $network_deactivating ) {
			unset( $network_deactivating );

			if ( ! in_array( (string) $plugin, self::get_pro_plugin_basenames(), true ) ) {
				return;
			}

			self::disable_when_pro_unavailable();
		}

		/**
		 * When Pro is reactivated, restore login link if it was enabled before deactivation.
		 *
		 * @param string $plugin       Plugin basename.
		 * @param bool   $network_wide Network context.
		 */
		public static function handle_pro_plugin_activated( $plugin, $network_wide ) {
			unset( $network_wide );

			if ( ! in_array( (string) $plugin, self::get_pro_plugin_basenames(), true ) ) {
				return;
			}

			self::restore_when_pro_available();
		}

		/**
		 * Safety net if Pro was removed/deactivated before hooks ran.
		 */
		public static function sync_enable_state_with_pro() {
			if ( \AIO_Login\AIO_Login::has_pro() ) {
				self::restore_when_pro_available();
				return;
			}

			if ( self::is_enabled() ) {
				self::disable_when_pro_unavailable();
			}
		}

		/**
		 * Pro + admin toggle — required for login page UI and send handler.
		 *
		 * @return bool
		 */
		public static function is_login_available() {
			return \AIO_Login\AIO_Login::has_pro() && self::is_enabled();
		}

		/**
		 * WooCommerce integration section enabled in admin.
		 *
		 * @return bool
		 */
		public static function is_woocommerce_integration_enabled() {
			return 'on' === get_option( 'aio_login_woocommerce_magic_link_enabled', 'off' );
		}

		/**
		 * @return bool
		 */
		public static function is_woocommerce_configured() {
			return self::is_login_available();
		}

		/**
		 * Clear WooCommerce integration settings transients (Pro).
		 */
		public static function clear_woocommerce_settings_cache() {
			$keys = array(
				'aio_login_woocommerce_settings_cache_v4',
				'aio_login_woocommerce_settings_cache_v5',
				'aio_login_woocommerce_settings_cache_v6',
				'aio_login_woocommerce_settings_cache_v7',
			);

			foreach ( $keys as $key ) {
				delete_transient( $key );
			}
		}

		/**
		 * Keep WooCommerce Login With Link in sync when global login link is off.
		 */
		public static function sync_woocommerce_magic_link_with_global() {
			if ( ! self::is_login_available() ) {
				update_option( 'aio_login_woocommerce_magic_link_enabled', 'off', false );
			}

			self::clear_woocommerce_settings_cache();
		}

		/**
		 * WooCommerce + passwordless login link available for storefront UI.
		 *
		 * @return bool
		 */
		public static function is_woocommerce_ui_available() {
			if ( ! self::is_woocommerce_configured() ) {
				return false;
			}
			if ( ! class_exists( 'WooCommerce' ) ) {
				return false;
			}
			if ( 'on' !== get_option( 'aio_login_woocommerce_integration_enabled', 'off' ) ) {
				return false;
			}
			return self::is_woocommerce_integration_enabled();
		}

		/**
		 * @param string $context login|registration|checkout.
		 * @return bool
		 */
		public static function is_woocommerce_context_enabled( $context ) {
			$context = sanitize_key( (string) $context );
			if ( ! in_array( $context, array( 'login', 'registration', 'checkout' ), true ) ) {
				return false;
			}
			if ( ! self::is_woocommerce_ui_available() ) {
				return false;
			}
			$key = 'aio_login_woocommerce_magic_link_' . $context;
			return 'on' === get_option( $key, 'on' );
		}

		/**
		 * @return bool
		 */
		public static function should_skip_two_factor() {
			return 'on' === get_option( 'aio_login_magic_link_skip_2fa', 'on' );
		}

		/**
		 * Validity in minutes for tokens / rate limits.
		 *
		 * @return int
		 */
		public static function get_validity_minutes() {
			$value = max( 1, absint( get_option( 'aio_login_magic_link_validity_value', 10 ) ) );
			$unit  = sanitize_key( (string) get_option( 'aio_login_magic_link_validity_unit', 'minutes' ) );

			switch ( $unit ) {
				case 'hours':
					return $value * 60;
				case 'days':
					return $value * 24 * 60;
				default:
					return $value;
			}
		}

		/**
		 * @return int
		 */
		public static function get_max_requests() {
			return max( 1, min( 100, absint( get_option( 'aio_login_magic_link_max_requests', 5 ) ) ) );
		}

		/**
		 * @return array<string, mixed>
		 */
		public static function get_admin_settings() {
			return array(
				'nonce'                  => wp_create_nonce( self::NONCE_ACTION ),
				'magic_link_enable'      => self::is_login_available(),
				'magic_link_validity'    => (string) max( 1, absint( get_option( 'aio_login_magic_link_validity_value', 10 ) ) ),
				'magic_link_validity_unit' => self::sanitize_validity_unit( get_option( 'aio_login_magic_link_validity_unit', 'minutes' ) ),
				'magic_link_max_requests' => (string) self::get_max_requests(),
				'magic_link_skip_2fa'    => self::should_skip_two_factor(),
				'has_pro'                => \AIO_Login\AIO_Login::has_pro(),
			);
		}

		/**
		 * @param mixed $unit Raw unit.
		 * @return string minutes|hours|days
		 */
		public static function sanitize_validity_unit( $unit ) {
			$unit = sanitize_key( (string) $unit );
			if ( in_array( $unit, array( 'minutes', 'hours', 'days' ), true ) ) {
				return $unit;
			}
			return 'minutes';
		}
	}

	add_action( 'deactivated_plugin', array( Magic_Link_Settings::class, 'handle_pro_plugin_deactivated' ), 10, 2 );
	add_action( 'activated_plugin', array( Magic_Link_Settings::class, 'handle_pro_plugin_activated' ), 10, 2 );
	add_action( 'plugins_loaded', array( Magic_Link_Settings::class, 'sync_enable_state_with_pro' ), 20 );
}
