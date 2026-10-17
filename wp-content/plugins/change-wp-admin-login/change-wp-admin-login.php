<?php
/**
 * Plugin Name: All In One Login
 * Plugin URI: https://aiologin.com/
 * Author: AIO Login
 * Author URI: https://aiologin.com/
 * Description: AIO Login is a top-notch WordPress admin security plugin that empowers you to secure and customize WordPress login page (wp-admin) at the same time. Which means it offers robust security features and extensive customization options.
 * Version: 2.4.1
 * Text Domain: change-wp-admin-login
 * Domain Path: /languages
 *
 * @package All In One Login
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'AIO_LOGIN__FILE' ) ) {
	define( 'AIO_LOGIN__FILE', __FILE__ );
}

require_once plugin_dir_path( __FILE__ ) . 'aio-login.php';
