<?php
/**
 * Main Loader file for the plugin.
 *
 * @package AIO Login
 */

defined( 'ABSPATH' ) || exit;

global $all_in_one_login;

if ( is_null( $all_in_one_login ) ) {
	define( 'AIO_LOGIN__DIR_PATH', plugin_dir_path( AIO_LOGIN__FILE ) );
	define( 'AIO_LOGIN__DIR_URL', plugin_dir_url( AIO_LOGIN__FILE ) );
	define( 'AIO_LOGIN__VERSION', '2.4.1' );
	define(
		'AIO_LOGIN_GET_PRO_URL',
		'https://aiologin.com/pricing/?utm_source=plugn-redirect&utm_medium=upgrade-to-pro&utm_campaign=get-pro&utm_id=plugin'
	);

	require_once AIO_LOGIN__DIR_PATH . 'includes/freemius.php';
	require_once AIO_LOGIN__DIR_PATH . 'includes/class-aio-login.php';

	$all_in_one_login = AIO_Login\AIO_Login::get_instance();
}

return $all_in_one_login;
