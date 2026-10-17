<?php
/**
 * User Enumeration Protection Activator
 *
 * @package All In One Login
 */

namespace AIO_Login\User_Enumeration_Protection;

defined( 'ABSPATH' ) || exit;

/**
 * Class User_Enumeration_Activator
 */
class User_Enumeration_Activator {

	/**
	 * Create database table for enumeration logs
	 */
	public static function create_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'aio_login_enumeration_logs';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			ip_address varchar(45) NOT NULL,
			type varchar(50) NOT NULL,
			username varchar(255) DEFAULT '',
			user_agent text,
			blocked_until datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY ip_address (ip_address),
			KEY type (type),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
} 