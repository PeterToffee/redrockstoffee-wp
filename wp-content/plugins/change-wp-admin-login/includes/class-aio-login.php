<?php
/**
 * Class AIO_Login
 *
 * @package All In One Login
 */

namespace AIO_Login;

use AIO_Login\Admin\Admin;
use AIO_Login\Change_WP_Admin_Login\Change_WP_Admin_Login;
use AIO_Login\Google_Recaptcha\Google_Recaptcha;
use AIO_Login\Helper\Helper;
use AIO_Login\Login_Controller\Failed_Logins;
use AIO_Login\Login_Controller\Login_Controller;
use AIO_Login\Login_Controller\Login_Redirection;
use AIO_Login\Login_Customization\Login_Customization;
use AIO_Login\Login_Customization\Login_Customization_Output;
use AIO_Login\Rest_API\Controller;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\AIO_Login' ) ) {
	/**
	 * Class AIO_Login
	 */
	class AIO_Login {
		/**
		 * Plugin dependencies
		 *
		 * @var array $dependencies Plugin dependencies.
		 */
		public static $dependencies = array();

		/**
		 * AIO_Login constructor.
		 */
		public function __construct() {
			$this->include_files();

			self::class_loader( Admin::class );
			self::class_loader( Change_WP_Admin_Login::class );
			self::class_loader( Google_Recaptcha::class );
			self::class_loader( Login_Controller::class );
			self::class_loader( Login_Redirection::class );
			self::class_loader( Login_Customization::class );
			self::class_loader( Login_Customization_Output::class );
			/*
			 * Pro loads after free; class_exists() here is too early and boots Pro_Fallback while Pro is active,
			 * duplicating template-09 footer markup and hooks.
			 */
			add_action(
				'plugins_loaded',
				static function () {
					if ( ! class_exists( 'AIO_Login_Pro\\Login_Customization\\Login_Customization_Output' ) ) {
						\AIO_Login\Login_Customization\Login_Customization_Output_Pro_Fallback::get_instance();
					}
				},
				20
			);
			self::class_loader( \AIO_Login_Pro\Login_Customization\Login_Customizer::class );
			self::class_loader( \AIO_Login\User_Enumeration_Protection\User_Enumeration_Protection::class );
			self::class_loader( \AIO_Login\Passwordless_Otp\OTP_Admin_Rest::class );
			self::class_loader( \AIO_Login\Passwordless_Otp\OTP_Login::class );
			self::class_loader( \AIO_Login\Passwordless_Otp\OTP_User_Phone::class );
			self::class_loader( \AIO_Login\Passwordless_Otp\Magic_Link_Admin_Rest::class );
			self::class_loader( \AIO_Login\Passwordless_Otp\Magic_Link_Login::class );

			$this->init();

			do_action( 'aio_login__plugin_init' );
		}

		/**
		 * Include files.
		 */
		private function include_files() {
			require_once AIO_LOGIN__DIR_PATH . 'includes/class-helper.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/captcha/class-captcha-validation.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/admin/class-admin-i18n.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/admin/class-admin.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/change-wp-admin-login/class-change-wp-admin-login.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/google-recaptcha/class-google-recaptcha.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/login-controller/class-login-controller.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/login-controller/class-login-redirection.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/login-controller/class-failed-logins.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/login-customization/class-login-customization.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/login-customization/class-login-customization-output.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/login-customization/class-login-customization-output-pro-fallback.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/login-customization/class-login-customizer.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/user-enumeration-protection/class-user-enumeration-protection.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/user-enumeration-protection/class-user-enumeration-activator.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/passwordless-otp/class-otp-lockout.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/passwordless-otp/class-otp-encryption.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/passwordless-otp/class-otp-country-codes.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/passwordless-otp/class-otp-settings.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/passwordless-otp/class-otp-captcha.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/passwordless-otp/class-otp-service.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/passwordless-otp/class-otp-email-sender.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/passwordless-otp/class-otp-admin-rest.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/passwordless-otp/class-otp-login.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/passwordless-otp/class-otp-user-phone.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/passwordless-otp/class-magic-link-settings.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/passwordless-otp/class-magic-link-admin-rest.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/passwordless-otp/class-magic-link-service.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/passwordless-otp/class-magic-link-email-sender.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/passwordless-otp/class-magic-link-login.php';
		}

		/**
		 * Class loader.
		 *
		 * @param string $class_name Class name.
		 */
		public static function class_loader( $class_name ) {
			$return = false;
			if ( class_exists( $class_name ) ) {
				if ( method_exists( $class_name, 'get_instance' ) ) {
					$return = $class_name::get_instance();

					self::$dependencies[ $class_name ] = $return;
				}
			}

			return $return;
		}

		/**
		 * Init.
		 */
		private function init() {
			register_activation_hook( AIO_LOGIN__FILE, array( $this, 'activate_plugin' ) );
			register_uninstall_hook( AIO_LOGIN__FILE, array( self::class, 'uninstall_plugin' ) );

			// Priority -10 so domain is loaded before other init callbacks that call __().
			add_action( 'init', array( $this, 'load_textdomain' ), -10 );
			add_action( 'init', array( $this, 'if_activation_hook_not_triggered' ) );

			if ( is_multisite() ) {
				add_action( 'wp_initialize_site', array( $this, 'new_site_registered' ) );
			}
		}

		/**
		 * Check if pro version is installed.
		 *
		 * @return bool
		 */
		public static function has_pro() {
			// Check if pro plugin is active via filter (set by pro plugin)
			$has_pro = apply_filters( 'aio_login_has_pro', false );
			
			if ( $has_pro ) {
				return true;
			}

			if ( function_exists( 'is_plugin_active' ) ) {
				if ( is_plugin_active( 'aio-login-pro/aio-login-pro.php' ) ) {
					return true;
				}
			}

			if ( class_exists( 'AIO_Login_Pro\\AIO_Login_Pro' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Activate plugin.
		 */
		public function activate_plugin() {
			Helper::create_table(
				'login_attempts',
				array(
					'user_login' => 'varchar(255) NOT NULL',
					'ip_address' => 'varchar(255) NOT NULL',
					'country'    => 'varchar(255) NOT NULL',
					'city'       => 'varchar(255) NOT NULL',
					'time'       => 'VARCHAR(255) NOT NULL',
					'user_agent' => 'varchar(255) NOT NULL',
					'status'     => 'varchar(255) NOT NULL DEFAULT ""',
				)
			);
			Helper::create_table(
				'login_lockouts',
				array(
					'ip_address' => 'varchar(255) NOT NULL',
					'country'    => 'varchar(255) NOT NULL',
					'city'       => 'varchar(255) NOT NULL',
					'time'       => 'bigint(20) NOT NULL',
					'user_agent' => 'varchar(255) NOT NULL',
				)
			);

			if ( ! get_option( 'aio_login__version' ) ) {
				update_option( 'aio_login__version', AIO_LOGIN__VERSION );
			}

			// Create database tables
			\AIO_Login\User_Enumeration_Protection\User_Enumeration_Activator::create_table();

			\AIO_Login\Passwordless_Otp\OTP_Settings::set_defaults();

			// Set default options
			if ( ! get_option( 'aio_login_limit_attempts_enable' ) ) {
				update_option( 'aio_login_limit_attempts_enable', 'off' );
			}

			if ( ! get_option( 'aio_login_change_login_url_enable' ) ) {
				update_option( 'aio_login_change_login_url_enable', 'off' );
			}

			if ( ! get_option( 'aio_login_user_enumeration_enable' ) ) {
				update_option( 'aio_login_user_enumeration_enable', 'off' );
			}

			// Flush rewrite rules
			flush_rewrite_rules();
		}

		/**
		 * Uninstall AIO Login plugin.
		 */
		public static function uninstall_plugin() {
			\AIO_Login\Helper\Helper::drop_table( 'login_attempts' );
			\AIO_Login\Helper\Helper::drop_table( 'login_lockouts' );
			\AIO_Login\Helper\Helper::drop_table( 'aio_login_enumeration_logs' );

			global $wpdb;
			$sql = "DELETE FROM %i WHERE option_name LIKE 'aio_login%'";
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$sql,
					$wpdb->options
				)
			);
		}

		/**
		 * Load textdomain.
		 */
		public function load_textdomain() {
			load_plugin_textdomain(
				'change-wp-admin-login',
				false,
				dirname( plugin_basename( AIO_LOGIN__FILE ) ) . '/languages'
			);
		}

		/**
		 * If activation hook not triggered.
		 */
		public function if_activation_hook_not_triggered() {
			if ( is_admin() && 'true' !== get_option( 'aio_login__update' ) ) {
				Helper::create_table(
					'login_attempts',
					array(
						'user_login' => 'varchar(255) NOT NULL',
						'ip_address' => 'varchar(255) NOT NULL',
						'country'    => 'varchar(255) NOT NULL',
						'city'       => 'varchar(255) NOT NULL',
						'time'       => 'VARCHAR(255) NOT NULL',
						'user_agent' => 'varchar(255) NOT NULL',
						'status'     => 'varchar(255) NOT NULL DEFAULT ""',
					)
				);
				Helper::create_table(
					'login_lockouts',
					array(
						'ip_address' => 'varchar(255) NOT NULL',
						'country'    => 'varchar(255) NOT NULL',
						'city'       => 'varchar(255) NOT NULL',
						'time'       => 'bigint(20) NOT NULL',
						'user_agent' => 'varchar(255) NOT NULL',
					)
				);

				\AIO_Login\User_Enumeration_Protection\User_Enumeration_Activator::create_table();
				\AIO_Login\Passwordless_Otp\OTP_Settings::set_defaults();

				update_option( 'aio_login__update', 'true' );
			}

			if ( ! get_option( 'aio_login__version' ) ) {
				// Only set default if the option doesn't exist
				if ( ! get_option( 'aio_login__cwpal_enable' ) ) {
					update_option( 'aio_login__cwpal_enable', 'on' );
				}
			}

		}

		/**
		 * New site registered.
		 *
		 * @param \WP_Site $new_site New site.
		 */
		 public function new_site_registered( $new_site ) {
			$site_id = $new_site->blog_id;
			switch_to_blog( $site_id );
			$this->activate_plugin();
			restore_current_blog();
		}

		/**
		 * Get instance.
		 *
		 * @return AIO_Login
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
